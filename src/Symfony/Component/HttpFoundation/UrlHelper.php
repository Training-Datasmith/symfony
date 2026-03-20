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

use Symfony\Component\Routing\Request_Context;
use Symfony\Component\Routing\Request_Context_Aware_Interface;
/**
 * A helper service for manipulating URLs within and outside the request scope.
 *
 * @author Valentin Udaltsov <udaltsov.valentin@gmail.com>
 */
final readonly class Url_Helper
{
    public function __construct(private Request_Stack $request_stack, private Request_Context_Aware_Interface|Request_Context|null $request_context = null)
    {
    }
    public function get_absolute_url(string $path): string
    {
        if (str_contains($path, '://') || str_starts_with($path, '//')) {
            return $path;
        }
        if (null === $request = $this->request_stack->get_main_request()) {
            return $this->get_absolute_url_from_context($path);
        }
        if ('#' === $path[0]) {
            $path = $request->get_request_uri() . $path;
        } elseif ('?' === $path[0]) {
            $path = $request->get_path_info() . $path;
        }
        if (!$path || '/' !== $path[0]) {
            $prefix = $request->get_path_info();
            $last = \strlen($prefix) - 1;
            if ($last !== $pos = strrpos($prefix, '/')) {
                $prefix = substr($prefix, 0, $pos) . '/';
            }
            return $request->get_uri_for_path($prefix . $path);
        }
        return $request->get_scheme_and_http_host() . $path;
    }
    public function get_relative_path(string $path): string
    {
        if (str_contains($path, '://') || str_starts_with($path, '//')) {
            return $path;
        }
        if (null === $request = $this->request_stack->get_main_request()) {
            return $path;
        }
        return $request->get_relative_uri_for_path($path);
    }
    private function get_absolute_url_from_context(string $path): string
    {
        if (null === $context = $this->request_context) {
            return $path;
        }
        if ($context instanceof Request_Context_Aware_Interface) {
            $context = $context->get_context();
        }
        if ('' === $host = $context->get_host()) {
            return $path;
        }
        $scheme = $context->get_scheme();
        $port = '';
        if ('http' === $scheme && 80 !== $context->get_http_port()) {
            $port = ':' . $context->get_http_port();
        } elseif ('https' === $scheme && 443 !== $context->get_https_port()) {
            $port = ':' . $context->get_https_port();
        }
        if ('#' === $path[0]) {
            $query_string = $context->get_query_string();
            $path = $context->get_path_info() . ($query_string ? '?' . $query_string : '') . $path;
        } elseif ('?' === $path[0]) {
            $path = $context->get_path_info() . $path;
        }
        if ('/' !== $path[0]) {
            $path = rtrim($context->get_base_url(), '/') . '/' . $path;
        }
        return $scheme . '://' . $host . $port . $path;
    }
}