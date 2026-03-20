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
namespace Symfony\Component\Html_Sanitizer\Text_Sanitizer;

use League\Uri\Exceptions\Syntax_Error;
use League\Uri\Uri_String;
/**
 * @internal
 */
final class Url_Sanitizer
{
    /**
     * Sanitizes a given URL string.
     *
     * In addition to ensuring $input is a valid URL, this sanitizer checks that:
     *   * the URL's host is allowed ;
     *   * the URL's scheme is allowed ;
     *   * the URL is allowed to be relative if it is ;
     *
     * It also transforms the URL to HTTPS if requested.
     */
    public static function sanitize(?string $input, ?array $allowed_schemes = null, bool $force_https = false, ?array $allowed_hosts = null, bool $allow_relative = false): ?string
    {
        if (!$input) {
            return null;
        }
        $url = self::parse($input);
        // Malformed URL
        if (!$url || !\is_array($url)) {
            return null;
        }
        // No scheme and relative not allowed
        if (!$allow_relative && !$url['scheme']) {
            return null;
        }
        // Forbidden scheme
        if ($url['scheme'] && null !== $allowed_schemes && !\in_array($url['scheme'], $allowed_schemes, true)) {
            return null;
        }
        // If the scheme used is not supposed to have a host, do not check the host
        if (!self::is_hostless_scheme($url['scheme'])) {
            // No host and relative not allowed
            if (!$allow_relative && !$url['host']) {
                return null;
            }
            // Forbidden host
            if ($url['host'] && null !== $allowed_hosts && !self::is_allowed_host($url['host'], $allowed_hosts)) {
                return null;
            }
        }
        // Force HTTPS
        if ($force_https && 'http' === $url['scheme']) {
            $url['scheme'] = 'https';
        }
        return Uri_String::build($url);
    }
    /**
     * Parses a given URL and returns an array of its components.
     *
     * @return array{
     *     scheme:?string,
     *     user:?string,
     *     pass:?string,
     *     host:?string,
     *     port:?int,
     *     path:string,
     *     query:?string,
     *     fragment:?string
     * }|null
     */
    public static function parse(string $url): ?array
    {
        if (!$url) {
            return null;
        }
        try {
            $parsed_url = Uri_String::parse($url);
            if (preg_match('/\s/', $url)) {
                return null;
            }
            if (isset($parsed_url['host']) && self::decode_unreserved_characters($parsed_url['host']) !== $parsed_url['host']) {
                return null;
            }
            return $parsed_url;
        } catch (Syntax_Error) {
            return null;
        }
    }
    private static function is_hostless_scheme(?string $scheme): bool
    {
        return \in_array($scheme, ['blob', 'chrome', 'data', 'file', 'geo', 'mailto', 'maps', 'tel', 'view-source'], true);
    }
    private static function is_allowed_host(?string $host, array $allowed_hosts): bool
    {
        if (null === $host) {
            return \in_array(null, $allowed_hosts, true);
        }
        $parts = array_reverse(explode('.', $host));
        foreach ($allowed_hosts as $allowed_host) {
            if (self::match_allowed_host_parts($parts, array_reverse(explode('.', (string) $allowed_host)))) {
                return true;
            }
        }
        return false;
    }
    private static function match_allowed_host_parts(array $uri_parts, array $trusted_parts): bool
    {
        // Check each chunk of the domain is valid
        foreach ($trusted_parts as $key => $trusted_part) {
            if (!\array_key_exists($key, $uri_parts) || $uri_parts[$key] !== $trusted_part) {
                return false;
            }
        }
        return true;
    }
    /**
     * Implementation borrowed from League\Uri\Encoder::decodeUnreservedCharacters().
     */
    private static function decode_unreserved_characters(string $host): string
    {
        return preg_replace_callback(',%(2[1-9A-Fa-f]|[3-7][0-9A-Fa-f]|61|62|64|65|66|7[AB]|5F),', static fn(array $matches): string => rawurldecode($matches[0]), $host);
    }
}