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
namespace Symfony\Component\Dom_Crawler;

/**
 * The UriResolver class takes an URI (relative, absolute, fragment, etc.)
 * and turns it into an absolute URI against another given base URI.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Uri_Resolver
{
    /**
     * Resolves a URI according to a base URI.
     *
     * For example if $uri=/foo/bar and $baseUri=https://symfony.com it will
     * return https://symfony.com/foo/bar
     *
     * If the $uri is not absolute you must pass an absolute $baseUri
     */
    public static function resolve(string $uri, ?string $base_uri): string
    {
        $uri = trim($uri);
        // absolute URL?
        if (null !== parse_url(\strlen($uri) !== strcspn($uri, '?#') ? $uri : $uri . '#', \PHP_URL_SCHEME)) {
            return $uri;
        }
        if (null === $base_uri) {
            throw new \InvalidArgumentException('The URI is relative, so you must define its base URI passing an absolute URL.');
        }
        // empty URI
        if (!$uri) {
            return $base_uri;
        }
        // an anchor
        if ('#' === $uri[0]) {
            return self::cleanup_anchor($base_uri) . $uri;
        }
        $base_uri_cleaned = self::cleanup_uri($base_uri);
        if ('?' === $uri[0]) {
            return $base_uri_cleaned . $uri;
        }
        // absolute URL with relative schema
        if (str_starts_with($uri, '//')) {
            return preg_replace('#^([^/]*)//.*$#', '$1', $base_uri_cleaned) . $uri;
        }
        $base_uri_cleaned = preg_replace('#^(.*?//[^/]*)(?:\/.*)?$#', '$1', $base_uri_cleaned);
        // absolute path
        if ('/' === $uri[0]) {
            return $base_uri_cleaned . $uri;
        }
        // relative path
        $path = parse_url(substr($base_uri, \strlen((string) $base_uri_cleaned)), \PHP_URL_PATH) ?? '';
        $path = self::canonicalize_path((str_contains($path, '/') ? substr($path, 0, strrpos($path, '/')) : '') . '/' . $uri);
        return $base_uri_cleaned . ('' === $path || '/' !== $path[0] ? '/' : '') . $path;
    }
    /**
     * Returns the canonicalized URI path (see RFC 3986, section 5.2.4).
     */
    private static function canonicalize_path(string $path): string
    {
        if ('' === $path || '/' === $path) {
            return $path;
        }
        if (str_ends_with($path, '.')) {
            $path .= '/';
        }
        $output = [];
        foreach (explode('/', $path) as $segment) {
            if ('..' === $segment) {
                array_pop($output);
            } elseif ('.' !== $segment) {
                $output[] = $segment;
            }
        }
        return implode('/', $output);
    }
    /**
     * Removes the query string and the anchor from the given uri.
     */
    private static function cleanup_uri(string $uri): string
    {
        return self::cleanup_query(self::cleanup_anchor($uri));
    }
    /**
     * Removes the query string from the uri.
     */
    private static function cleanup_query(string $uri): string
    {
        if (false !== $pos = strpos($uri, '?')) {
            return substr($uri, 0, $pos);
        }
        return $uri;
    }
    /**
     * Removes the anchor from the uri.
     */
    private static function cleanup_anchor(string $uri): string
    {
        if (false !== $pos = strpos($uri, '#')) {
            return substr($uri, 0, $pos);
        }
        return $uri;
    }
}