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
namespace Symfony\Component\Http_Foundation;

/**
 * ResponseHeaderBag is a container for Response HTTP headers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Response_Header_Bag extends Header_Bag
{
    public const COOKIES_FLAT = 'flat';
    public const COOKIES_ARRAY = 'array';
    public const DISPOSITION_ATTACHMENT = 'attachment';
    public const DISPOSITION_INLINE = 'inline';
    protected array $computed_cache_control = [];
    protected array $cookies = [];
    protected array $header_names = [];
    public function __construct(array $headers = [])
    {
        parent::__construct($headers);
        if (!isset($this->headers['cache-control'])) {
            $this->set('Cache-Control', '');
        }
        /* RFC2616 - 14.18 says all Responses need to have a Date */
        if (!isset($this->headers['date'])) {
            $this->init_date();
        }
    }
    /**
     * Returns the headers, with original capitalizations.
     */
    public function all_preserve_case(): array
    {
        $headers = [];
        foreach ($this->all() as $name => $value) {
            $headers[$this->header_names[$name] ?? $name] = $value;
        }
        return $headers;
    }
    public function all_preserve_case_without_cookies(): array
    {
        $headers = $this->all_preserve_case();
        if (isset($this->header_names['set-cookie'])) {
            unset($headers[$this->header_names['set-cookie']]);
        }
        return $headers;
    }
    public function replace(array $headers = []): void
    {
        $this->header_names = [];
        parent::replace($headers);
        if (!isset($this->headers['cache-control'])) {
            $this->set('Cache-Control', '');
        }
        if (!isset($this->headers['date'])) {
            $this->init_date();
        }
    }
    public function all(?string $key = null): array
    {
        $headers = parent::all();
        if (null !== $key) {
            $key = strtr($key, self::UPPER, self::LOWER);
            return 'set-cookie' !== $key ? $headers[$key] ?? [] : array_map(strval(...), $this->get_cookies());
        }
        foreach ($this->get_cookies() as $cookie) {
            $headers['set-cookie'][] = (string) $cookie;
        }
        return $headers;
    }
    public function set(string $key, string|array|null $values, bool $replace = true): void
    {
        $unique_key = strtr($key, self::UPPER, self::LOWER);
        if ('set-cookie' === $unique_key) {
            if ($replace) {
                $this->cookies = [];
            }
            foreach ((array) $values as $cookie) {
                $this->set_cookie(Cookie::from_string($cookie));
            }
            $this->header_names[$unique_key] = $key;
            return;
        }
        $this->header_names[$unique_key] = $key;
        parent::set($key, $values, $replace);
        // ensure the cache-control header has sensible defaults
        if (\in_array($unique_key, ['cache-control', 'etag', 'last-modified', 'expires'], true) && '' !== $computed = $this->compute_cache_control_value()) {
            $this->headers['cache-control'] = [$computed];
            $this->header_names['cache-control'] = 'Cache-Control';
            $this->computed_cache_control = $this->parse_cache_control($computed);
        }
    }
    public function remove(string $key): void
    {
        $unique_key = strtr($key, self::UPPER, self::LOWER);
        unset($this->header_names[$unique_key]);
        if ('set-cookie' === $unique_key) {
            $this->cookies = [];
            return;
        }
        parent::remove($key);
        if ('cache-control' === $unique_key) {
            $this->computed_cache_control = [];
        }
        if ('date' === $unique_key) {
            $this->init_date();
        }
    }
    public function has_cache_control_directive(string $key): bool
    {
        return \array_key_exists($key, $this->computed_cache_control);
    }
    public function get_cache_control_directive(string $key): bool|string|null
    {
        return $this->computed_cache_control[$key] ?? null;
    }
    public function set_cookie(Cookie $cookie): void
    {
        $this->cookies[$cookie->get_domain() ?? ''][$cookie->get_path()][$cookie->get_name()] = $cookie;
        $this->header_names['set-cookie'] = 'Set-Cookie';
    }
    /**
     * Removes a cookie from the array, but does not unset it in the browser.
     */
    public function remove_cookie(string $name, ?string $path = '/', ?string $domain = null): void
    {
        $path ??= '/';
        unset($this->cookies[$domain ?? ''][$path][$name]);
        if (empty($this->cookies[$domain ?? ''][$path])) {
            unset($this->cookies[$domain ?? ''][$path]);
            if (empty($this->cookies[$domain ?? ''])) {
                unset($this->cookies[$domain ?? '']);
            }
        }
        if (!$this->cookies) {
            unset($this->header_names['set-cookie']);
        }
    }
    /**
     * Returns an array with all cookies.
     *
     * @return Cookie[]
     *
     * @throws \InvalidArgumentException When the $format is invalid
     */
    public function get_cookies(string $format = self::COOKIES_FLAT): array
    {
        if (!\in_array($format, [self::COOKIES_FLAT, self::COOKIES_ARRAY], true)) {
            throw new \InvalidArgumentException(\sprintf('Format "%s" invalid (%s).', $format, implode(', ', [self::COOKIES_FLAT, self::COOKIES_ARRAY])));
        }
        if (self::COOKIES_ARRAY === $format) {
            return $this->cookies;
        }
        $flattened_cookies = [];
        foreach ($this->cookies as $path) {
            foreach ($path as $cookies) {
                foreach ($cookies as $cookie) {
                    $flattened_cookies[] = $cookie;
                }
            }
        }
        return $flattened_cookies;
    }
    /**
     * Clears a cookie in the browser.
     */
    public function clear_cookie(string $name, ?string $path = '/', ?string $domain = null, bool $secure = false, bool $http_only = true, ?string $same_site = null, bool $partitioned = false): void
    {
        $this->set_cookie(new Cookie($name, null, 1, $path, $domain, $secure, $http_only, false, $same_site, $partitioned));
    }
    /**
     * @see HeaderUtils::makeDisposition()
     */
    public function make_disposition(string $disposition, string $filename, string $filename_fallback = ''): string
    {
        return Header_Utils::make_disposition($disposition, $filename, $filename_fallback);
    }
    /**
     * Returns the calculated value of the cache-control header.
     *
     * This considers several other headers and calculates or modifies the
     * cache-control header to a sensible, conservative value.
     */
    protected function compute_cache_control_value(): string
    {
        if (!$this->cache_control) {
            if ($this->has('Last-Modified') || $this->has('Expires')) {
                return 'private, must-revalidate';
                // allows for heuristic expiration (RFC 7234 Section 4.2.2) in the case of "Last-Modified"
            }
            // conservative by default
            return 'no-cache, private';
        }
        $header = $this->get_cache_control_header();
        if (isset($this->cache_control['public']) || isset($this->cache_control['private'])) {
            return $header;
        }
        // public if s-maxage is defined, private otherwise
        if (!isset($this->cache_control['s-maxage'])) {
            return $header . ', private';
        }
        return $header;
    }
    private function init_date(): void
    {
        $this->set('Date', gmdate('D, d M Y H:i:s') . ' GMT');
    }
}