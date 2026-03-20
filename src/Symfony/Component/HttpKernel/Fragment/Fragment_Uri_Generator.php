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
namespace Symfony\Component\Http_Kernel\Fragment;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Uri_Signer;
use Symfony\Component\Http_Kernel\Controller\Controller_Reference;
/**
 * Generates a fragment URI.
 *
 * @author Kévin Dunglas <kevin@dunglas.fr>
 * @author Fabien Potencier <fabien@symfony.com>
 */
final readonly class Fragment_Uri_Generator implements Fragment_Uri_Generator_Interface
{
    public function __construct(private string $fragment_path, private ?Uri_Signer $signer = null, private ?Request_Stack $request_stack = null)
    {
    }
    public function generate(Controller_Reference $controller, ?Request $request = null, bool $absolute = false, bool $strict = true, bool $sign = true): string
    {
        if (null === $request && (null === $this->request_stack || null === $request = $this->request_stack->get_current_request())) {
            throw new \LogicException('Generating a fragment URL can only be done when handling a Request.');
        }
        if ($sign && null === $this->signer) {
            throw new \LogicException('You must use a URI when using the ESI rendering strategy or set a URL signer.');
        }
        if ($strict) {
            $this->check_non_scalar($controller->attributes);
        }
        // We need to forward the current _format and _locale values as we don't have
        // a proper routing pattern to do the job for us.
        // This makes things inconsistent if you switch from rendering a controller
        // to rendering a route if the route pattern does not contain the special
        // _format and _locale placeholders.
        if (!isset($controller->attributes['_format'])) {
            $controller->attributes['_format'] = $request->get_request_format();
        }
        if (!isset($controller->attributes['_locale'])) {
            $controller->attributes['_locale'] = $request->get_locale();
        }
        $controller->attributes['_controller'] = $controller->controller;
        $controller->query['_path'] = http_build_query($controller->attributes, '', '&');
        $path = $this->fragment_path . '?' . http_build_query($controller->query, '', '&');
        // we need to sign the absolute URI, but want to return the path only.
        $fragment_uri = $sign || $absolute ? $request->get_uri_for_path($path) : $request->get_base_url() . $path;
        if (!$sign) {
            return $fragment_uri;
        }
        $fragment_uri = $this->signer->sign($fragment_uri);
        return $absolute ? $fragment_uri : substr($fragment_uri, \strlen($request->get_scheme_and_http_host()));
    }
    private function check_non_scalar(array $values): void
    {
        foreach ($values as $key => $value) {
            if (\is_array($value)) {
                $this->check_non_scalar($value);
            } elseif (!\is_scalar($value) && null !== $value) {
                throw new \LogicException(\sprintf('Controller attributes cannot contain non-scalar/non-null values (value for key "%s" is not a scalar or null).', $key));
            }
        }
    }
}