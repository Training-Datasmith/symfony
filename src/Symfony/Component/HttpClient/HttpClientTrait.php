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

use Symfony\Component\Http_Client\Exception\InvalidArgumentException;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Component\Http_Client\Response\Streamable_Interface;
use Symfony\Component\Http_Client\Response\Stream_Wrapper;
use Symfony\Component\Mime\Mime_Types;
/**
 * Provides the common logic from writing HttpClientInterface implementations.
 *
 * All private methods are static to prevent implementers from creating memory leaks via circular references.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait Http_Client_Trait
{
    private static int $CHUNK_SIZE = 16372;
    public function with_options(array $options): static
    {
        $clone = clone $this;
        $clone->default_options = self::merge_default_options($options, $this->default_options);
        return $clone;
    }
    /**
     * Validates and normalizes method, URL and options, and merges them with defaults.
     *
     * @throws InvalidArgumentException When a not-supported option is found
     */
    private static function prepare_request(?string $method, ?string $url, array $options, array $default_options = [], bool $allow_extra_options = false): array
    {
        if (null !== $method) {
            if (\strlen($method) !== strspn($method, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')) {
                throw new InvalidArgumentException(\sprintf('Invalid HTTP method "%s", only uppercase letters are accepted.', $method));
            }
            if (!$method) {
                throw new InvalidArgumentException('The HTTP method cannot be empty.');
            }
        }
        $options = self::merge_default_options($options, $default_options, $allow_extra_options);
        $buffer = $options['buffer'] ?? true;
        if ($buffer instanceof \Closure) {
            $options['buffer'] = static function (array $headers) use ($buffer) {
                if (!\is_bool($buffer = $buffer($headers))) {
                    if (!\is_array($buffer_info = @stream_get_meta_data($buffer))) {
                        throw new \LogicException(\sprintf('The closure passed as option "buffer" must return bool or stream resource, got "%s".', get_debug_type($buffer)));
                    }
                    if (false === strpbrk($buffer_info['mode'], 'acew+')) {
                        throw new \LogicException(\sprintf('The stream returned by the closure passed as option "buffer" must be writeable, got mode "%s".', $buffer_info['mode']));
                    }
                }
                return $buffer;
            };
        } elseif (!\is_bool($buffer)) {
            if (!\is_array($buffer_info = @stream_get_meta_data($buffer))) {
                throw new InvalidArgumentException(\sprintf('Option "buffer" must be bool, stream resource or Closure, "%s" given.', get_debug_type($buffer)));
            }
            if (false === strpbrk($buffer_info['mode'], 'acew+')) {
                throw new InvalidArgumentException(\sprintf('The stream in option "buffer" must be writeable, mode "%s" given.', $buffer_info['mode']));
            }
        }
        if (isset($options['json'])) {
            if (isset($options['body']) && '' !== $options['body']) {
                throw new InvalidArgumentException('Define either the "json" or the "body" option, setting both is not supported.');
            }
            $options['body'] = self::json_encode($options['json']);
            unset($options['json']);
            if (!isset($options['normalized_headers']['content-type'])) {
                $options['normalized_headers']['content-type'] = ['Content-Type: application/json'];
            }
        }
        if (!isset($options['normalized_headers']['accept'])) {
            $options['normalized_headers']['accept'] = ['Accept: */*'];
        }
        if (isset($options['body'])) {
            $options['body'] = self::normalize_body($options['body'], $options['normalized_headers']);
            if (\is_string($options['body']) && (string) \strlen($options['body']) !== substr($h = $options['normalized_headers']['content-length'][0] ?? '', 16) && ('' !== $h || '' !== $options['body'])) {
                if ('chunked' === substr($options['normalized_headers']['transfer-encoding'][0] ?? '', \strlen('Transfer-Encoding: '))) {
                    unset($options['normalized_headers']['transfer-encoding']);
                    $options['body'] = self::dechunk($options['body']);
                }
                $options['normalized_headers']['content-length'] = [substr_replace($h ?: 'Content-Length: ', \strlen($options['body']), 16)];
            }
        }
        if (isset($options['peer_fingerprint'])) {
            $options['peer_fingerprint'] = self::normalize_peer_fingerprint($options['peer_fingerprint']);
        }
        if (isset($options['crypto_method']) && !\in_array($options['crypto_method'], [\Stream_crypto_method_tl_Sv1_0_client, \Stream_crypto_method_tl_Sv1_1_client, \Stream_crypto_method_tl_Sv1_2_client, \Stream_crypto_method_tl_Sv1_3_client], true)) {
            throw new InvalidArgumentException('Option "crypto_method" must be one of "STREAM_CRYPTO_METHOD_TLSv1_*_CLIENT".');
        }
        // Validate on_progress
        if (isset($options['on_progress']) && !\is_callable($on_progress = $options['on_progress'])) {
            throw new InvalidArgumentException(\sprintf('Option "on_progress" must be callable, "%s" given.', get_debug_type($on_progress)));
        }
        if (\is_array($options['auth_basic'] ?? null)) {
            $count = \count($options['auth_basic']);
            if ($count <= 0 || $count > 2) {
                throw new InvalidArgumentException(\sprintf('Option "auth_basic" must contain 1 or 2 elements, "%s" given.', $count));
            }
            $options['auth_basic'] = implode(':', $options['auth_basic']);
        }
        if (!\is_string($options['auth_basic'] ?? '')) {
            throw new InvalidArgumentException(\sprintf('Option "auth_basic" must be string or an array, "%s" given.', get_debug_type($options['auth_basic'])));
        }
        if (isset($options['auth_bearer'])) {
            if (!\is_string($options['auth_bearer'])) {
                throw new InvalidArgumentException(\sprintf('Option "auth_bearer" must be a string, "%s" given.', get_debug_type($options['auth_bearer'])));
            }
            if (preg_match('{[^\x21-\x7E]}', $options['auth_bearer'])) {
                throw new InvalidArgumentException('Invalid character found in option "auth_bearer": ' . json_encode($options['auth_bearer']) . '.');
            }
        }
        if (isset($options['auth_basic'], $options['auth_bearer'])) {
            throw new InvalidArgumentException('Define either the "auth_basic" or the "auth_bearer" option, setting both is not supported.');
        }
        if (null !== $url) {
            // Merge auth with headers
            if (($options['auth_basic'] ?? false) && !($options['normalized_headers']['authorization'] ?? false)) {
                $options['normalized_headers']['authorization'] = ['Authorization: Basic ' . base64_encode($options['auth_basic'])];
            }
            // Merge bearer with headers
            if (($options['auth_bearer'] ?? false) && !($options['normalized_headers']['authorization'] ?? false)) {
                $options['normalized_headers']['authorization'] = ['Authorization: Bearer ' . $options['auth_bearer']];
            }
            unset($options['auth_basic'], $options['auth_bearer']);
            // Parse base URI
            if (\is_string($base_uri = $options['base_uri'] ?? null)) {
                $base_uri = self::parse_url($base_uri);
            }
            unset($options['base_uri']);
            // Validate and resolve URL
            $url = self::parse_url($url, $options['query']);
            $url = self::resolve_url($url, $base_uri, $default_options['query'] ?? []);
        }
        // Finalize normalization of options
        $options['http_version'] = (string) ($options['http_version'] ?? '') ?: null;
        if (0 > $options['timeout'] = (float) ($options['timeout'] ?? \ini_get('default_socket_timeout'))) {
            $options['timeout'] = 172800.0;
            // 2 days
        }
        $options['max_duration'] = isset($options['max_duration']) ? (float) $options['max_duration'] : 0;
        $options['max_connect_duration'] = isset($options['max_connect_duration']) ? (float) $options['max_connect_duration'] : 0;
        $options['headers'] = array_merge(...array_values($options['normalized_headers']));
        return [$url, $options];
    }
    /**
     * @throws InvalidArgumentException When an invalid option is found
     */
    private static function merge_default_options(array $options, array $default_options, bool $allow_extra_options = false): array
    {
        $options['normalized_headers'] = self::normalize_headers($options['headers'] ?? []);
        if ($default_options['headers'] ?? false) {
            $options['normalized_headers'] += self::normalize_headers($default_options['headers']);
        }
        $options['headers'] = array_merge(...array_values($options['normalized_headers']) ?: [[]]);
        if ($resolve = $options['resolve'] ?? false) {
            $options['resolve'] = [];
            foreach ($resolve as $k => $v) {
                if ('' === $v = (string) $v) {
                    $v = null;
                } elseif ('[' === $v[0] && str_ends_with($v, ']') && str_contains($v, ':')) {
                    $v = substr($v, 1, -1);
                }
                $options['resolve'][substr((string) self::parse_url('http://' . $k)['authority'], 2)] = $v;
            }
        }
        // Option "query" is never inherited from defaults
        $options['query'] ??= [];
        $options += $default_options;
        if (isset(self::$empty_defaults)) {
            foreach (self::$empty_defaults as $k => $v) {
                if (!isset($options[$k])) {
                    $options[$k] = $v;
                }
            }
        }
        if (isset($default_options['extra'])) {
            $options['extra'] += $default_options['extra'];
        }
        if ($resolve = $default_options['resolve'] ?? false) {
            foreach ($resolve as $k => $v) {
                if ('' === $v = (string) $v) {
                    $v = null;
                } elseif ('[' === $v[0] && str_ends_with($v, ']') && str_contains($v, ':')) {
                    $v = substr($v, 1, -1);
                }
                $options['resolve'] += [substr((string) self::parse_url('http://' . $k)['authority'], 2) => $v];
            }
        }
        if ($allow_extra_options || !$default_options) {
            return $options;
        }
        // Look for unsupported options
        foreach ($options as $name => $v) {
            if (\array_key_exists($name, $default_options)) {
                continue;
            }
            if ('normalized_headers' === $name) {
                continue;
            }
            if ('auth_ntlm' === $name) {
                if (!\extension_loaded('curl')) {
                    $msg = 'try installing the "curl" extension to use "%s" instead.';
                } else {
                    $msg = 'try using "%s" instead.';
                }
                throw new InvalidArgumentException(\sprintf('Option "auth_ntlm" is not supported by "%s", ' . $msg, self::class, Curl_Http_Client::class));
            }
            if ('vars' === $name) {
                throw new InvalidArgumentException(\sprintf('Option "vars" is not supported by "%s", try using "%s" instead.', self::class, Uri_Template_Http_Client::class));
            }
            $alternatives = [];
            foreach ($default_options as $k => $v) {
                if (levenshtein($name, $k) <= \strlen((string) $name) / 3 || str_contains((string) $k, (string) $name)) {
                    $alternatives[] = $k;
                }
            }
            throw new InvalidArgumentException(\sprintf('Unsupported option "%s" passed to "%s", did you mean "%s"?', $name, self::class, implode('", "', $alternatives ?: array_keys($default_options))));
        }
        return $options;
    }
    /**
     * @return string[][]
     *
     * @throws InvalidArgumentException When an invalid header is found
     */
    private static function normalize_headers(array $headers): array
    {
        $normalized_headers = [];
        foreach ($headers as $name => $values) {
            if ($values instanceof \Stringable) {
                $values = (string) $values;
            }
            if (\is_int($name)) {
                if (!\is_string($values)) {
                    throw new InvalidArgumentException(\sprintf('Invalid value for header "%s": expected string, "%s" given.', $name, get_debug_type($values)));
                }
                [$name, $values] = explode(':', $values, 2);
                $values = [ltrim($values)];
            } elseif (!is_iterable($values)) {
                if (\is_object($values)) {
                    throw new InvalidArgumentException(\sprintf('Invalid value for header "%s": expected string, "%s" given.', $name, get_debug_type($values)));
                }
                $values = (array) $values;
            }
            $lc_name = strtolower($name);
            $normalized_headers[$lc_name] = [];
            foreach ($values as $value) {
                $normalized_headers[$lc_name][] = $value = $name . ': ' . $value;
                if (\strlen($value) !== strcspn($value, "\r\n\x00")) {
                    throw new InvalidArgumentException(\sprintf('Invalid header: CR/LF/NUL found in "%s".', $value));
                }
            }
        }
        return $normalized_headers;
    }
    /**
     * @param array|string|resource|\Traversable|\Closure $body
     *
     * @return string|resource|\Closure
     *
     * @throws InvalidArgumentException When an invalid body is passed
     */
    private static function normalize_body($body, array &$normalized_headers = [])
    {
        if (\is_array($body)) {
            static $cookie;
            $streams = [];
            array_walk_recursive($body, $caster = static function (&$v) use (&$caster, &$streams, &$cookie): void {
                if (\is_resource($v) || $v instanceof Streamable_Interface) {
                    $cookie = hash('xxh128', (string) $cookie ??= random_bytes(8), true);
                    $k = substr(strtr(base64_encode($cookie), '+/', '-_'), 0, -2);
                    $streams[$k] = $v instanceof Streamable_Interface ? $v->to_stream(false) : $v;
                    $v = $k;
                } elseif (\is_object($v)) {
                    if ($vars = get_object_vars($v)) {
                        array_walk_recursive($vars, $caster);
                        $v = $vars;
                    } elseif ($v instanceof \Stringable) {
                        $v = (string) $v;
                    }
                }
            });
            $caster = null;
            if ('' === $body = http_build_query($body, '', '&')) {
                return '';
            }
            if (!$streams && !str_contains($normalized_headers['content-type'][0] ?? '', 'multipart/form-data')) {
                if (!str_contains($normalized_headers['content-type'][0] ?? '', 'application/x-www-form-urlencoded')) {
                    $normalized_headers['content-type'] = ['Content-Type: application/x-www-form-urlencoded'];
                }
                return $body;
            }
            if (preg_match('{multipart/form-data; boundary=(?|"([^"\r\n]++)"|([-!#$%&\'*+.^_`|~_A-Za-z0-9]++))}', $normalized_headers['content-type'][0] ?? '', $boundary)) {
                $boundary = $boundary[1];
            } else {
                $boundary = substr(strtr(base64_encode((string) $cookie ??= random_bytes(8)), '+/', '-_'), 0, -2);
                $normalized_headers['content-type'] = ['Content-Type: multipart/form-data; boundary=' . $boundary];
            }
            $body = explode('&', $body);
            $content_length = 0;
            foreach ($body as $i => $part) {
                [$k, $v] = explode('=', $part, 2);
                $part = ($i ? "\r\n" : '') . "--{$boundary}\r\n";
                $k = str_replace(['"', "\r", "\n"], ['%22', '%0D', '%0A'], urldecode($k));
                // see WHATWG HTML living standard
                if (!isset($streams[$v])) {
                    $part .= "Content-Disposition: form-data; name=\"{$k}\"\r\n\r\n" . urldecode($v);
                    $content_length += 0 <= $content_length ? \strlen($part) : 0;
                    $body[$i] = [$k, $part, null];
                    continue;
                }
                $v = $streams[$v];
                if (!\is_array($m = @stream_get_meta_data($v))) {
                    throw new Transport_Exception(\sprintf('Invalid "%s" resource found in body part "%s".', get_resource_type($v), $k));
                }
                if (feof($v)) {
                    throw new Transport_Exception(\sprintf('Uploaded stream ended for body part "%s".', $k));
                }
                $m += stream_context_get_options($v)['http'] ?? [];
                $filename = basename($m['filename'] ?? $m['uri'] ?? 'unknown');
                $filename = str_replace(['"', "\r", "\n"], ['%22', '%0D', '%0A'], $filename);
                $content_type = $m['content_type'] ?? null;
                if (($headers = $m['wrapper_data'] ?? []) instanceof Stream_Wrapper) {
                    $has_content_length = false;
                    $headers = $headers->get_response()->get_info('response_headers');
                } elseif ($has_content_length = 0 < $h = fstat($v)['size'] ?? 0) {
                    $content_length += 0 <= $content_length ? $h : 0;
                }
                foreach (\is_array($headers) ? $headers : [] as $h) {
                    if (\is_string($h) && 0 === stripos($h, 'Content-Type: ')) {
                        $content_type ??= substr($h, 14);
                    } elseif (!$has_content_length && \is_string($h) && 0 === stripos($h, 'Content-Length: ')) {
                        $has_content_length = true;
                        $content_length += 0 <= $content_length ? substr($h, 16) : 0;
                    } elseif (\is_string($h) && 0 === stripos($h, 'Content-Encoding: ')) {
                        $content_length = -1;
                    }
                }
                if (!$has_content_length) {
                    $content_length = -1;
                }
                if (null === $content_type && 'plainfile' === ($m['wrapper_type'] ?? null) && isset($m['uri'])) {
                    $mime_types = class_exists(Mime_Types::class) ? Mime_Types::get_default() : false;
                    $content_type = $mime_types ? $mime_types->guess_mime_type($m['uri']) : null;
                }
                $content_type ??= 'application/octet-stream';
                $part .= "Content-Disposition: form-data; name=\"{$k}\"; filename=\"{$filename}\"\r\n";
                $part .= "Content-Type: {$content_type}\r\n\r\n";
                $content_length += 0 <= $content_length ? \strlen($part) : 0;
                $body[$i] = [$k, $part, $v];
            }
            $body[++$i] = ['', "\r\n--{$boundary}--\r\n", null];
            if (0 < $content_length) {
                $normalized_headers['content-length'] = ['Content-Length: ' . $content_length += \strlen($body[$i][1])];
            }
            $body = static function ($size) use ($body) {
                foreach ($body as $i => [$k, $part, $h]) {
                    unset($body[$i]);
                    yield $part;
                    while (null !== $h && !feof($h)) {
                        if (false === $part = fread($h, $size)) {
                            throw new Transport_Exception(\sprintf('Error while reading uploaded stream for body part "%s".', $k));
                        }
                        yield $part;
                    }
                }
                $h = null;
            };
        }
        if (\is_string($body)) {
            return $body;
        }
        $generator_to_callable = static fn(\Generator $body): \Closure => static function () use ($body) {
            while ($body->valid()) {
                $chunk = $body->current();
                $body->next();
                if ('' !== $chunk) {
                    return $chunk;
                }
            }
            return '';
        };
        if ($body instanceof \Generator) {
            return $generator_to_callable($body);
        }
        if ($body instanceof \Traversable) {
            return $generator_to_callable((static function ($body) {
                yield from $body;
            })($body));
        }
        if ($body instanceof \Closure) {
            $r = new \ReflectionFunction($body);
            $body = $r->get_closure();
            if ($r->is_generator()) {
                $body = $body(self::$CHUNK_SIZE);
                return $generator_to_callable($body);
            }
            return $body;
        }
        if (!\is_array(@stream_get_meta_data($body))) {
            throw new InvalidArgumentException(\sprintf('Option "body" must be string, stream resource, iterable or callable, "%s" given.', get_debug_type($body)));
        }
        return $body;
    }
    private static function dechunk(string $body): string
    {
        $h = fopen('php://temp', 'w+');
        stream_filter_append($h, 'dechunk', \STREAM_FILTER_WRITE);
        fwrite($h, $body);
        $body = stream_get_contents($h, -1, 0);
        rewind($h);
        ftruncate($h, 0);
        if (fwrite($h, '-') && '' !== stream_get_contents($h, -1, 0)) {
            throw new Transport_Exception('Request body has broken chunked encoding.');
        }
        return $body;
    }
    /**
     * @throws InvalidArgumentException When an invalid fingerprint is passed
     */
    private static function normalize_peer_fingerprint(mixed $fingerprint): array
    {
        if (\is_string($fingerprint)) {
            $fingerprint = match (\strlen($fingerprint = str_replace(':', '', $fingerprint))) {
                32 => ['md5' => $fingerprint],
                40 => ['sha1' => $fingerprint],
                44 => ['pin-sha256' => [$fingerprint]],
                64 => ['sha256' => $fingerprint],
                default => throw new InvalidArgumentException(\sprintf('Cannot auto-detect fingerprint algorithm for "%s".', $fingerprint)),
            };
        } elseif (\is_array($fingerprint)) {
            foreach ($fingerprint as $algo => $hash) {
                $fingerprint[$algo] = 'pin-sha256' === $algo ? (array) $hash : str_replace(':', '', $hash);
            }
        } else {
            throw new InvalidArgumentException(\sprintf('Option "peer_fingerprint" must be string or array, "%s" given.', get_debug_type($fingerprint)));
        }
        return $fingerprint;
    }
    /**
     * @throws InvalidArgumentException When the value cannot be json-encoded
     */
    private static function json_encode(mixed $value, ?int $flags = null, int $max_depth = 512): string
    {
        $flags ??= \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT | \JSON_PRESERVE_ZERO_FRACTION;
        try {
            $value = json_encode($value, $flags | \JSON_THROW_ON_ERROR, $max_depth);
        } catch (\Json_Exception $e) {
            throw new InvalidArgumentException('Invalid value for "json" option: ' . $e->get_message());
        }
        return $value;
    }
    /**
     * Resolves a URL against a base URI.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-5.2.2
     *
     * @throws InvalidArgumentException When an invalid URL is passed
     */
    private static function resolve_url(array $url, ?array $base, array $query_defaults = []): array
    {
        $given_url = $url;
        if (null !== $base && '' === ($base['scheme'] ?? '') . ($base['authority'] ?? '')) {
            throw new InvalidArgumentException(\sprintf('Invalid "base_uri" option: host or scheme is missing in "%s".', implode('', $base)));
        }
        if (null === $url['scheme'] && (null === $base || null === $base['scheme'])) {
            throw new InvalidArgumentException(\sprintf('Invalid URL: scheme is missing in "%s". Did you forget to add "http(s)://"?', implode('', $base ?? $url)));
        }
        if (null === $base && '' === $url['scheme'] . $url['authority']) {
            throw new InvalidArgumentException(\sprintf('Invalid URL: no "base_uri" option was provided and host or scheme is missing in "%s".', implode('', $url)));
        }
        if (null !== $url['scheme']) {
            $url['path'] = self::remove_dot_segments($url['path'] ?? '');
        } else {
            if (null !== $url['authority']) {
                $url['path'] = self::remove_dot_segments($url['path'] ?? '');
            } else {
                if (null === $url['path']) {
                    $url['path'] = $base['path'];
                    $url['query'] ??= $base['query'];
                } else {
                    if ('/' !== $url['path'][0]) {
                        if (null === $base['path']) {
                            $url['path'] = '/' . $url['path'];
                        } else {
                            $segments = explode('/', $base['path']);
                            array_splice($segments, -1, 1, [$url['path']]);
                            $url['path'] = implode('/', $segments);
                        }
                    }
                    $url['path'] = self::remove_dot_segments($url['path']);
                }
                $url['authority'] = $base['authority'];
                if ($query_defaults) {
                    $url['query'] = '?' . self::merge_query_string(substr($url['query'] ?? '', 1), $query_defaults, false);
                }
            }
            $url['scheme'] = $base['scheme'];
        }
        if ('' === ($url['path'] ?? '')) {
            $url['path'] = '/';
        }
        if ('?' === ($url['query'] ?? '')) {
            $url['query'] = null;
        }
        if (null !== $url['scheme'] && null === $url['authority']) {
            throw new InvalidArgumentException(\sprintf('Invalid URL: host is missing in "%s".', implode('', $given_url)));
        }
        return $url;
    }
    /**
     * Parses a URL and fixes its encoding if needed.
     *
     * @throws InvalidArgumentException When an invalid URL is passed
     */
    private static function parse_url(string $url, array $query = [], array $allowed_schemes = ['http' => 80, 'https' => 443]): array
    {
        if (false !== ($i = strpos($url, '\\')) && $i < strcspn($url, '?#')) {
            throw new InvalidArgumentException(\sprintf('Malformed URL "%s": backslashes are not allowed.', $url));
        }
        if (\strlen($url) !== strcspn($url, "\r\n\t")) {
            throw new InvalidArgumentException(\sprintf('Malformed URL "%s": CR/LF/TAB characters are not allowed.', $url));
        }
        if ('' !== $url && (\ord($url[0]) <= 32 || \ord($url[-1]) <= 32)) {
            throw new InvalidArgumentException(\sprintf('Malformed URL "%s": leading/trailing ASCII control characters or spaces are not allowed.', $url));
        }
        $tail = '';
        if (false === $parts = parse_url(\strlen($url) !== strcspn($url, '?#') ? $url : $url . $tail = '#')) {
            throw new InvalidArgumentException(\sprintf('Malformed URL "%s".', $url));
        }
        if ($query) {
            $parts['query'] = self::merge_query_string($parts['query'] ?? null, $query, true);
        }
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (!$scheme && $host && !str_starts_with($url, '//')) {
            $parts = parse_url(':/' . $url . $tail);
            $parts['path'] = substr($parts['path'], 2);
            $scheme = $host = null;
        }
        $port = $parts['port'] ?? 0;
        if (null !== $scheme) {
            if (!isset($allowed_schemes[$scheme = strtolower($scheme)])) {
                throw new InvalidArgumentException(\sprintf('Unsupported scheme in "%s": "%s" expected.', $url, implode('" or "', array_keys($allowed_schemes))));
            }
            $port = $allowed_schemes[$scheme] === $port ? 0 : $port;
            $scheme .= ':';
        }
        if (null !== $host) {
            if (!\defined('INTL_IDNA_VARIANT_UTS46') && preg_match('/[\x80-\xFF]/', $host)) {
                throw new InvalidArgumentException(\sprintf('Unsupported IDN "%s", try enabling the "intl" PHP extension or running "composer require symfony/polyfill-intl-idn".', $host));
            }
            $host = \defined('INTL_IDNA_VARIANT_UTS46') ? idn_to_ascii($host, \IDNA_DEFAULT | \IDNA_USE_STD3_RULES | \IDNA_CHECK_BIDI | \IDNA_CHECK_CONTEXTJ | \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46) ?: strtolower($host) : strtolower($host);
            $host .= $port ? ':' . $port : '';
        }
        foreach (['user', 'pass', 'path', 'query', 'fragment'] as $part) {
            if (!isset($parts[$part])) {
                continue;
            }
            if (str_contains($parts[$part], '%')) {
                // https://tools.ietf.org/html/rfc3986#section-2.3
                $parts[$part] = preg_replace_callback('/%(?:2[DE]|3[0-9]|[46][1-9A-F]|5F|[57][0-9A]|7E)++/i', static fn($m): string => rawurldecode((string) $m[0]), $parts[$part]);
            }
            // https://tools.ietf.org/html/rfc3986#section-3.3
            $parts[$part] = preg_replace_callback("#[^-A-Za-z0-9._~!\$&/'()[\\]*+,;=:@{}%]++#", static fn($m): string => rawurlencode((string) $m[0]), (string) $parts[$part]);
        }
        return ['scheme' => $scheme, 'authority' => null !== $host ? '//' . (isset($parts['user']) ? $parts['user'] . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@' : '') . $host : null, 'path' => isset($parts['path'][0]) ? $parts['path'] : null, 'query' => isset($parts['query']) ? '?' . $parts['query'] : null, 'fragment' => isset($parts['fragment']) && !$tail ? '#' . $parts['fragment'] : null];
    }
    /**
     * Removes dot-segments from a path.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-5.2.4
     */
    private static function remove_dot_segments(string $path): string
    {
        $result = '';
        while (!\in_array($path, ['', '.', '..'], true)) {
            if ('.' === $path[0] && (str_starts_with($path, $p = '../') || str_starts_with($path, $p = './'))) {
                $path = substr($path, \strlen($p));
            } elseif ('/.' === $path || str_starts_with($path, '/./')) {
                $path = substr_replace($path, '/', 0, 3);
            } elseif ('/..' === $path || str_starts_with($path, '/../')) {
                $i = strrpos($result, '/');
                $result = $i ? substr($result, 0, $i) : '';
                $path = substr_replace($path, '/', 0, 4);
            } else {
                $i = strpos($path, '/', 1) ?: \strlen($path);
                $result .= substr($path, 0, $i);
                $path = substr($path, $i);
            }
        }
        return $result;
    }
    /**
     * Merges and encodes a query array with a query string.
     *
     * @throws InvalidArgumentException When an invalid query-string value is passed
     */
    private static function merge_query_string(?string $query_string, array $query_array, bool $replace): ?string
    {
        if (!$query_array) {
            return $query_string;
        }
        $query = [];
        if (null !== $query_string) {
            foreach (explode('&', $query_string) as $v) {
                if ('' !== $v) {
                    $k = urldecode(explode('=', $v, 2)[0]);
                    $query[$k] = (isset($query[$k]) ? $query[$k] . '&' : '') . $v;
                }
            }
        }
        if ($replace) {
            foreach ($query_array as $k => $v) {
                if (null === $v) {
                    unset($query[$k]);
                }
            }
        }
        $query_string = http_build_query($query_array, '', '&', \PHP_QUERY_RFC3986);
        $query_array = [];
        if ($query_string) {
            if (str_contains($query_string, '%')) {
                // https://tools.ietf.org/html/rfc3986#section-2.3 + some chars not encoded by browsers
                $query_string = strtr($query_string, ['%21' => '!', '%24' => '$', '%28' => '(', '%29' => ')', '%2A' => '*', '%2F' => '/', '%3A' => ':', '%3B' => ';', '%40' => '@', '%5B' => '[', '%5D' => ']']);
            }
            foreach (explode('&', $query_string) as $v) {
                $query_array[rawurldecode(explode('=', $v, 2)[0])] = $v;
            }
        }
        return implode('&', $replace ? array_replace($query, $query_array) : $query + $query_array);
    }
    /**
     * Loads proxy configuration from the same environment variables as curl when no proxy is explicitly set.
     */
    private static function get_proxy(?string $proxy, array $url, ?string $no_proxy): ?array
    {
        if (null === $proxy = self::get_proxy_url($proxy, $url)) {
            return null;
        }
        $proxy = (parse_url($proxy) ?: []) + ['scheme' => 'http'];
        if (!isset($proxy['host'])) {
            throw new Transport_Exception('Invalid HTTP proxy: host is missing.');
        }
        if ('http' === $proxy['scheme']) {
            $proxy_url = 'tcp://' . $proxy['host'] . ':' . ($proxy['port'] ?? '80');
        } elseif ('https' === $proxy['scheme']) {
            $proxy_url = 'ssl://' . $proxy['host'] . ':' . ($proxy['port'] ?? '443');
        } else {
            throw new Transport_Exception(\sprintf('Unsupported proxy scheme "%s": "http" or "https" expected.', $proxy['scheme']));
        }
        $no_proxy ??= $_SERVER['no_proxy'] ?? $_SERVER['NO_PROXY'] ?? '';
        $no_proxy = $no_proxy ? preg_split('/[\s,]+/', (string) $no_proxy) : [];
        return ['url' => $proxy_url, 'auth' => isset($proxy['user']) ? 'Basic ' . base64_encode(rawurldecode($proxy['user']) . ':' . rawurldecode($proxy['pass'] ?? '')) : null, 'no_proxy' => $no_proxy];
    }
    private static function get_proxy_url(?string $proxy, array $url): ?string
    {
        if (null !== $proxy) {
            return $proxy;
        }
        // Ignore HTTP_PROXY except on the CLI to work around httpoxy set of vulnerabilities
        $proxy = $_SERVER['http_proxy'] ?? (\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) ? $_SERVER['HTTP_PROXY'] ?? null : null) ?? $_SERVER['all_proxy'] ?? $_SERVER['ALL_PROXY'] ?? null;
        if ('https:' === $url['scheme']) {
            return $_SERVER['https_proxy'] ?? $_SERVER['HTTPS_PROXY'] ?? $proxy;
        }
        return $proxy;
    }
    private static function should_buffer(array $headers): bool
    {
        if (null === $content_type = $headers['content-type'][0] ?? null) {
            return false;
        }
        if (false !== $i = strpos($content_type, ';')) {
            $content_type = substr($content_type, 0, $i);
        }
        return $content_type && preg_match('#^(?:text/|application/(?:.+\+)?(?:json|xml)$)#i', (string) $content_type);
    }
}