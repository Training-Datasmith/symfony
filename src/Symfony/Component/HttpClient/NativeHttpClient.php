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
namespace Symfony\Component\Http_Client;

use Psr\Log\Logger_Aware_Interface;
use Psr\Log\Logger_Aware_Trait;
use Symfony\Component\Http_Client\Exception\InvalidArgumentException;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Component\Http_Client\Internal\Native_Client_State;
use Symfony\Component\Http_Client\Response\Native_Response;
use Symfony\Component\Http_Client\Response\Response_Stream;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Http_Client\Response_Stream_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * A portable implementation of the HttpClientInterface contracts based on PHP stream wrappers.
 *
 * PHP stream wrappers are able to fetch response bodies concurrently,
 * but each request is opened synchronously.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Native_Http_Client implements Http_Client_Interface, Logger_Aware_Interface, Reset_Interface
{
    use Http_Client_Trait;
    use Logger_Aware_Trait;
    public const OPTIONS_DEFAULTS = Http_Client_Interface::OPTIONS_DEFAULTS + ['crypto_method' => \Stream_crypto_method_tl_Sv1_2_client];
    private array $default_options = self::OPTIONS_DEFAULTS;
    private static array $empty_defaults = self::OPTIONS_DEFAULTS;
    private Native_Client_State $multi;
    /**
     * @param array $defaultOptions     Default request's options
     * @param int   $maxHostConnections The maximum number of connections to open
     *
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public function __construct(array $default_options = [], int $max_host_connections = 6)
    {
        $this->default_options['buffer'] ??= self::should_buffer(...);
        if ($default_options) {
            [, $this->default_options] = self::prepare_request(null, null, $default_options, $this->default_options);
        }
        $this->multi = new Native_Client_State();
        $this->multi->max_host_connections = 0 < $max_host_connections ? $max_host_connections : \PHP_INT_MAX;
    }
    /**
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        [$url, $options] = self::prepare_request($method, $url, $options, $this->default_options);
        if ($options['bindto']) {
            if (file_exists($options['bindto'])) {
                throw new Transport_Exception(self::class . ' cannot bind to local Unix sockets, use e.g. CurlHttpClient instead.');
            }
            if (str_starts_with((string) $options['bindto'], 'if!')) {
                throw new Transport_Exception(self::class . ' cannot bind to network interfaces, use e.g. CurlHttpClient instead.');
            }
            if (str_starts_with((string) $options['bindto'], 'host!')) {
                $options['bindto'] = substr((string) $options['bindto'], 5);
            }
        }
        $has_content_length = isset($options['normalized_headers']['content-length']);
        $has_body = '' !== $options['body'] || 'POST' === $method || $has_content_length;
        $options['body'] = self::get_body_as_string($options['body']);
        if ('chunked' === substr($options['normalized_headers']['transfer-encoding'][0] ?? '', \strlen('Transfer-Encoding: '))) {
            unset($options['normalized_headers']['transfer-encoding']);
            $options['headers'] = array_merge(...array_values($options['normalized_headers']));
            $options['body'] = self::dechunk($options['body']);
        }
        if ('' === $options['body'] && $has_body && !$has_content_length) {
            $options['headers'][] = 'Content-Length: 0';
        }
        if ($has_body && !isset($options['normalized_headers']['content-type'])) {
            $options['headers'][] = 'Content-Type: application/x-www-form-urlencoded';
        }
        if (\extension_loaded('zlib') && !isset($options['normalized_headers']['accept-encoding'])) {
            // gzip is the most widely available algo, no need to deal with deflate
            $options['headers'][] = 'Accept-Encoding: gzip';
        }
        if ($options['peer_fingerprint']) {
            if (isset($options['peer_fingerprint']['pin-sha256']) && 1 === \count($options['peer_fingerprint'])) {
                throw new Transport_Exception(self::class . ' cannot verify "pin-sha256" fingerprints, please provide a "sha256" one.');
            }
            unset($options['peer_fingerprint']['pin-sha256']);
        }
        $info = ['response_headers' => [], 'url' => $url, 'error' => null, 'canceled' => false, 'http_method' => $method, 'http_code' => 0, 'redirect_count' => 0, 'start_time' => 0.0, 'connect_time' => 0.0, 'redirect_time' => 0.0, 'pretransfer_time' => 0.0, 'starttransfer_time' => 0.0, 'total_time' => 0.0, 'namelookup_time' => 0.0, 'size_upload' => 0, 'size_download' => 0, 'size_body' => \strlen((string) $options['body']), 'primary_ip' => '', 'primary_port' => 'http:' === $url['scheme'] ? 80 : 443, 'debug' => \extension_loaded('curl') ? '' : "* Enable the curl extension for better performance\n"];
        if ($on_progress = $options['on_progress']) {
            $max_duration = 0 < $options['max_duration'] ? $options['max_duration'] : \INF;
            $on_progress = static function (...$progress) use ($on_progress, &$info, $max_duration): void {
                if ($info['total_time'] >= $max_duration) {
                    throw new Transport_Exception(\sprintf('Max duration was reached for "%s".', implode('', $info['url'])));
                }
                $progress_info = $info;
                $progress_info['url'] = implode('', $info['url']);
                unset($progress_info['size_body']);
                // Memoize the last progress to ease calling the callback periodically when no network transfer happens
                static $last_progress = [0, 0];
                if ($progress && -1 === $progress[0]) {
                    // Response completed
                    $last_progress[0] = max($last_progress);
                } else {
                    $last_progress = $progress ?: $last_progress;
                }
                $on_progress($last_progress[0], $last_progress[1], $progress_info);
            };
        } elseif (0 < $options['max_duration']) {
            $max_duration = $options['max_duration'];
            $on_progress = static function () use (&$info, $max_duration): void {
                if ($info['total_time'] >= $max_duration) {
                    throw new Transport_Exception(\sprintf('Max duration was reached for "%s".', implode('', $info['url'])));
                }
            };
        }
        // Always register a notification callback to compute live stats about the response
        $notification = static function (int $code, int $severity, ?string $msg, int $msg_code, int $dl_now, int $dl_size) use ($on_progress, &$info): void {
            $info['total_time'] = microtime(true) - $info['start_time'];
            if (\STREAM_NOTIFY_PROGRESS === $code) {
                $info['starttransfer_time'] = $info['starttransfer_time'] ?: $info['total_time'];
                $info['size_upload'] += $dl_now ? 0 : $info['size_body'];
                $info['size_download'] = $dl_now;
            } elseif (\STREAM_NOTIFY_CONNECT === $code) {
                $info['connect_time'] = $info['total_time'];
                $info['debug'] .= $info['request_header'];
                unset($info['request_header']);
            } else {
                return;
            }
            if ($on_progress) {
                $on_progress($dl_now, $dl_size);
            }
        };
        if ($options['resolve']) {
            $this->multi->dns_cache = $options['resolve'] + $this->multi->dns_cache;
        }
        $this->logger?->info(\sprintf('Request: "%s %s"', $method, implode('', $url)));
        if (!isset($options['normalized_headers']['user-agent'])) {
            $options['headers'][] = 'User-Agent: Symfony HttpClient (Native)';
        }
        if (0 < $options['max_duration']) {
            $options['timeout'] = min($options['max_duration'], $options['timeout']);
        }
        if (\PHP_INT_SIZE === 4 && 2147 < $options['timeout']) {
            $options['timeout'] = 2147;
            // fopen() on x86 doesn't support longer timeouts
        }
        switch ($crypto_method = $options['crypto_method']) {
            case \Stream_crypto_method_tl_Sv1_0_client:
                $crypto_method |= \Stream_crypto_method_tl_Sv1_1_client;
            // no break
            case \Stream_crypto_method_tl_Sv1_1_client:
                $crypto_method |= \Stream_crypto_method_tl_Sv1_2_client;
            // no break
            case \Stream_crypto_method_tl_Sv1_2_client:
                $crypto_method |= \Stream_crypto_method_tl_Sv1_3_client;
        }
        $context = ['http' => [
            'protocol_version' => min($options['http_version'] ?: '1.1', '1.1'),
            'method' => $method,
            'content' => $options['body'],
            'ignore_errors' => true,
            'curl_verify_ssl_peer' => $options['verify_peer'],
            'curl_verify_ssl_host' => $options['verify_host'],
            'auto_decode' => false,
            // Disable dechunk filter, it's incompatible with stream_select()
            'timeout' => 0 < $options['max_connect_duration'] ? min($options['timeout'], $options['max_connect_duration']) : $options['timeout'],
            'follow_location' => false,
        ], 'ssl' => array_filter(['verify_peer' => $options['verify_peer'], 'verify_peer_name' => $options['verify_host'], 'cafile' => $options['cafile'], 'capath' => $options['capath'], 'local_cert' => $options['local_cert'], 'local_pk' => $options['local_pk'], 'passphrase' => $options['passphrase'], 'ciphers' => $options['ciphers'], 'peer_fingerprint' => $options['peer_fingerprint'], 'capture_peer_cert_chain' => $options['capture_peer_cert_chain'], 'allow_self_signed' => (bool) $options['peer_fingerprint'], 'SNI_enabled' => true, 'disable_compression' => true, 'crypto_method' => $crypto_method], static fn($v): bool => null !== $v), 'socket' => ['bindto' => $options['bindto'], 'tcp_nodelay' => true]];
        $context = stream_context_create($context, ['notification' => $notification]);
        $resolver = static function (\Symfony\Component\Http_Client\Internal\Native_Client_State $multi) use ($context, $options, $url, &$info, $on_progress): array {
            $authority = $url['authority'];
            [$host, $port] = self::parse_host_port($url, $info);
            if (!isset($options['normalized_headers']['host'])) {
                $options['headers'][] = 'Host: ' . $host . $port;
            }
            $proxy = self::get_proxy($options['proxy'], $url, $options['no_proxy']);
            if (!self::configure_headers_and_proxy($context, $host, $options['headers'], $proxy, 'https:' === $url['scheme'])) {
                $ip = self::dns_resolve($host, $multi, $info, $on_progress);
                $url['authority'] = substr_replace($url['authority'], $ip, -\strlen($host) - \strlen($port), \strlen($host));
            }
            return [self::create_redirect_resolver($options, $authority, $proxy, $info, $on_progress), implode('', $url)];
        };
        return new Native_Response($this->multi, $context, implode('', $url), $options, $info, $resolver, $on_progress, $this->logger);
    }
    public function stream(Response_Interface|iterable $responses, ?float $timeout = null): Response_Stream_Interface
    {
        if ($responses instanceof Native_Response) {
            $responses = [$responses];
        }
        return new Response_Stream(Native_Response::stream($responses, $timeout));
    }
    public function reset(): void
    {
        $this->multi->reset();
    }
    private static function get_body_as_string($body): string
    {
        if (\is_resource($body)) {
            return stream_get_contents($body);
        }
        if (!$body instanceof \Closure) {
            return $body;
        }
        $result = '';
        while ('' !== $data = $body(self::$CHUNK_SIZE)) {
            if (!\is_string($data)) {
                throw new Transport_Exception(\sprintf('Return value of the "body" option callback must be string, "%s" returned.', get_debug_type($data)));
            }
            $result .= $data;
        }
        return $result;
    }
    /**
     * Extracts the host and the port from the URL.
     */
    private static function parse_host_port(array $url, array &$info): array
    {
        if ($port = parse_url((string) $url['authority'], \PHP_URL_PORT) ?: '') {
            $info['primary_port'] = $port;
            $port = ':' . $port;
        } else {
            $info['primary_port'] = 'http:' === $url['scheme'] ? 80 : 443;
        }
        return [parse_url((string) $url['authority'], \PHP_URL_HOST), $port];
    }
    /**
     * Resolves the IP of the host using the local DNS cache if possible.
     */
    private static function dns_resolve(string $host, Native_Client_State $multi, array &$info, ?\Closure $on_progress): string
    {
        $flag = '' !== $host && '[' === $host[0] && ']' === $host[-1] && str_contains($host, ':') ? \FILTER_FLAG_IPV6 : \FILTER_FLAG_IPV4;
        $ip = \FILTER_FLAG_IPV6 === $flag ? substr($host, 1, -1) : $host;
        $now = microtime(true);
        if (filter_var($ip, \FILTER_VALIDATE_IP, $flag)) {
            // The host is already an IP address
        } elseif (null === $ip = $multi->dns_cache[$host] ?? null) {
            $info['debug'] .= "* Hostname was NOT found in DNS cache\n";
            if ($ip = gethostbynamel($host)) {
                $ip = $ip[0];
            } elseif (!\defined('STREAM_PF_INET6')) {
                throw new Transport_Exception(\sprintf('Could not resolve host "%s".', $host));
            } elseif ($ip = dns_get_record($host, \DNS_AAAA)) {
                $ip = $ip[0]['ipv6'];
            } elseif (\extension_loaded('sockets')) {
                if (!$addr_info = socket_addrinfo_lookup($host, 0, ['ai_socktype' => \SOCK_STREAM, 'ai_family' => \AF_INET6])) {
                    throw new Transport_Exception(\sprintf('Could not resolve host "%s".', $host));
                }
                $ip = socket_addrinfo_explain($addr_info[0])['ai_addr']['sin6_addr'];
            } elseif ('localhost' === $host || 'localhost.' === $host) {
                $ip = '::1';
            } else {
                throw new Transport_Exception(\sprintf('Could not resolve host "%s".', $host));
            }
            $multi->dns_cache[$host] = $ip;
            $info['debug'] .= "* Added {$host}:0:{$ip} to DNS cache\n";
        } else {
            $info['debug'] .= "* Hostname was found in DNS cache\n";
        }
        $host = str_contains((string) $ip, ':') ? "[{$ip}]" : $ip;
        $info['namelookup_time'] = microtime(true) - ($info['start_time'] ?: $now);
        $info['primary_ip'] = $ip;
        if ($on_progress) {
            // Notify DNS resolution
            $on_progress();
        }
        return $host;
    }
    /**
     * Handles redirects - the native logic is too buggy to be used.
     */
    private static function create_redirect_resolver(array $options, string $authority, ?array $proxy, array &$info, ?\Closure $on_progress): \Closure
    {
        $redirect_headers = [];
        if (0 < $max_redirects = $options['max_redirects']) {
            $redirect_headers = ['authority' => $authority];
            $redirect_headers['with_auth'] = $redirect_headers['no_auth'] = array_filter($options['headers'], static fn($h): bool => 0 !== stripos((string) $h, 'Host:'));
            if (isset($options['normalized_headers']['authorization']) || isset($options['normalized_headers']['cookie'])) {
                $redirect_headers['no_auth'] = array_filter($redirect_headers['no_auth'], static fn($h): bool => 0 !== stripos((string) $h, 'Authorization:') && 0 !== stripos((string) $h, 'Cookie:'));
            }
        }
        return static function (Native_Client_State $multi, ?string $location, $context) use (&$redirect_headers, $proxy, &$info, $max_redirects, $on_progress): ?string {
            if (null === $location || $info['http_code'] < 300 || 400 <= $info['http_code']) {
                $info['redirect_url'] = null;
                return null;
            }
            try {
                $url = self::parse_url($location);
                $location_has_host = isset($url['authority']);
                $url = self::resolve_url($url, $info['url']);
            } catch (InvalidArgumentException) {
                $info['redirect_url'] = null;
                return null;
            }
            $info['redirect_url'] = implode('', $url);
            if ($info['redirect_count'] >= $max_redirects) {
                return null;
            }
            $info['url'] = $url;
            ++$info['redirect_count'];
            $info['redirect_time'] = microtime(true) - $info['start_time'];
            // Do like curl and browsers: turn POST to GET on 301, 302 and 303
            if (\in_array($info['http_code'], [301, 302, 303], true)) {
                $options = stream_context_get_options($context)['http'];
                if ('POST' === $options['method'] || 303 === $info['http_code']) {
                    $info['http_method'] = $options['method'] = 'HEAD' === $options['method'] ? 'HEAD' : 'GET';
                    $options['content'] = '';
                    $filter_content_headers = static fn($h): bool => 0 !== stripos($h, 'Content-Length:') && 0 !== stripos($h, 'Content-Type:') && 0 !== stripos($h, 'Transfer-Encoding:');
                    $options['header'] = array_filter($options['header'], $filter_content_headers);
                    $redirect_headers['no_auth'] = array_filter($redirect_headers['no_auth'], $filter_content_headers);
                    $redirect_headers['with_auth'] = array_filter($redirect_headers['with_auth'], $filter_content_headers);
                    stream_context_set_options($context, ['http' => $options]);
                }
            }
            [$host, $port] = self::parse_host_port($url, $info);
            if ($location_has_host) {
                // Authorization and Cookie headers MUST NOT follow except for the initial authority name
                $request_headers = $redirect_headers['authority'] === $url['authority'] ? $redirect_headers['with_auth'] : $redirect_headers['no_auth'];
                $request_headers[] = 'Host: ' . $host . $port;
                $dns_resolve = !self::configure_headers_and_proxy($context, $host, $request_headers, $proxy, 'https:' === $url['scheme']);
            } else {
                $dns_resolve = isset(stream_context_get_options($context)['ssl']['peer_name']);
            }
            if ($dns_resolve) {
                $ip = self::dns_resolve($host, $multi, $info, $on_progress);
                $url['authority'] = substr_replace($url['authority'], $ip, -\strlen($host) - \strlen($port), \strlen($host));
            }
            return implode('', $url);
        };
    }
    private static function configure_headers_and_proxy($context, string $host, array $request_headers, ?array $proxy, bool $is_ssl): bool
    {
        if (null === $proxy) {
            stream_context_set_option($context, 'http', 'header', $request_headers);
            stream_context_set_option($context, 'ssl', 'peer_name', $host);
            return false;
        }
        // Matching "no_proxy" should follow the behavior of curl
        foreach ($proxy['no_proxy'] as $rule) {
            $dot_rule = '.' . ltrim((string) $rule, '.');
            if ('*' === $rule || $host === $rule || str_ends_with($host, $dot_rule)) {
                stream_context_set_option($context, 'http', 'proxy', null);
                stream_context_set_option($context, 'http', 'request_fulluri', false);
                stream_context_set_option($context, 'http', 'header', $request_headers);
                stream_context_set_option($context, 'ssl', 'peer_name', $host);
                return false;
            }
        }
        if (null !== $proxy['auth']) {
            $request_headers[] = 'Proxy-Authorization: ' . $proxy['auth'];
        }
        stream_context_set_option($context, 'http', 'proxy', $proxy['url']);
        stream_context_set_option($context, 'http', 'request_fulluri', !$is_ssl);
        stream_context_set_option($context, 'http', 'header', $request_headers);
        stream_context_set_option($context, 'ssl', 'peer_name', null);
        return true;
    }
}