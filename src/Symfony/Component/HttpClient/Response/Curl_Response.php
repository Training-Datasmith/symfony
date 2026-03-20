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
namespace Symfony\Component\Http_Client\Response;

use Psr\Log\Logger_Interface;
use Symfony\Component\Http_Client\Chunk\First_Chunk;
use Symfony\Component\Http_Client\Chunk\Informational_Chunk;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Component\Http_Client\Internal\Canary;
use Symfony\Component\Http_Client\Internal\Client_State;
use Symfony\Component\Http_Client\Internal\Curl_Client_State;
use Symfony\Contracts\Http_Client\Response_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class Curl_Response implements Response_Interface, Streamable_Interface
{
    use Common_Response_Trait {
        getContent as private doGetContent;
    }
    use Transport_Response_Trait;
    /**
     * @var resource
     */
    private $debug_buffer;
    /**
     * @internal
     */
    public function __construct(private Curl_Client_State $multi, \Curl_Handle|string $ch, ?array $options = null, ?Logger_Interface $logger = null, string $method = 'GET', ?callable $resolve_redirect = null, ?int $curl_version = null, ?string $original_url = null)
    {
        if ($ch instanceof \Curl_Handle) {
            $this->handle = $ch;
            $this->debug_buffer = fopen('php://temp', 'w+');
            if (0x74000 === $curl_version) {
                fwrite($this->debug_buffer, 'Due to a bug in curl 7.64.0, the debug log is disabled; use another version to work around the issue.');
            } else {
                curl_setopt($ch, \CURLOPT_VERBOSE, true);
                curl_setopt($ch, \CURLOPT_STDERR, $this->debug_buffer);
            }
        } else {
            $this->info['url'] = $ch;
            $ch = $this->handle;
        }
        $this->id = $id = (int) $ch;
        $this->logger = $logger;
        $this->should_buffer = $options['buffer'] ?? true;
        $this->timeout = $options['timeout'] ?? null;
        $this->info['http_method'] = $method;
        $this->info['user_data'] = $options['user_data'] ?? null;
        $this->info['max_duration'] = $options['max_duration'] ?? null;
        $this->info['max_connect_duration'] = $options['max_connect_duration'] ?? null;
        $this->info['start_time'] ??= microtime(true);
        $this->info['original_url'] = $original_url ?? $this->info['url'] ?? curl_getinfo($ch, \CURLINFO_EFFECTIVE_URL);
        $info =& $this->info;
        $headers =& $this->headers;
        $debug_buffer = $this->debug_buffer;
        if (!$info['response_headers']) {
            // Used to keep track of what we're waiting for
            curl_setopt($ch, \CURLOPT_PRIVATE, \in_array($method, ['GET', 'HEAD', 'OPTIONS', 'TRACE', 'QUERY'], true) && 1.0 < (float) ($options['http_version'] ?? 1.1) ? 'H2' : 'H0');
            // H = headers + retry counter
        }
        curl_setopt($ch, \CURLOPT_HEADERFUNCTION, static function ($ch, string $data) use (&$info, &$headers, $options, $multi, $id, &$location, $resolve_redirect, $logger): int {
            return self::parse_header_line($ch, $data, $info, $headers, $options, $multi, $id, $location, $resolve_redirect, $logger);
        });
        if (null === $options) {
            // Pushed response: buffer until requested
            curl_setopt($ch, \CURLOPT_WRITEFUNCTION, static function ($ch, string $data) use ($multi, $id): int {
                $multi->handles_activity[$id][] = $data;
                curl_pause($ch, \CURLPAUSE_RECV);
                return \strlen($data);
            });
            return;
        }
        $exec_counter = $multi->exec_counter;
        $this->info['pause_handler'] = static function (float $duration) use ($ch, $multi, $exec_counter): void {
            if (0 < $duration) {
                if ($exec_counter === $multi->exec_counter) {
                    curl_multi_remove_handle($multi->handle, $ch);
                }
                $last_expiry = end($multi->pause_expiries);
                $multi->pause_expiries[(int) $ch] = $duration += hrtime(true) / 1000000000.0;
                if (false !== $last_expiry && $last_expiry > $duration) {
                    asort($multi->pause_expiries);
                }
                curl_pause($ch, \CURLPAUSE_ALL);
            } else {
                unset($multi->pause_expiries[(int) $ch]);
                curl_pause($ch, \CURLPAUSE_CONT);
                curl_multi_add_handle($multi->handle, $ch);
            }
        };
        $this->inflate = !isset($options['normalized_headers']['accept-encoding']);
        curl_pause($ch, \CURLPAUSE_CONT);
        if ($on_progress = $options['on_progress']) {
            $url = isset($info['url']) ? ['url' => $info['url']] : [];
            curl_setopt($ch, \CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, \CURLOPT_PROGRESSFUNCTION, static function ($ch, $dl_size, $dl_now) use ($on_progress, &$info, $url, $multi, $debug_buffer): ?int {
                try {
                    $info['debug'] ??= '';
                    rewind($debug_buffer);
                    if (fstat($debug_buffer)['size']) {
                        $info['debug'] .= stream_get_contents($debug_buffer);
                        rewind($debug_buffer);
                        ftruncate($debug_buffer, 0);
                    }
                    $on_progress($dl_now, $dl_size, $url + curl_getinfo($ch) + $info);
                } catch (\Throwable $e) {
                    $multi->handles_activity[(int) $ch][] = null;
                    $multi->handles_activity[(int) $ch][] = $e;
                    return 1;
                    // Abort the request
                }
                return null;
            });
        }
        curl_setopt($ch, \CURLOPT_WRITEFUNCTION, static function ($ch, string $data) use ($multi, $id): int {
            if ('H' === (curl_getinfo($ch, \CURLINFO_PRIVATE)[0] ?? null)) {
                $multi->handles_activity[$id][] = null;
                $multi->handles_activity[$id][] = new Transport_Exception(\sprintf('Unsupported protocol for "%s"', curl_getinfo($ch, \CURLINFO_EFFECTIVE_URL)));
                return 0;
            }
            curl_setopt($ch, \CURLOPT_WRITEFUNCTION, static function ($ch, string $data) use ($multi, $id): int {
                $multi->handles_activity[$id][] = $data;
                return \strlen($data);
            });
            $multi->handles_activity[$id][] = $data;
            return \strlen($data);
        });
        $this->initializer = static function (self $response): bool {
            $wait_for = curl_getinfo($response->handle, \CURLINFO_PRIVATE);
            return 'H' === $wait_for[0];
        };
        // Schedule the request in a non-blocking way
        $multi->last_timeout = null;
        $multi->open_handles[$id] = [$ch, $options];
        curl_multi_add_handle($multi->handle, $ch);
        $this->canary = new Canary(static function () use ($ch, $multi, $id): void {
            unset($multi->pause_expiries[$id], $multi->open_handles[$id], $multi->handles_activity[$id]);
            curl_setopt($ch, \CURLOPT_PRIVATE, '_0');
            if ($multi->performing) {
                return;
            }
            curl_multi_remove_handle($multi->handle, $ch);
            curl_setopt_array($ch, [\CURLOPT_NOPROGRESS => true, \CURLOPT_PROGRESSFUNCTION => null, \CURLOPT_HEADERFUNCTION => null, \CURLOPT_WRITEFUNCTION => null, \CURLOPT_READFUNCTION => null, \CURLOPT_INFILE => null]);
            if (!$multi->open_handles) {
                // Schedule DNS cache eviction for the next request
                $multi->dns_cache->evictions = $multi->dns_cache->evictions ?: $multi->dns_cache->removals;
                $multi->dns_cache->removals = $multi->dns_cache->hostnames = [];
            }
        });
    }
    public function get_info(?string $type = null): mixed
    {
        if (!$info = $this->final_info) {
            $info = array_merge($this->info, curl_getinfo($this->handle));
            $info['redirect_url'] = $this->info['redirect_url'] ?? null;
            // workaround curl not subtracting the time offset for pushed responses
            if (isset($this->info['url']) && $info['start_time'] / 1000 < $info['total_time']) {
                $info['total_time'] -= $info['starttransfer_time'] ?: $info['total_time'];
                $info['starttransfer_time'] = 0.0;
            }
            $info['debug'] ??= '';
            rewind($this->debug_buffer);
            if (fstat($this->debug_buffer)['size']) {
                $info['debug'] .= stream_get_contents($this->debug_buffer);
                rewind($this->debug_buffer);
                ftruncate($this->debug_buffer, 0);
            }
            $this->info = array_merge($this->info, $info);
            $wait_for = curl_getinfo($this->handle, \CURLINFO_PRIVATE);
            if ('H' !== $wait_for[0] && 'C' !== $wait_for[0]) {
                curl_setopt($this->handle, \CURLOPT_VERBOSE, false);
                $this->final_info = $info;
            }
        }
        return null !== $type ? $info[$type] ?? null : $info;
    }
    public function get_content(bool $throw = true): string
    {
        $performing = $this->multi->performing;
        $this->multi->performing = $performing || '_0' === curl_getinfo($this->handle, \CURLINFO_PRIVATE);
        try {
            return $this->do_get_content($throw);
        } finally {
            $this->multi->performing = $performing;
        }
    }
    public function __destruct()
    {
        try {
            if (null === $this->timeout) {
                return;
                // Unused pushed response
            }
            $this->do_destruct();
        } finally {
            if ($this->handle instanceof \Curl_Handle) {
                curl_setopt($this->handle, \CURLOPT_VERBOSE, false);
            }
        }
    }
    private static function schedule(self $response, array &$running_responses): void
    {
        if (isset($running_responses[$i = (int) $response->multi->handle])) {
            $running_responses[$i][1][$response->id] = $response;
        } else {
            $running_responses[$i] = [$response->multi, [$response->id => $response]];
        }
        if ('_0' === curl_getinfo($response->handle, \CURLINFO_PRIVATE)) {
            // Response already completed
            $response->multi->handles_activity[$response->id][] = null;
            $response->multi->handles_activity[$response->id][] = null !== $response->info['error'] ? new Transport_Exception($response->info['error']) : null;
        }
    }
    /**
     * @param CurlClientState $multi
     */
    private static function perform(Client_State $multi, ?array $responses = null): void
    {
        if ($multi->performing) {
            if ($responses) {
                $response = $responses[array_key_first($responses)];
                $multi->handles_activity[(int) $response->handle][] = null;
                $multi->handles_activity[(int) $response->handle][] = new Transport_Exception(\sprintf('Userland callback cannot use the client nor the response while processing "%s".', curl_getinfo($response->handle, \CURLINFO_EFFECTIVE_URL)));
            }
            return;
        }
        try {
            $multi->performing = true;
            ++$multi->exec_counter;
            $active = 0;
            while (\CURLM_CALL_MULTI_PERFORM === $err = curl_multi_exec($multi->handle, $active)) {
            }
            if (\CURLM_OK !== $err) {
                throw new Transport_Exception(curl_multi_strerror($err));
            }
            while ($info = curl_multi_info_read($multi->handle)) {
                if (\CURLMSG_DONE !== $info['msg']) {
                    continue;
                }
                $result = $info['result'];
                $id = (int) $ch = $info['handle'];
                $wait_for = @curl_getinfo($ch, \CURLINFO_PRIVATE) ?: '_0';
                if (\in_array($result, [
                    \CURLE_SEND_ERROR,
                    \CURLE_RECV_ERROR,
                    /* CURLE_HTTP2 */
                    16,
                    /* CURLE_HTTP2_STREAM */
                    92,
                ], true) && $wait_for[1] && 'C' !== $wait_for[0]) {
                    curl_multi_remove_handle($multi->handle, $ch);
                    $wait_for[1] = (string) ((int) $wait_for[1] - 1);
                    // decrement the retry counter
                    curl_setopt($ch, \CURLOPT_PRIVATE, $wait_for);
                    curl_setopt($ch, \CURLOPT_FORBID_REUSE, true);
                    if (0 === curl_multi_add_handle($multi->handle, $ch)) {
                        continue;
                    }
                }
                if (\CURLE_RECV_ERROR === $result && 'H' === $wait_for[0] && 400 <= ($responses[(int) $ch]->info['http_code'] ?? 0)) {
                    $multi->handles_activity[$id][] = new First_Chunk();
                    curl_setopt($ch, \CURLOPT_PRIVATE, 'C' . $wait_for[1]);
                }
                $multi->handles_activity[$id][] = null;
                $multi->handles_activity[$id][] = \in_array($result, [\CURLE_OK, \CURLE_TOO_MANY_REDIRECTS], true) || '_0' === $wait_for || curl_getinfo($ch, \CURLINFO_SIZE_DOWNLOAD) === curl_getinfo($ch, \CURLINFO_CONTENT_LENGTH_DOWNLOAD) || 'C' === $wait_for[0] && 'OpenSSL SSL_read: SSL_ERROR_SYSCALL, errno 0' === curl_error($ch) && -1.0 === curl_getinfo($ch, \CURLINFO_CONTENT_LENGTH_DOWNLOAD) && \in_array('close', array_map(strtolower(...), $responses[$id]->headers['connection'] ?? []), true) ? null : new Transport_Exception(ucfirst(curl_error($ch) ?: (string) curl_strerror($result)) . \sprintf(' for "%s".', curl_getinfo($ch, \CURLINFO_EFFECTIVE_URL)));
            }
        } finally {
            $multi->performing = false;
        }
    }
    /**
     * @param CurlClientState $multi
     */
    private static function select(Client_State $multi, float $timeout): int
    {
        if ($multi->pause_expiries) {
            $now = hrtime(true) / 1000000000.0;
            foreach ($multi->pause_expiries as $id => $pause_expiry) {
                if ($now < $pause_expiry) {
                    $timeout = min($timeout, $pause_expiry - $now);
                    break;
                }
                unset($multi->pause_expiries[$id]);
                curl_pause($multi->open_handles[$id][0], \CURLPAUSE_CONT);
                curl_multi_add_handle($multi->handle, $multi->open_handles[$id][0]);
            }
        }
        if (0 !== $selected = curl_multi_select($multi->handle, $timeout)) {
            return $selected;
        }
        if ($multi->pause_expiries && 0 < $timeout -= hrtime(true) / 1000000000.0 - $now) {
            usleep((int) (1000000.0 * $timeout));
        }
        return 0;
    }
    /**
     * Parses header lines as curl yields them to us.
     */
    private static function parse_header_line($ch, string $data, array &$info, array &$headers, ?array $options, Curl_Client_State $multi, int $id, ?string &$location, ?callable $resolve_redirect, ?Logger_Interface $logger): int
    {
        if (!str_ends_with($data, "\r\n")) {
            return 0;
        }
        $wait_for = @curl_getinfo($ch, \CURLINFO_PRIVATE) ?: '_0';
        if ('H' !== $wait_for[0]) {
            return \strlen($data);
            // Ignore HTTP trailers
        }
        $status_code = curl_getinfo($ch, \CURLINFO_RESPONSE_CODE);
        if ($status_code !== $info['http_code'] && !preg_match("#^HTTP/\\d+(?:\\.\\d+)? {$status_code}(?: |\r\n\$)#", $data)) {
            return \strlen($data);
            // Ignore headers from responses to CONNECT requests
        }
        if ("\r\n" !== $data) {
            // Regular header line: add it to the list
            self::add_response_headers([substr($data, 0, -2)], $info, $headers);
            if (!str_starts_with($data, 'HTTP/')) {
                if (0 === stripos($data, 'Location:')) {
                    $location = trim(substr($data, 9, -2));
                }
                return \strlen($data);
            }
            if (\function_exists('openssl_x509_read') && $certinfo = curl_getinfo($ch, \CURLINFO_CERTINFO)) {
                $info['peer_certificate_chain'] = array_map(openssl_x509_read(...), array_column($certinfo, 'Cert'));
            }
            if (300 <= $info['http_code'] && $info['http_code'] < 400 && null !== $options) {
                if (curl_getinfo($ch, \CURLINFO_REDIRECT_COUNT) === $options['max_redirects']) {
                    curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, false);
                } elseif (303 === $info['http_code'] || 'POST' === $info['http_method'] && \in_array($info['http_code'], [301, 302], true)) {
                    curl_setopt($ch, \CURLOPT_POSTFIELDS, '');
                }
            }
            return \strlen($data);
        }
        // End of headers: handle informational responses, redirects, etc.
        if (200 > $status_code) {
            $multi->handles_activity[$id][] = new Informational_Chunk($status_code, $headers);
            $location = null;
            return \strlen($data);
        }
        $info['redirect_url'] = null;
        if (300 <= $status_code && $status_code < 400 && null !== $location && null !== $options) {
            if ($no_content = 303 === $status_code || 'POST' === $info['http_method'] && \in_array($status_code, [301, 302], true)) {
                $info['http_method'] = 'HEAD' === $info['http_method'] ? 'HEAD' : 'GET';
                curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, $info['http_method']);
            }
            if (null === $info['redirect_url'] = $resolve_redirect($ch, $location, $no_content)) {
                $options['max_redirects'] = curl_getinfo($ch, \CURLINFO_REDIRECT_COUNT);
                curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($ch, \CURLOPT_MAXREDIRS, $options['max_redirects']);
            }
        }
        if (401 === $status_code && isset($options['auth_ntlm']) && 0 === strncasecmp($headers['www-authenticate'][0] ?? '', 'NTLM ', 5)) {
            // Continue with NTLM auth
        } elseif ($status_code < 300 || 400 <= $status_code || null === $location || null === $options || curl_getinfo($ch, \CURLINFO_REDIRECT_COUNT) === $options['max_redirects']) {
            // Headers and redirects completed, time to get the response's content
            $multi->handles_activity[$id][] = new First_Chunk();
            if ('HEAD' === $info['http_method'] || \in_array($status_code, [204, 304], true)) {
                $wait_for = '_0';
                // no content expected
                $multi->handles_activity[$id][] = null;
                $multi->handles_activity[$id][] = null;
            } else {
                $wait_for[0] = 'C';
                // C = content
            }
            curl_setopt($ch, \CURLOPT_PRIVATE, $wait_for);
        } elseif (null !== $info['redirect_url'] && $logger) {
            $logger->info(\sprintf('Redirecting: "%s %s"', $info['http_code'], $info['redirect_url']));
        }
        $location = null;
        return \strlen($data);
    }
}