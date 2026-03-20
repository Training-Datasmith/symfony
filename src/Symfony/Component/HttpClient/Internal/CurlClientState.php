<?php

declare (strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Component\Http_Client\Internal;

use Psr\Log\Logger_Interface;
use Symfony\Component\Http_Client\Response\Curl_Response;
/**
 * Internal representation of the cURL client's state.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
final class Curl_Client_State extends Client_State
{
    public ?\Curl_Multi_Handle $handle = null;
    public ?\Curl_Share_Handle $share = null;
    public \Curl_Share_Handle|\Curl_Share_Persistent_Handle|null $persistent_share = null;
    public bool $performing = false;
    /** @var PushedResponse[] */
    public array $pushed_responses = [];
    public Dns_Cache $dns_cache;
    /** @var float[] */
    public array $pause_expiries = [];
    public int $exec_counter = \PHP_INT_MIN;
    public ?Logger_Interface $logger = null;
    public static array $curl_version;
    public function __construct(private int $max_host_connections, private int $max_pending_pushes)
    {
        self::$curl_version ??= curl_version();
        $this->dns_cache = new Dns_Cache();
        // handle, share and persistentShare are initialized lazily in __get()
        unset($this->handle, $this->share, $this->persistent_share);
    }
    public function reset(): void
    {
        foreach ($this->pushed_responses as $url => $response) {
            $this->logger?->debug(\sprintf('Unused pushed response: "%s"', $url));
            curl_multi_remove_handle($this->handle, $response->handle);
        }
        $this->pushed_responses = [];
        $this->dns_cache->evictions = $this->dns_cache->evictions ?: $this->dns_cache->removals;
        $this->dns_cache->removals = $this->dns_cache->hostnames = [];
        unset($this->share);
    }
    public function __get(string $name): mixed
    {
        if ('persistentShare' === $name) {
            if (\PHP_VERSION_ID < 80500) {
                return $this->persistent_share = $this->share;
            }
            return $this->persistent_share = curl_share_init_persistent([\CURL_LOCK_DATA_DNS, \CURL_LOCK_DATA_SSL_SESSION, \CURL_LOCK_DATA_CONNECT]);
        }
        if ('share' === $name) {
            $this->share = curl_share_init();
            curl_share_setopt($this->share, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_DNS);
            curl_share_setopt($this->share, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_SSL_SESSION);
            curl_share_setopt($this->share, \CURLSHOPT_SHARE, \CURL_LOCK_DATA_CONNECT);
            return $this->share;
        }
        if ('handle' === $name) {
            $this->handle = curl_multi_init();
            // Don't enable HTTP/1.1 pipelining: it forces responses to be sent in order
            if (\defined('CURLPIPE_MULTIPLEX')) {
                curl_multi_setopt($this->handle, \CURLMOPT_PIPELINING, \CURLPIPE_MULTIPLEX);
            }
            $max_host_connections = $this->max_host_connections;
            if (\defined('CURLMOPT_MAX_HOST_CONNECTIONS') && 0 < $max_host_connections) {
                $max_host_connections = curl_multi_setopt($this->handle, \CURLMOPT_MAX_HOST_CONNECTIONS, $max_host_connections) ? min(50 * $max_host_connections, 4294967295) : $max_host_connections;
            }
            if (\defined('CURLMOPT_MAXCONNECTS') && 0 < $max_host_connections) {
                curl_multi_setopt($this->handle, \CURLMOPT_MAXCONNECTS, $max_host_connections);
            }
            // Skip configuring HTTP/2 push when it's unsupported or buggy, see https://bugs.php.net/77535
            if (0 < $this->max_pending_pushes && (\defined('CURLMOPT_PUSHFUNCTION') && 0x73d00 <= self::$curl_version['version_number'] && \CURL_VERSION_HTTP2 & self::$curl_version['features'])) {
                // Clone to prevent a circular reference
                $multi = clone $this;
                $multi->handle = null;
                $multi->share = null;
                $multi->persistent_share = null;
                $multi->pushed_responses =& $this->pushed_responses;
                $multi->logger =& $this->logger;
                $multi->handles_activity =& $this->handles_activity;
                $multi->open_handles =& $this->open_handles;
                curl_multi_setopt($this->handle, \CURLMOPT_PUSHFUNCTION, $multi->handle_push(...));
            }
            return $this->handle;
        }
        throw new \LogicException(\sprintf('Unknown property "%s" on "%s".', $name, self::class));
    }
    private function handle_push($parent, $pushed, array $request_headers): int
    {
        $headers = [];
        $origin = curl_getinfo($parent, \CURLINFO_EFFECTIVE_URL);
        foreach ($request_headers as $h) {
            if (false !== $i = strpos((string) $h, ':', 1)) {
                $headers[substr((string) $h, 0, $i)][] = substr((string) $h, 1 + $i);
            }
        }
        if (!isset($headers[':method']) || !isset($headers[':scheme']) || !isset($headers[':authority']) || !isset($headers[':path'])) {
            $this->logger?->debug(\sprintf('Rejecting pushed response from "%s": pushed headers are invalid', $origin));
            return \CURL_PUSH_DENY;
        }
        $url = $headers[':scheme'][0] . '://' . $headers[':authority'][0];
        // curl before 7.65 doesn't validate the pushed ":authority" header,
        // but this is a MUST in the HTTP/2 RFC; let's restrict pushes to the original host,
        // ignoring domains mentioned as alt-name in the certificate for now (same as curl).
        if (!str_starts_with($origin, $url . '/')) {
            $this->logger?->debug(\sprintf('Rejecting pushed response from "%s": server is not authoritative for "%s"', $origin, $url));
            return \CURL_PUSH_DENY;
        }
        if ($this->max_pending_pushes <= \count($this->pushed_responses)) {
            $fifo_url = key($this->pushed_responses);
            unset($this->pushed_responses[$fifo_url]);
            $this->logger?->debug(\sprintf('Evicting oldest pushed response: "%s"', $fifo_url));
        }
        $url .= $headers[':path'][0];
        $this->logger?->debug(\sprintf('Queueing pushed response: "%s"', $url));
        $this->pushed_responses[$url] = new Pushed_Response(new Curl_Response($this, $pushed), $headers, $this->open_handles[(int) $parent][1] ?? [], $pushed);
        return \CURL_PUSH_OK;
    }
}