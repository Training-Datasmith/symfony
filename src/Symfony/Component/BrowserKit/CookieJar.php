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
namespace Symfony\Component\Browser_Kit;

use Symfony\Component\Browser_Kit\Exception\InvalidArgumentException;
/**
 * CookieJar.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Cookie_Jar
{
    protected array $cookie_jar = [];
    public function set(Cookie $cookie): void
    {
        $this->cookie_jar[$cookie->get_domain()][$cookie->get_path()][$cookie->get_name()] = $cookie;
    }
    /**
     * Gets a cookie by name.
     *
     * You should never use an empty domain, but if you do so,
     * this method returns the first cookie for the given name/path
     * (this behavior ensures a BC behavior with previous versions of
     * Symfony).
     */
    public function get(string $name, string $path = '/', ?string $domain = null): ?Cookie
    {
        $this->flush_expired_cookies();
        foreach ($this->cookie_jar as $cookie_domain => $path_cookies) {
            if ($cookie_domain && $domain) {
                $cookie_domain = '.' . ltrim((string) $cookie_domain, '.');
                if (!str_ends_with('.' . $domain, $cookie_domain)) {
                    continue;
                }
            }
            foreach ($path_cookies as $cookie_path => $named_cookies) {
                if (!str_starts_with($path, (string) $cookie_path)) {
                    continue;
                }
                if (isset($named_cookies[$name])) {
                    return $named_cookies[$name];
                }
            }
        }
        return null;
    }
    /**
     * Removes a cookie by name.
     *
     * You should never use an empty domain, but if you do so,
     * all cookies for the given name/path expire (this behavior
     * ensures a BC behavior with previous versions of Symfony).
     */
    public function expire(string $name, ?string $path = '/', ?string $domain = null): void
    {
        $path ??= '/';
        if (!$domain) {
            // an empty domain means any domain
            // this should never happen but it allows for a better BC
            $domains = array_keys($this->cookie_jar);
        } else {
            $domains = [$domain];
        }
        foreach ($domains as $domain) {
            unset($this->cookie_jar[$domain][$path][$name]);
            if (empty($this->cookie_jar[$domain][$path])) {
                unset($this->cookie_jar[$domain][$path]);
                if (empty($this->cookie_jar[$domain])) {
                    unset($this->cookie_jar[$domain]);
                }
            }
        }
    }
    /**
     * Removes all the cookies from the jar.
     */
    public function clear(): void
    {
        $this->cookie_jar = [];
    }
    /**
     * Updates the cookie jar from a response Set-Cookie headers.
     *
     * @param string[] $setCookies Set-Cookie headers from an HTTP response
     */
    public function update_from_set_cookie(array $set_cookies, ?string $uri = null): void
    {
        $cookies = [];
        foreach ($set_cookies as $cookie) {
            foreach (explode(',', $cookie) as $i => $part) {
                if (0 === $i || preg_match('/^(?P<token>\s*[0-9A-Za-z!#\$%\&\'\*\+\-\.^_`\|~]+)=/', $part)) {
                    $cookies[] = ltrim($part);
                } else {
                    $cookies[\count($cookies) - 1] .= ',' . $part;
                }
            }
        }
        foreach ($cookies as $cookie) {
            try {
                $this->set(Cookie::from_string($cookie, $uri));
            } catch (InvalidArgumentException) {
                // invalid cookies are just ignored
            }
        }
    }
    /**
     * Updates the cookie jar from a Response object.
     */
    public function update_from_response(Response $response, ?string $uri = null): void
    {
        $this->update_from_set_cookie($response->get_header('Set-Cookie', false), $uri);
    }
    /**
     * Returns not yet expired cookies.
     *
     * @return Cookie[]
     */
    public function all(): array
    {
        $this->flush_expired_cookies();
        $flattened_cookies = [];
        foreach ($this->cookie_jar as $path) {
            foreach ($path as $cookies) {
                foreach ($cookies as $cookie) {
                    $flattened_cookies[] = $cookie;
                }
            }
        }
        return $flattened_cookies;
    }
    /**
     * Returns not yet expired cookie values for the given URI.
     */
    public function all_values(string $uri, bool $returns_raw_value = false): array
    {
        $this->flush_expired_cookies();
        $parts = array_replace(['path' => '/'], parse_url($uri));
        $cookies = [];
        foreach ($this->cookie_jar as $domain => $path_cookies) {
            if ($domain) {
                $domain = '.' . ltrim((string) $domain, '.');
                if (!str_ends_with('.' . $parts['host'], $domain)) {
                    continue;
                }
            }
            foreach ($path_cookies as $path => $named_cookies) {
                if (!str_starts_with((string) $parts['path'], (string) $path)) {
                    continue;
                }
                foreach ($named_cookies as $cookie) {
                    if ($cookie->is_secure() && 'https' !== $parts['scheme']) {
                        continue;
                    }
                    $cookies[$cookie->get_name()] = $returns_raw_value ? $cookie->get_raw_value() : $cookie->get_value();
                }
            }
        }
        return $cookies;
    }
    /**
     * Returns not yet expired raw cookie values for the given URI.
     */
    public function all_raw_values(string $uri): array
    {
        return $this->all_values($uri, true);
    }
    /**
     * Removes all expired cookies.
     */
    public function flush_expired_cookies(): void
    {
        foreach ($this->cookie_jar as $domain => $path_cookies) {
            foreach ($path_cookies as $path => $named_cookies) {
                foreach ($named_cookies as $name => $cookie) {
                    if ($cookie->is_expired()) {
                        unset($this->cookie_jar[$domain][$path][$name]);
                    }
                }
            }
        }
    }
}