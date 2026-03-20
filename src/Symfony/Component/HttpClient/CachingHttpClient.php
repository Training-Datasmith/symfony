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

use Symfony\Component\Http_Client\Caching\Freshness;
use Symfony\Component\Http_Client\Chunk\Error_Chunk;
use Symfony\Component\Http_Client\Exception\Chunk_Cache_Item_Not_Found_Exception;
use Symfony\Component\Http_Client\Response\Async_Context;
use Symfony\Component\Http_Client\Response\Async_Response;
use Symfony\Component\Http_Client\Response\Mock_Response;
use Symfony\Component\Http_Client\Response\Response_Stream;
use Symfony\Contracts\Cache\Item_Interface;
use Symfony\Contracts\Cache\Tag_Aware_Cache_Interface;
use Symfony\Contracts\Http_Client\Chunk_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Http_Client\Response_Stream_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Adds caching on top of an HTTP client (per RFC 9111).
 *
 * Known omissions / partially supported features per RFC 9111:
 *   1. Range requests:
 *     - All range requests ("partial content") are passed through and never cached.
 *   2. stale-while-revalidate:
 *     - There's no actual "background revalidation" for stale responses, they will
 *       always be revalidated.
 *   3. min-fresh, max-stale, only-if-cached:
 *     - Request directives are not parsed; the client ignores them.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9111
 */
class Caching_Http_Client implements Http_Client_Interface, Reset_Interface
{
    use Async_Decorator_Trait {
        stream as asyncStream;
        Async_Decorator_Trait::withOptions insteadof Http_Client_Trait;
    }
    use Http_Client_Trait;
    /**
     * The status codes that are always cacheable.
     */
    private const CACHEABLE_STATUS_CODES = [200, 203, 204, 300, 301, 404, 410];
    /**
     * The status codes that are cacheable if the response carries explicit cache directives.
     */
    private const CONDITIONALLY_CACHEABLE_STATUS_CODES = [302, 303, 307, 308];
    /**
     * The HTTP methods that are always cacheable.
     */
    private const CACHEABLE_METHODS = ['GET', 'HEAD'];
    /**
     * The HTTP methods that will trigger a cache invalidation.
     */
    private const UNSAFE_METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];
    /**
     * Headers that influence the response and may affect caching behavior.
     */
    private const RESPONSE_INFLUENCING_HEADERS = ['accept' => true, 'accept-charset' => true, 'accept-encoding' => true, 'accept-language' => true, 'authorization' => true, 'cookie' => true, 'expect' => true, 'host' => true, 'user-agent' => true];
    /**
     * Headers that MUST NOT be stored as per RFC 9111 Section 3.1.
     */
    private const EXCLUDED_HEADERS = ['connection' => true, 'proxy-authenticate' => true, 'proxy-authentication-info' => true, 'proxy-authorization' => true];
    /**
     * Maximum heuristic freshness lifetime in seconds (24 hours).
     */
    private const MAX_HEURISTIC_FRESHNESS_TTL = 86400;
    private array $default_options = self::OPTIONS_DEFAULTS;
    /**
     * @param bool     $sharedCache Indicates whether this cache is shared or private. When true, responses
     *                              may be skipped from caching in presence of certain headers
     *                              (e.g. Authorization) unless explicitly marked as public.
     * @param int|null $maxTtl      The maximum time-to-live (in seconds) for cached responses.
     *                              If a server-provided TTL exceeds this value, it will be capped
     *                              to this maximum.
     */
    public function __construct(private Http_Client_Interface $client, private readonly Tag_Aware_Cache_Interface $cache, array $default_options = [], private readonly bool $shared_cache = true, private readonly ?int $max_ttl = null)
    {
        if ($default_options) {
            [, $this->default_options] = self::prepare_request(null, null, $default_options, $this->default_options);
        }
    }
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        [$full_url, $options] = self::prepare_request($method, $url, $options, $this->default_options);
        $full_url = implode('', $full_url);
        $full_url_tag = self::hash($full_url);
        if ('' !== $options['body'] || ($options['extra']['no_cache'] ?? false) || isset($options['normalized_headers']['range']) || !\in_array($method, self::CACHEABLE_METHODS, true)) {
            return new Async_Response($this->client, $method, $url, $options, function (Chunk_Interface $chunk, Async_Context $context) use ($method, $full_url_tag): \Generator {
                if (null !== $chunk->get_error() || $chunk->is_timeout() || !$chunk->is_first()) {
                    yield $chunk;
                    return;
                }
                $status_code = $context->get_status_code();
                if ($status_code >= 100 && $status_code < 400 && \in_array($method, self::UNSAFE_METHODS, true)) {
                    $this->cache->invalidate_tags([$full_url_tag]);
                }
                $context->passthru();
                yield $chunk;
            });
        }
        $request_hash = self::hash($method . $full_url . serialize(array_intersect_key($options['normalized_headers'], self::RESPONSE_INFLUENCING_HEADERS)));
        $vary_key = "vary_{$request_hash}";
        $vary_fields = $this->cache->get($vary_key, static fn($item, &$save): array => ($save = false) ?: [], 0);
        $metadata_key = self::get_metadata_key($request_hash, $options['normalized_headers'], $vary_fields);
        $cached_data = $this->cache->get($metadata_key, static fn($item, &$save): array => ($save = false) ?: [], 0);
        $freshness = null;
        if ($cached_data) {
            $freshness = $this->evaluate_cache_freshness($cached_data);
            if (Freshness::Fresh === $freshness) {
                return $this->create_response_from_cache($cached_data, $method, $url, $options, $metadata_key);
            }
            if (isset($cached_data['headers']['etag'])) {
                $options['headers']['If-None-Match'] = implode(', ', $cached_data['headers']['etag']);
            }
            if (isset($cached_data['headers']['last-modified'][0])) {
                $options['headers']['If-Modified-Since'] = $cached_data['headers']['last-modified'][0];
            }
        }
        // consistent expiration time for all items
        $expires_at = null === $this->max_ttl ? null : \DateTimeImmutable::create_from_format('U', time() + $this->max_ttl);
        return new Async_Response($this->client, $method, $url, $options, function (Chunk_Interface $chunk, Async_Context $context) use ($expires_at, $full_url_tag, $request_hash, $vary_key, $vary_fields, &$metadata_key, $cached_data, $freshness, $url, $method, $options): \Generator {
            static $attempt_tag = null;
            static $first_chunk_key = null;
            static $chunk_key = null;
            if (null !== $chunk->get_error() || $chunk->is_timeout()) {
                null !== $attempt_tag && $this->cache->invalidate_tags([$attempt_tag]);
                if (Freshness::StaleButUsable === $freshness) {
                    // avoid throwing exception in ErrorChunk#__destruct()
                    $chunk instanceof Error_Chunk && $chunk->did_throw(true);
                    $context->passthru();
                    $context->replace_response($this->create_response_from_cache($cached_data, $method, $url, $options, $metadata_key));
                    return;
                }
                if (Freshness::MustRevalidate === $freshness) {
                    // avoid throwing exception in ErrorChunk#__destruct()
                    $chunk instanceof Error_Chunk && $chunk->did_throw(true);
                    $context->passthru();
                    $context->replace_response(self::create_gateway_timeout_response($method, $url, $options));
                    return;
                }
                yield $chunk;
                return;
            }
            $headers = $context->get_headers();
            if ($chunk->is_first()) {
                $status_code = $context->get_status_code();
                $cache_control = self::parse_cache_control_header($headers['cache-control'] ?? []);
                $attempt_tag = self::generate_chunk_key();
                if (304 === $status_code && null !== $freshness) {
                    $max_age = $this->determine_max_age($headers, $cache_control);
                    $this->cache->get($metadata_key, static function (Item_Interface $item) use ($headers, $max_age, $cached_data, $expires_at, $full_url_tag, $metadata_key): array {
                        $item->expires_at($expires_at)->tag([$full_url_tag, $metadata_key]);
                        $cached_data['expires_at'] = self::calculate_expires_at($max_age);
                        $cached_data['stored_at'] = time();
                        $cached_data['initial_age'] = (int) ($headers['age'][0] ?? 0);
                        $cached_data['headers'] = array_merge($cached_data['headers'], array_diff_key($headers, self::EXCLUDED_HEADERS));
                        return $cached_data;
                    }, \INF);
                    $context->passthru();
                    $context->replace_response($this->create_response_from_cache($cached_data, $method, $url, $options, $metadata_key, $expires_at));
                    return;
                }
                if ($status_code >= 500 && $status_code < 600) {
                    if (Freshness::StaleButUsable === $freshness) {
                        $context->passthru();
                        $context->replace_response($this->create_response_from_cache($cached_data, $method, $url, $options, $metadata_key));
                        return;
                    }
                    if (Freshness::MustRevalidate === $freshness) {
                        $context->passthru();
                        $context->replace_response(self::create_gateway_timeout_response($method, $url, $options));
                        return;
                    }
                }
                if (!$this->is_server_response_cacheable($status_code, $options['normalized_headers'], $headers, $cache_control)) {
                    $context->passthru();
                    yield $chunk;
                    return;
                }
                // recomputing vary fields in case it changed or for first request
                $new_vary_fields = [];
                foreach ($headers['vary'] ?? [] as $vary) {
                    foreach (explode(',', $vary) as $field) {
                        $field = strtolower(trim($field));
                        if ('cookie' === $field ? $this->shared_cache : !preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D', $field)) {
                            $field = '*';
                        }
                        $new_vary_fields[] = $field;
                    }
                }
                if (\in_array('*', $new_vary_fields, true)) {
                    $context->passthru();
                    yield $chunk;
                    return;
                }
                sort($new_vary_fields);
                if ($vary_fields !== $new_vary_fields) {
                    $this->cache->invalidate_tags([$full_url_tag]);
                    $metadata_key = self::get_metadata_key($request_hash, $options['normalized_headers'], $new_vary_fields);
                }
                $this->cache->get($vary_key, static function (Item_Interface $item) use ($new_vary_fields, $expires_at, $full_url_tag): array {
                    $item->tag([$full_url_tag])->expires_at($expires_at);
                    return $new_vary_fields;
                }, \INF);
                $first_chunk_key = $chunk_key = self::generate_chunk_key();
                yield $chunk;
                return;
            }
            if (null === $chunk_key) {
                // informational chunks
                yield $chunk;
                return;
            }
            if ($chunk->is_last()) {
                $this->cache->get($chunk_key, static function (Item_Interface $item) use ($expires_at, $full_url_tag, $metadata_key, $chunk, $attempt_tag): array {
                    $item->tag([$full_url_tag, $metadata_key, $attempt_tag])->expires_at($expires_at);
                    return ['content' => $chunk->get_content(), 'next_chunk' => null];
                }, \INF);
                $max_age = $this->determine_max_age($headers, self::parse_cache_control_header($headers['cache-control'] ?? []));
                $this->cache->get($metadata_key, static function (Item_Interface $item) use ($context, $headers, $max_age, $expires_at, $full_url_tag, $metadata_key, $attempt_tag, $first_chunk_key): array {
                    $item->tag([$full_url_tag, $metadata_key, $attempt_tag])->expires_at($expires_at);
                    return ['status_code' => $context->get_status_code(), 'headers' => array_diff_key($headers, self::EXCLUDED_HEADERS), 'initial_age' => (int) ($headers['age'][0] ?? 0), 'stored_at' => time(), 'expires_at' => self::calculate_expires_at($max_age), 'next_chunk' => $first_chunk_key];
                }, \INF);
                yield $chunk;
                return;
            }
            $next_chunk_key = self::generate_chunk_key();
            $this->cache->get($chunk_key, static function (Item_Interface $item) use ($expires_at, $full_url_tag, $metadata_key, $attempt_tag, $chunk, $next_chunk_key): array {
                $item->tag([$full_url_tag, $metadata_key, $attempt_tag])->expires_at($expires_at);
                return ['content' => $chunk->get_content(), 'next_chunk' => $next_chunk_key];
            }, \INF);
            $chunk_key = $next_chunk_key;
            yield $chunk;
        });
    }
    public function stream(Response_Interface|iterable $responses, ?float $timeout = null): Response_Stream_Interface
    {
        if ($responses instanceof Response_Interface) {
            $responses = [$responses];
        }
        $mock_responses = [];
        $async_responses = [];
        foreach ($responses as $response) {
            if ($response instanceof Mock_Response) {
                $mock_responses[] = $response;
            } else {
                $async_responses[] = $response;
            }
        }
        if (!$mock_responses) {
            return $this->async_stream($async_responses, $timeout);
        }
        if (!$async_responses) {
            return new Response_Stream(Mock_Response::stream($mock_responses, $timeout));
        }
        return new Response_Stream((function () use ($mock_responses, $async_responses, $timeout) {
            yield from Mock_Response::stream($mock_responses, $timeout);
            yield from $this->async_stream($async_responses, $timeout);
        })());
    }
    private static function hash(string $to_hash): string
    {
        return str_replace('/', '_', base64_encode(hash('sha256', $to_hash, true)));
    }
    private static function generate_chunk_key(): string
    {
        return str_replace('/', '_', base64_encode(random_bytes(6)));
    }
    /**
     * Generates a unique metadata key based on the request hash and varying headers.
     *
     * @param string                         $requestHash       A hash representing the request details
     * @param array<string, string|string[]> $normalizedHeaders Normalized headers of the request
     * @param string[]                       $varyFields        Headers to consider for building the variant key
     *
     * @return string The metadata key composed of the request hash and variant key
     */
    private static function get_metadata_key(string $request_hash, array $normalized_headers, array $vary_fields): string
    {
        $variant_key = self::hash(self::build_variant_key($normalized_headers, $vary_fields));
        return "metadata_{$request_hash}_{$variant_key}";
    }
    /**
     * Build a variant key for caching, given an array of normalized headers and the vary fields.
     *
     * The key is an ampersand-separated string of "header=value" pairs, with
     * the special case of "header=" for headers that are not present.
     *
     * @param array<string, string|string[]> $normalizedHeaders
     * @param string[]                       $varyFields
     */
    private static function build_variant_key(array $normalized_headers, array $vary_fields): string
    {
        $parts = [];
        foreach ($vary_fields as $field) {
            $lower = strtolower($field);
            if (!isset($normalized_headers[$lower])) {
                $parts[$lower] = $lower . '=';
            } else {
                $parts[$lower] = $lower . '=' . implode(',', array_map(rawurlencode(...), (array) $normalized_headers[$lower]));
            }
        }
        ksort($parts);
        return implode('&', $parts);
    }
    /**
     * Parse the Cache-Control header and return an array of directive names as keys
     * and their values as values, or true if the directive has no value.
     *
     * @param array<string, string|string[]> $header The Cache-Control header as an array of strings
     *
     * @return array<string, string|true> The parsed Cache-Control directives
     */
    private static function parse_cache_control_header(array $header): array
    {
        $parsed = [];
        foreach ($header as $line) {
            foreach (explode(',', $line) as $directive) {
                if (str_contains($directive, '=')) {
                    [$name, $value] = explode('=', $directive, 2);
                    $parsed[trim($name)] = trim($value);
                } else {
                    $parsed[trim($directive)] = true;
                }
            }
        }
        return $parsed;
    }
    /**
     * Evaluates the freshness of a cached response based on its headers and expiration time.
     *
     * This method determines the state of the cached response by analyzing the Cache-Control
     * directives and the expiration timestamp.
     *
     * @param array{headers: array<string, string[]>, expires_at: int|null} $data The cached response data, including headers and expiration time
     */
    private function evaluate_cache_freshness(array $data): Freshness
    {
        $parse_cache_control_header = self::parse_cache_control_header($data['headers']['cache-control'] ?? []);
        if (isset($parse_cache_control_header['no-cache'])) {
            return Freshness::Stale;
        }
        $now = time();
        $expires = $data['expires_at'];
        if (null !== $expires && $now < $expires) {
            return Freshness::Fresh;
        }
        if (isset($parse_cache_control_header['must-revalidate']) || $this->shared_cache && isset($parse_cache_control_header['proxy-revalidate'])) {
            return Freshness::MustRevalidate;
        }
        if (isset($parse_cache_control_header['stale-if-error']) && $now - $expires <= (int) $parse_cache_control_header['stale-if-error']) {
            return Freshness::StaleButUsable;
        }
        return Freshness::Stale;
    }
    /**
     * Determine the maximum age of the response.
     *
     * This method first checks for the presence of the s-maxage directive, and if
     * present, returns its value minus the current age. If s-maxage is not present,
     * it checks for the presence of the max-age directive, and if present, returns
     * its value minus the current age. If neither directive is present, it checks
     * the Expires header for a valid timestamp, and if present, returns the
     * difference between the timestamp and the current time minus the current age.
     *
     * If none of the above directives or headers are present, the method returns null.
     *
     * @param array<string, string|string[]> $headers      An array of HTTP headers
     * @param array<string, string|true>     $cacheControl An array of parsed Cache-Control directives
     *
     * @return int|null The maximum age of the response, or null if it cannot be determined
     */
    private function determine_max_age(array $headers, array $cache_control): ?int
    {
        $age = self::get_current_age($headers);
        if ($this->shared_cache && isset($cache_control['s-maxage'])) {
            $shared_max_age = (int) $cache_control['s-maxage'];
            return max(0, $shared_max_age - $age);
        }
        if (isset($cache_control['max-age'])) {
            $max_age = (int) $cache_control['max-age'];
            return max(0, $max_age - $age);
        }
        foreach ($headers['expires'] ?? [] as $expire) {
            if (false !== $expiration_timestamp = strtotime($expire)) {
                $time_until_expiration = $expiration_timestamp - time() - $age;
                return max($time_until_expiration, 0);
            }
        }
        // Heuristic freshness fallback when no explicit directives are present
        if (!isset($cache_control['no-cache']) && !isset($cache_control['no-store']) && isset($headers['last-modified'])) {
            foreach ($headers['last-modified'] as $last_modified) {
                if (false === $last_modified_timestamp = strtotime($last_modified)) {
                    continue;
                }
                if (0 < $seconds_since_last_modified = time() - $last_modified_timestamp) {
                    // Heuristic: 10% of time since last modified, capped at max heuristic freshness
                    $heuristic_freshness_seconds = (int) ($seconds_since_last_modified * 0.1);
                    $capped_heuristic_freshness = min($heuristic_freshness_seconds, self::MAX_HEURISTIC_FRESHNESS_TTL);
                    return max(0, $capped_heuristic_freshness - $age);
                }
            }
        }
        return null;
    }
    /**
     * Retrieves the current age of the response from the headers.
     *
     * @param array<string, string|string[]> $headers An array of HTTP headers
     *
     * @return int The age of the response in seconds, defaults to 0 if not present
     */
    private static function get_current_age(array $headers): int
    {
        return (int) ($headers['age'][0] ?? 0);
    }
    /**
     * Calculates the expiration time of the response given the maximum age.
     *
     * @param int|null $maxAge The maximum age of the response in seconds, or null if it cannot be determined
     *
     * @return int|null The expiration time of the response as a Unix timestamp, or null if the maximum age is null
     */
    private static function calculate_expires_at(?int $max_age): ?int
    {
        if (null === $max_age) {
            return null;
        }
        return time() + $max_age;
    }
    /**
     * Checks if the server response is cacheable according to the HTTP 1.1
     * specification (RFC 9111).
     *
     * This function will return true if the server response can be cached,
     * false otherwise.
     *
     * @param array<string, string|string[]> $requestHeaders
     * @param array<string, string|string[]> $responseHeaders
     * @param array<string, string|true>     $cacheControl
     */
    private function is_server_response_cacheable(int $status_code, array $request_headers, array $response_headers, array $cache_control): bool
    {
        // no-store => skip caching
        if (isset($cache_control['no-store'])) {
            return false;
        }
        if ($this->shared_cache) {
            if (!isset($cache_control['public']) && !isset($cache_control['s-maxage']) && !isset($cache_control['must-revalidate']) && isset($request_headers['authorization'])) {
                return false;
            }
            if (isset($cache_control['private'])) {
                return false;
            }
            if (isset($response_headers['authentication-info']) || isset($response_headers['set-cookie']) || isset($response_headers['www-authenticate'])) {
                return false;
            }
        }
        // Conditionals require an explicit expiration
        if (\in_array($status_code, self::CONDITIONALLY_CACHEABLE_STATUS_CODES, true)) {
            return $this->has_explicit_expiration($response_headers, $cache_control);
        }
        return \in_array($status_code, self::CACHEABLE_STATUS_CODES, true);
    }
    /**
     * Checks if the response has an explicit expiration.
     *
     * This function will return true if the response has an explicit expiration
     * time specified in the headers or in the Cache-Control directives,
     * false otherwise.
     *
     * @param array<string, string|string[]> $headers
     * @param array<string, string|true>     $cacheControl
     */
    private function has_explicit_expiration(array $headers, array $cache_control): bool
    {
        return isset($headers['expires']) || $this->shared_cache && isset($cache_control['s-maxage']) || isset($cache_control['max-age']);
    }
    /**
     * Creates a MockResponse object from cached data.
     *
     * This function constructs a MockResponse from the cached data, including
     * the original request method, URL, and options, as well as the cached
     * response headers and content. The constructed MockResponse is then
     * returned.
     *
     * @param array{next_chunk: string, status_code: int, initial_age: int, headers: array<string, string|string[]>, stored_at: int} $cachedData
     */
    private function create_response_from_cache(array $cached_data, string $method, string $url, array $options, string $metadata_key, \DateTimeImmutable|false|null $new_expires_at = false): Mock_Response
    {
        $cache = $this->cache;
        $beta = 0;
        $callback = static function (Item_Interface $item) use ($cache, $metadata_key): never {
            $cache->invalidate_tags([$metadata_key]);
            throw new Chunk_Cache_Item_Not_Found_Exception(\sprintf('Missing cache item for chunk with key "%s". This indicates an internal cache inconsistency.', $item->get_key()));
        };
        if (false !== $new_expires_at) {
            $beta = \INF;
            $callback = static function (Item_Interface $item) use ($callback, $new_expires_at): array {
                if (!$item->is_hit()) {
                    $callback($item);
                }
                $item->expires_at($new_expires_at);
                return $item->get();
            };
        }
        $body = static function () use ($cache, $cached_data, $callback, $beta): \Generator {
            while (null !== $cached_data['next_chunk']) {
                $cached_data = $cache->get($cached_data['next_chunk'], $callback, $beta);
                if ('' !== $cached_data['content']) {
                    yield $cached_data['content'];
                }
            }
        };
        return Mock_Response::from_request($method, $url, $options, new Mock_Response($body(), ['http_code' => $cached_data['status_code'], 'response_headers' => ['age' => $cached_data['initial_age'] + (time() - $cached_data['stored_at'])] + $cached_data['headers']]));
    }
    private static function create_gateway_timeout_response(string $method, string $url, array $options): Mock_Response
    {
        return Mock_Response::from_request($method, $url, $options, new Mock_Response('', ['http_code' => 504]));
    }
}