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
use Psr\Log\Logger_Interface;
use Symfony\Component\Http_Client\Exception\InvalidArgumentException;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Component\Http_Client\Internal\Curl_Client_State;
use Symfony\Component\Http_Client\Internal\Pushed_Response;
use Symfony\Component\Http_Client\Response\Curl_Response;
use Symfony\Component\Http_Client\Response\Response_Stream;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Http_Client\Response_Stream_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * A performant implementation of the HttpClientInterface contracts based on the curl extension.
 *
 * This provides fully concurrent HTTP requests, with transparent
 * HTTP/2 push when a curl version that supports it is installed.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Curl_Http_Client implements Http_Client_Interface, Logger_Aware_Interface, Reset_Interface
{
    use Http_Client_Trait;
    public const OPTIONS_DEFAULTS = Http_Client_Interface::OPTIONS_DEFAULTS + ['crypto_method' => \Stream_crypto_method_tl_Sv1_2_client];
    private array $default_options = self::OPTIONS_DEFAULTS + [
        // array|string - an array containing the username as first value, and optionally the
        //  password as the second one; or string like username:password - enabling NTLM auth
        'auth_ntlm' => null,
        'extra' => [
            'use_persistent_connections' => false,
            // A list of extra curl options indexed by their corresponding CURLOPT_*
            'curl' => [],
        ],
    ];
    private static array $empty_defaults = self::OPTIONS_DEFAULTS + ['auth_ntlm' => null];
    private ?Logger_Interface $logger = null;
    /**
     * An internal object to share state between the client and its responses.
     */
    private Curl_Client_State $multi;
    /**
     * @param array $defaultOptions     Default request's options
     * @param int   $maxHostConnections The maximum number of connections to a single host
     * @param int   $maxPendingPushes   The maximum number of pushed responses to accept in the queue
     *
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public function __construct(array $default_options = [], int $max_host_connections = 6, int $max_pending_pushes = 0)
    {
        if (!\extension_loaded('curl')) {
            throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\CurlHttpClient" as the "curl" extension is not installed.');
        }
        $this->default_options['buffer'] ??= self::should_buffer(...);
        if ($default_options) {
            [, $this->default_options] = self::prepare_request(null, null, $default_options, $this->default_options);
        }
        $this->multi = new Curl_Client_State($max_host_connections, $max_pending_pushes);
    }
    public function set_logger(Logger_Interface $logger): void
    {
        $this->logger = $this->multi->logger = $logger;
    }
    /**
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        [$url, $options] = self::prepare_request($method, $url, $options, $this->default_options);
        $scheme = $url['scheme'];
        $authority = $url['authority'];
        $host = parse_url((string) $authority, \PHP_URL_HOST);
        $port = parse_url((string) $authority, \PHP_URL_PORT) ?: ('http:' === $scheme ? 80 : 443);
        $proxy = self::get_proxy_url($options['proxy'], $url);
        $url = implode('', $url);
        if (!isset($options['normalized_headers']['user-agent'])) {
            $options['headers'][] = 'User-Agent: Symfony HttpClient (Curl)';
        }
        $curlopts = [
            \CURLOPT_URL => $url,
            \CURLOPT_TCP_NODELAY => true,
            \CURLOPT_PROTOCOLS => \CURLPROTO_HTTP | \CURLPROTO_HTTPS,
            \CURLOPT_REDIR_PROTOCOLS => \CURLPROTO_HTTP | \CURLPROTO_HTTPS,
            \CURLOPT_FOLLOWLOCATION => true,
            \CURLOPT_MAXREDIRS => max(0, $options['max_redirects']),
            \CURLOPT_COOKIEFILE => '',
            // Keep track of cookies during redirects
            \CURLOPT_TIMEOUT => 0,
            \CURLOPT_PROXY => $proxy,
            \CURLOPT_NOPROXY => $options['no_proxy'] ?? $_SERVER['no_proxy'] ?? $_SERVER['NO_PROXY'] ?? '',
            \CURLOPT_SSL_VERIFYPEER => $options['verify_peer'],
            \CURLOPT_SSL_VERIFYHOST => $options['verify_host'] ? 2 : 0,
            \CURLOPT_CAINFO => $options['cafile'],
            \CURLOPT_CAPATH => $options['capath'],
            \CURLOPT_SSL_CIPHER_LIST => $options['ciphers'],
            \CURLOPT_SSLCERT => $options['local_cert'],
            \CURLOPT_SSLKEY => $options['local_pk'],
            \CURLOPT_KEYPASSWD => $options['passphrase'],
            \CURLOPT_CERTINFO => $options['capture_peer_cert_chain'],
            \CURLOPT_SSLVERSION => match ($options['crypto_method']) {
                \Stream_crypto_method_tl_Sv1_3_client => \Curl_sslversion_tl_Sv1_3,
                \Stream_crypto_method_tl_Sv1_2_client => \Curl_sslversion_tl_Sv1_2,
                \Stream_crypto_method_tl_Sv1_1_client => \Curl_sslversion_tl_Sv1_1,
                \Stream_crypto_method_tl_Sv1_0_client => \Curl_sslversion_tl_Sv1_0,
            },
        ];
        if (1.0 === (float) $options['http_version']) {
            $curlopts[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_1_0;
        } elseif (1.1 === (float) $options['http_version']) {
            $curlopts[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_1_1;
        } elseif (\defined('CURL_VERSION_HTTP2') && \CURL_VERSION_HTTP2 & Curl_Client_State::$curl_version['features'] && ('https:' === $scheme || 2.0 === (float) $options['http_version'])) {
            $curlopts[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_2_0;
        } elseif (\defined('CURL_VERSION_HTTP3') && \CURL_VERSION_HTTP3 & Curl_Client_State::$curl_version['features'] && 3.0 === (float) $options['http_version'] && !self::will_use_proxy($proxy, $curlopts[\CURLOPT_NOPROXY], $host)) {
            $curlopts[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_3;
        }
        if (isset($options['auth_ntlm'])) {
            $curlopts[\CURLOPT_HTTPAUTH] = \CURLAUTH_NTLM;
            $curlopts[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_1_1;
            if (\is_array($options['auth_ntlm'])) {
                $count = \count($options['auth_ntlm']);
                if ($count <= 0 || $count > 2) {
                    throw new InvalidArgumentException(\sprintf('Option "auth_ntlm" must contain 1 or 2 elements, %d given.', $count));
                }
                $options['auth_ntlm'] = implode(':', $options['auth_ntlm']);
            }
            if (!\is_string($options['auth_ntlm'])) {
                throw new InvalidArgumentException(\sprintf('Option "auth_ntlm" must be a string or an array, "%s" given.', get_debug_type($options['auth_ntlm'])));
            }
            $curlopts[\CURLOPT_USERPWD] = $options['auth_ntlm'];
        }
        if (!\ZEND_THREAD_SAFE) {
            $curlopts[\CURLOPT_DNS_USE_GLOBAL_CACHE] = false;
        }
        if (\defined('CURLOPT_HEADEROPT') && \defined('CURLHEADER_SEPARATE')) {
            $curlopts[\CURLOPT_HEADEROPT] = \CURLHEADER_SEPARATE;
        }
        // curl's resolve feature varies by host:port but ours varies by host only, let's handle this with our own DNS map
        if (isset($this->multi->dns_cache->hostnames[$host])) {
            $options['resolve'] += [$host => $this->multi->dns_cache->hostnames[$host]];
        }
        if ($options['resolve'] || $this->multi->dns_cache->evictions) {
            // First reset any old DNS cache entries then add the new ones
            $resolve = $this->multi->dns_cache->evictions;
            $this->multi->dns_cache->evictions = [];
            if ($resolve && 0x72a00 > Curl_Client_State::$curl_version['version_number']) {
                // DNS cache removals require curl 7.42 or higher
                $this->multi->reset();
            }
            foreach ($options['resolve'] as $resolve_host => $ip) {
                $resolve[] = null === $ip ? "-{$resolve_host}:{$port}" : "{$resolve_host}:{$port}:{$ip}";
                $this->multi->dns_cache->hostnames[$resolve_host] = $ip;
                $this->multi->dns_cache->removals["-{$resolve_host}:{$port}"] = "-{$resolve_host}:{$port}";
            }
            $curlopts[\CURLOPT_RESOLVE] = $resolve;
        }
        $curlopts[\CURLOPT_CUSTOMREQUEST] = $method;
        if ('POST' === $method) {
            // Use CURLOPT_POST to have browser-like POST-to-GET redirects for 301, 302 and 303
            $curlopts[\CURLOPT_POST] = true;
        } elseif ('HEAD' === $method) {
            $curlopts[\CURLOPT_NOBODY] = true;
        }
        if ('\\' !== \DIRECTORY_SEPARATOR && $options['timeout'] < 1) {
            $curlopts[\CURLOPT_NOSIGNAL] = true;
        }
        if (\extension_loaded('zlib') && !isset($options['normalized_headers']['accept-encoding'])) {
            $options['headers'][] = 'Accept-Encoding: gzip';
            // Expose only one encoding, some servers mess up when more are provided
        }
        $body = $options['body'];
        foreach ($options['headers'] as $i => $header) {
            if (\is_string($body) && '' !== $body && 0 === stripos((string) $header, 'Content-Length: ')) {
                // Let curl handle Content-Length headers
                unset($options['headers'][$i]);
                continue;
            }
            if (':' === $header[-2] && \strlen((string) $header) - 2 === strpos((string) $header, ': ')) {
                // curl requires a special syntax to send empty headers
                $curlopts[\CURLOPT_HTTPHEADER][] = substr_replace($header, ';', -2);
            } else {
                $curlopts[\CURLOPT_HTTPHEADER][] = $header;
            }
        }
        // Prevent curl from sending its default Accept and Expect headers
        foreach (['accept', 'expect'] as $header) {
            if (!isset($options['normalized_headers'][$header][0])) {
                $curlopts[\CURLOPT_HTTPHEADER][] = $header . ':';
            }
        }
        if (!\is_string($body)) {
            if (isset($options['auth_ntlm'])) {
                $curlopts[\CURLOPT_FORBID_REUSE] = true;
                // Reusing NTLM connections requires seeking capability, which only string bodies support
            }
            if (\is_resource($body)) {
                $curlopts[\CURLOPT_READDATA] = $body;
            } else {
                $curlopts[\CURLOPT_READFUNCTION] = static function ($ch, $fd, int $length) use ($body): string {
                    static $eof = false;
                    static $buffer = '';
                    return self::read_request_body($length, $body, $buffer, $eof);
                };
            }
            if (isset($options['normalized_headers']['content-length'][0])) {
                $curlopts[\CURLOPT_INFILESIZE] = (int) substr($options['normalized_headers']['content-length'][0], \strlen('Content-Length: '));
            }
            if (!isset($options['normalized_headers']['transfer-encoding'])) {
                $curlopts[\CURLOPT_HTTPHEADER][] = 'Transfer-Encoding:' . (isset($curlopts[\CURLOPT_INFILESIZE]) ? '' : ' chunked');
            }
            if ('POST' !== $method) {
                $curlopts[\CURLOPT_UPLOAD] = true;
                if (!isset($options['normalized_headers']['content-type']) && 0 !== ($curlopts[\CURLOPT_INFILESIZE] ?? null)) {
                    $curlopts[\CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
                }
            }
        } elseif ('' !== $body || 'POST' === $method) {
            $curlopts[\CURLOPT_POSTFIELDS] = $body;
        }
        if ($options['peer_fingerprint']) {
            if (!isset($options['peer_fingerprint']['pin-sha256'])) {
                throw new Transport_Exception(self::class . ' supports only "pin-sha256" fingerprints.');
            }
            $curlopts[\CURLOPT_PINNEDPUBLICKEY] = 'sha256//' . implode(';sha256//', $options['peer_fingerprint']['pin-sha256']);
        }
        if ($options['bindto']) {
            if (file_exists($options['bindto'])) {
                $curlopts[\CURLOPT_UNIX_SOCKET_PATH] = $options['bindto'];
            } elseif (!str_starts_with((string) $options['bindto'], 'if!') && preg_match('/^(.*):(\d+)$/', (string) $options['bindto'], $matches)) {
                $curlopts[\CURLOPT_INTERFACE] = trim($matches[1], '[]');
                $curlopts[\CURLOPT_LOCALPORT] = $matches[2];
            } else {
                $curlopts[\CURLOPT_INTERFACE] = $options['bindto'];
            }
        }
        if (0 < $options['max_duration']) {
            $curlopts[\CURLOPT_TIMEOUT_MS] = 1000 * $options['max_duration'];
        }
        if (0 < $options['max_connect_duration']) {
            $curlopts[\CURLOPT_CONNECTTIMEOUT_MS] = ceil(1000 * $options['max_connect_duration']);
        }
        if (!empty($options['extra']['curl']) && \is_array($options['extra']['curl'])) {
            $this->validate_extra_curl_options($options['extra']['curl']);
            $curlopts += $options['extra']['curl'];
        }
        if ($pushed_response = $this->multi->pushed_responses[$url] ?? null) {
            unset($this->multi->pushed_responses[$url]);
            if (self::accept_push_for_request($method, $options, $pushed_response)) {
                $this->logger?->debug(\sprintf('Accepting pushed response: "%s %s"', $method, $url));
                // Reinitialize the pushed response with request's options
                $ch = $pushed_response->handle;
                $pushed_response = $pushed_response->response;
                $pushed_response->__construct($this->multi, $url, $options, $this->logger);
            } else {
                $this->logger?->debug(\sprintf('Rejecting pushed response: "%s"', $url));
                $pushed_response = null;
            }
        }
        if (!$pushed_response) {
            $ch = curl_init();
            $this->logger?->info(\sprintf('Request: "%s %s"', $method, $url));
            $curlopts += [\CURLOPT_SHARE => $options['extra']['use_persistent_connections'] ?? false ? $this->multi->share : $this->multi->persistent_share];
        }
        foreach ($curlopts as $opt => $value) {
            if (\PHP_INT_SIZE === 8 && \defined('CURLOPT_INFILESIZE_LARGE') && \CURLOPT_INFILESIZE === $opt && $value >= 1 << 31) {
                $opt = \CURLOPT_INFILESIZE_LARGE;
            }
            if (null !== $value && !curl_setopt($ch, $opt, $value) && \CURLOPT_CERTINFO !== $opt && (!\defined('CURLOPT_HEADEROPT') || \CURLOPT_HEADEROPT !== $opt)) {
                $constant_name = $this->find_constant_name($opt);
                throw new Transport_Exception(\sprintf('Curl option "%s" is not supported.', $constant_name ?? $opt));
            }
        }
        return $pushed_response ?? new Curl_Response($this->multi, $ch, $options, $this->logger, $method, self::create_redirect_resolver($options, $authority), Curl_Client_State::$curl_version['version_number'], $url);
    }
    public function stream(Response_Interface|iterable $responses, ?float $timeout = null): Response_Stream_Interface
    {
        if ($responses instanceof Curl_Response) {
            $responses = [$responses];
        }
        if ($this->multi->handle instanceof \Curl_Multi_Handle) {
            $active = 0;
            while (\CURLM_CALL_MULTI_PERFORM === curl_multi_exec($this->multi->handle, $active)) {
            }
        }
        return new Response_Stream(Curl_Response::stream($responses, $timeout));
    }
    public function reset(): void
    {
        $this->multi->reset();
    }
    /**
     * Accepts pushed responses only if their headers related to authentication match the request.
     */
    private static function accept_push_for_request(string $method, array $options, Pushed_Response $pushed_response): bool
    {
        if ('' !== $options['body'] || $method !== $pushed_response->request_headers[':method'][0]) {
            return false;
        }
        foreach (['proxy', 'no_proxy', 'bindto', 'local_cert', 'local_pk'] as $k) {
            if ($options[$k] !== $pushed_response->parent_options[$k]) {
                return false;
            }
        }
        foreach (['authorization', 'cookie', 'range', 'proxy-authorization'] as $k) {
            $normalized_headers = $options['normalized_headers'][$k] ?? [];
            foreach ($normalized_headers as $i => $v) {
                $normalized_headers[$i] = substr((string) $v, \strlen($k) + 2);
            }
            if (($pushed_response->request_headers[$k] ?? []) !== $normalized_headers) {
                return false;
            }
        }
        $status_code = $pushed_response->response->get_info('http_code') ?: 200;
        return $status_code < 300 || 400 <= $status_code;
    }
    /**
     * Wraps the request's body callback to allow it to return strings longer than curl requested.
     */
    private static function read_request_body(int $length, \Closure $body, string &$buffer, bool &$eof): string
    {
        if (!$eof && \strlen($buffer) < $length) {
            if (!\is_string($data = $body($length))) {
                throw new Transport_Exception(\sprintf('The return value of the "body" option callback must be a string, "%s" returned.', get_debug_type($data)));
            }
            $buffer .= $data;
            $eof = '' === $data;
        }
        $data = substr($buffer, 0, $length);
        $buffer = substr($buffer, $length);
        return $data;
    }
    /**
     * Resolves relative URLs on redirects and deals with authentication headers.
     *
     * Work around CVE-2018-1000007: Authorization and Cookie headers should not follow redirects - fixed in Curl 7.64
     */
    private static function create_redirect_resolver(array $options, string $authority): \Closure
    {
        $redirect_headers = [];
        if (0 < $options['max_redirects']) {
            $redirect_headers['authority'] = $authority;
            $redirect_headers['with_auth'] = $redirect_headers['no_auth'] = array_filter($options['headers'], static fn($h): bool => 0 !== stripos((string) $h, 'Host:'));
            if (isset($options['normalized_headers']['authorization'][0]) || isset($options['normalized_headers']['cookie'][0])) {
                $redirect_headers['no_auth'] = array_filter($options['headers'], static fn($h): bool => 0 !== stripos((string) $h, 'Authorization:') && 0 !== stripos((string) $h, 'Cookie:'));
            }
        }
        return static function ($ch, string $location, bool $no_content) use (&$redirect_headers, $options): ?string {
            try {
                $location = self::parse_url($location);
                $url = self::parse_url(curl_getinfo($ch, \CURLINFO_EFFECTIVE_URL));
                $url = self::resolve_url($location, $url);
            } catch (InvalidArgumentException) {
                return null;
            }
            if ($no_content && $redirect_headers) {
                $filter_content_headers = static fn($h): bool => 0 !== stripos($h, 'Content-Length:') && 0 !== stripos($h, 'Content-Type:') && 0 !== stripos($h, 'Transfer-Encoding:');
                $redirect_headers['no_auth'] = array_filter($redirect_headers['no_auth'], $filter_content_headers);
                $redirect_headers['with_auth'] = array_filter($redirect_headers['with_auth'], $filter_content_headers);
            }
            if ($redirect_headers && isset($location['authority'])) {
                $request_headers = $location['authority'] === $redirect_headers['authority'] ? $redirect_headers['with_auth'] : $redirect_headers['no_auth'];
                curl_setopt($ch, \CURLOPT_HTTPHEADER, $request_headers);
            } elseif ($no_content && $redirect_headers) {
                curl_setopt($ch, \CURLOPT_HTTPHEADER, $redirect_headers['with_auth']);
            }
            $proxy = self::get_proxy_url($options['proxy'], $url);
            curl_setopt($ch, \CURLOPT_PROXY, $proxy);
            if (\defined('CURL_HTTP_VERSION_3') && \CURL_HTTP_VERSION_3 === curl_getinfo($ch, \CURLINFO_HTTP_VERSION) && self::will_use_proxy($proxy, $options['no_proxy'] ?? $_SERVER['no_proxy'] ?? $_SERVER['NO_PROXY'] ?? '', parse_url((string) $url['authority'], \PHP_URL_HOST))) {
                curl_setopt($ch, \CURLOPT_HTTP_VERSION, \defined('CURL_HTTP_VERSION_2_0') ? \CURL_HTTP_VERSION_2_0 : \CURL_HTTP_VERSION_1_1);
            }
            return implode('', $url);
        };
    }
    private function find_constant_name(int $opt): ?string
    {
        $constants = array_filter(get_defined_constants(), static fn($v, $k): bool => $v === $opt && 'C' === $k[0] && (str_starts_with((string) $k, 'CURLOPT_') || str_starts_with((string) $k, 'CURLINFO_')), \ARRAY_FILTER_USE_BOTH);
        return key($constants);
    }
    /**
     * Prevents overriding options that are set internally throughout the request.
     */
    private function validate_extra_curl_options(array $options): void
    {
        $curlopts_to_config = [
            // options used in CurlHttpClient
            \CURLOPT_HTTPAUTH => 'auth_ntlm',
            \CURLOPT_USERPWD => 'auth_ntlm',
            \CURLOPT_RESOLVE => 'resolve',
            \CURLOPT_NOSIGNAL => 'timeout',
            \CURLOPT_HTTPHEADER => 'headers',
            \CURLOPT_READDATA => 'body',
            \CURLOPT_READFUNCTION => 'body',
            \CURLOPT_INFILESIZE => 'body',
            \CURLOPT_POSTFIELDS => 'body',
            \CURLOPT_UPLOAD => 'body',
            \CURLOPT_INTERFACE => 'bindto',
            \CURLOPT_TIMEOUT_MS => 'max_duration',
            \CURLOPT_TIMEOUT => 'max_duration',
            \CURLOPT_CONNECTTIMEOUT_MS => 'max_connect_duration',
            \CURLOPT_CONNECTTIMEOUT => 'max_connect_duration',
            \CURLOPT_MAXREDIRS => 'max_redirects',
            \CURLOPT_POSTREDIR => 'max_redirects',
            \CURLOPT_PROXY => 'proxy',
            \CURLOPT_NOPROXY => 'no_proxy',
            \CURLOPT_SSL_VERIFYPEER => 'verify_peer',
            \CURLOPT_SSL_VERIFYHOST => 'verify_host',
            \CURLOPT_CAINFO => 'cafile',
            \CURLOPT_CAPATH => 'capath',
            \CURLOPT_SSL_CIPHER_LIST => 'ciphers',
            \CURLOPT_SSLCERT => 'local_cert',
            \CURLOPT_SSLKEY => 'local_pk',
            \CURLOPT_KEYPASSWD => 'passphrase',
            \CURLOPT_CERTINFO => 'capture_peer_cert_chain',
            \CURLOPT_USERAGENT => 'normalized_headers',
            \CURLOPT_REFERER => 'headers',
            // options used in CurlResponse
            \CURLOPT_NOPROGRESS => 'on_progress',
            \CURLOPT_PROGRESSFUNCTION => 'on_progress',
        ];
        if (\defined('CURLOPT_UNIX_SOCKET_PATH')) {
            $curlopts_to_config[\CURLOPT_UNIX_SOCKET_PATH] = 'bindto';
        }
        if (\defined('CURLOPT_PINNEDPUBLICKEY')) {
            $curlopts_to_config[\CURLOPT_PINNEDPUBLICKEY] = 'peer_fingerprint';
        }
        $curlopts_to_check = [\CURLOPT_PRIVATE, \CURLOPT_HEADERFUNCTION, \CURLOPT_WRITEFUNCTION, \CURLOPT_VERBOSE, \CURLOPT_STDERR, \CURLOPT_RETURNTRANSFER, \CURLOPT_URL, \CURLOPT_FOLLOWLOCATION, \CURLOPT_HEADER, \CURLOPT_HTTP_VERSION, \CURLOPT_PORT, \CURLOPT_DNS_USE_GLOBAL_CACHE, \CURLOPT_PROTOCOLS, \CURLOPT_REDIR_PROTOCOLS, \CURLOPT_COOKIEFILE, \CURLINFO_REDIRECT_COUNT];
        if (\defined('CURLOPT_HTTP09_ALLOWED')) {
            $curlopts_to_check[] = \CURLOPT_HTTP09_ALLOWED;
        }
        if (\defined('CURLOPT_HEADEROPT')) {
            $curlopts_to_check[] = \CURLOPT_HEADEROPT;
        }
        foreach ($options as $opt => $opt_value) {
            if (isset($curlopts_to_config[$opt])) {
                $const_name = $this->find_constant_name($opt) ?? $opt;
                throw new InvalidArgumentException(\sprintf('Cannot set "%s" with "extra.curl", use option "%s" instead.', $const_name, $curlopts_to_config[$opt]));
            }
            if (\in_array($opt, [\CURLOPT_POST, \CURLOPT_PUT, \CURLOPT_CUSTOMREQUEST, \CURLOPT_HTTPGET, \CURLOPT_NOBODY], true)) {
                throw new InvalidArgumentException('The HTTP method cannot be overridden using "extra.curl".');
            }
            if (\in_array($opt, $curlopts_to_check, true)) {
                $const_name = $this->find_constant_name($opt) ?? $opt;
                throw new InvalidArgumentException(\sprintf('Cannot set "%s" with "extra.curl".', $const_name));
            }
        }
    }
    private static function will_use_proxy(?string $proxy, string $no_proxy, string $host): bool
    {
        if (null === $proxy) {
            return false;
        }
        if ('' === $no_proxy) {
            return true;
        }
        foreach (preg_split('/[\s,]+/', $no_proxy) as $rule) {
            $dot_rule = '.' . ltrim($rule, '.');
            if ('*' === $rule || $host === $rule || str_ends_with($host, $dot_rule)) {
                return false;
            }
        }
        return true;
    }
}