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
namespace Symfony\Bundle\Framework_Bundle\Controller;

use Symfony\Component\Http_Foundation\Header_Utils;
use Symfony\Component\Http_Foundation\Redirect_Response;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Exception\Http_Exception;
use Symfony\Component\Routing\Generator\Url_Generator_Interface;
/**
 * Redirects a request to another URL.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Redirect_Controller
{
    public function __construct(private readonly ?Url_Generator_Interface $router = null, private readonly ?int $http_port = null, private readonly ?int $https_port = null)
    {
    }
    /**
     * Redirects to another route with the given name.
     *
     * The response status code is 302 if the permanent parameter is false (default),
     * and 301 if the redirection is permanent.
     *
     * In case the route name is empty, the status code will be 404 when permanent is false
     * and 410 otherwise.
     *
     * @param string     $route             The route name to redirect to
     * @param bool       $permanent         Whether the redirection is permanent
     * @param bool|array $ignoreAttributes  Whether to ignore attributes or an array of attributes to ignore
     * @param bool       $keepRequestMethod Whether redirect action should keep HTTP request method
     *
     * @throws HttpException In case the route name is empty
     */
    public function redirect_action(Request $request, string $route, bool $permanent = false, bool|array $ignore_attributes = false, bool $keep_request_method = false, bool $keep_query_params = false): Response
    {
        if ('' == $route) {
            throw new Http_Exception($permanent ? 410 : 404);
        }
        $attributes = [];
        if (false === $ignore_attributes || \is_array($ignore_attributes)) {
            $attributes = $request->attributes->get('_route_params');
            if ($keep_query_params) {
                if ($query = $request->server->get('QUERY_STRING')) {
                    $query = Header_Utils::parse_query($query);
                } else {
                    $query = $request->query->all();
                }
                $attributes = array_merge($query, $attributes);
            }
            unset($attributes['route'], $attributes['permanent'], $attributes['ignoreAttributes'], $attributes['keepRequestMethod'], $attributes['keepQueryParams']);
            if ($ignore_attributes) {
                $attributes = array_diff_key($attributes, array_flip($ignore_attributes));
            }
        }
        if ($keep_request_method) {
            $status_code = $permanent ? 308 : 307;
        } else {
            $status_code = $permanent ? 301 : 302;
        }
        return new Redirect_Response($this->router->generate($route, $attributes, Url_Generator_Interface::ABSOLUTE_URL), $status_code);
    }
    /**
     * Redirects to a URL.
     *
     * The response status code is 302 if the permanent parameter is false (default),
     * and 301 if the redirection is permanent.
     *
     * In case the path is empty, the status code will be 404 when permanent is false
     * and 410 otherwise.
     *
     * @param string      $path              The absolute path or URL to redirect to
     * @param bool        $permanent         Whether the redirect is permanent or not
     * @param string|null $scheme            The URL scheme (null to keep the current one)
     * @param int|null    $httpPort          The HTTP port (null to keep the current one for the same scheme or the default configured port)
     * @param int|null    $httpsPort         The HTTPS port (null to keep the current one for the same scheme or the default configured port)
     * @param bool        $keepRequestMethod Whether redirect action should keep HTTP request method
     *
     * @throws HttpException In case the path is empty
     */
    public function url_redirect_action(Request $request, string $path, bool $permanent = false, ?string $scheme = null, ?int $http_port = null, ?int $https_port = null, bool $keep_request_method = false): Response
    {
        if ('' === $path) {
            throw new Http_Exception($permanent ? 410 : 404);
        }
        if ($keep_request_method) {
            $status_code = $permanent ? 308 : 307;
        } else {
            $status_code = $permanent ? 301 : 302;
        }
        $scheme ??= $request->get_scheme();
        if (str_starts_with($path, '//')) {
            $path = $scheme . ':' . $path;
        }
        // redirect if the path is a full URL
        if (parse_url($path, \PHP_URL_SCHEME)) {
            return new Redirect_Response($path, $status_code);
        }
        if ($qs = $request->server->get('QUERY_STRING') ?: $request->get_query_string()) {
            if (!str_contains($path, '?')) {
                $qs = '?' . $qs;
            } else {
                $qs = '&' . $qs;
            }
        }
        $port = '';
        if ('http' === $scheme) {
            if (null === $http_port) {
                if ('http' === $request->get_scheme()) {
                    $http_port = $request->get_port();
                } else {
                    $http_port = $this->http_port;
                }
            }
            if (null !== $http_port && 80 != $http_port) {
                $port = ":{$http_port}";
            }
        } elseif ('https' === $scheme) {
            if (null === $https_port) {
                if ('https' === $request->get_scheme()) {
                    $https_port = $request->get_port();
                } else {
                    $https_port = $this->https_port;
                }
            }
            if (null !== $https_port && 443 != $https_port) {
                $port = ":{$https_port}";
            }
        }
        $url = $scheme . '://' . $request->get_host() . $port . $request->get_base_url() . $path . $qs;
        return new Redirect_Response($url, $status_code);
    }
    public function __invoke(Request $request): Response
    {
        $p = $request->attributes->get('_route_params', []);
        if (\array_key_exists('route', $p)) {
            if (\array_key_exists('path', $p)) {
                throw new \RuntimeException(\sprintf('Ambiguous redirection settings, use the "path" or "route" parameter, not both: "%s" and "%s" found respectively in "%s" routing configuration.', $p['path'], $p['route'], $request->attributes->get('_route')));
            }
            return $this->redirect_action($request, $p['route'], $p['permanent'] ?? false, $p['ignoreAttributes'] ?? false, $p['keepRequestMethod'] ?? false, $p['keepQueryParams'] ?? false);
        }
        if (\array_key_exists('path', $p)) {
            return $this->url_redirect_action($request, $p['path'], $p['permanent'] ?? false, $p['scheme'] ?? null, $p['httpPort'] ?? null, $p['httpsPort'] ?? null, $p['keepRequestMethod'] ?? false);
        }
        throw new \RuntimeException(\sprintf('The parameter "path" or "route" is required to configure the redirect action in "%s" routing configuration.', $request->attributes->get('_route')));
    }
}