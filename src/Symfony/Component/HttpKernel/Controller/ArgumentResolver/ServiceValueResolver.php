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
namespace Symfony\Component\Http_Kernel\Controller\Argument_Resolver;

use Psr\Container\Container_Interface;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
use Symfony\Component\Http_Kernel\Exception\Near_Miss_Value_Resolver_Exception;
/**
 * Yields a service keyed by _controller and argument name.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final readonly class Service_Value_Resolver implements Value_Resolver_Interface
{
    public function __construct(private Container_Interface $container)
    {
    }
    public function resolve(Request $request, Argument_Metadata $argument): array
    {
        $controller = $request->attributes->get('_controller');
        if (\is_array($controller) && \is_callable($controller, true) && \is_string($controller[0])) {
            $controller = $controller[0] . '::' . $controller[1];
        } elseif (!\is_string($controller) || '' === $controller) {
            return [];
        }
        if ('\\' === $controller[0]) {
            $controller = ltrim($controller, '\\');
        }
        if (!$this->container->has($controller) && false !== $i = strrpos($controller, ':')) {
            $controller = substr($controller, 0, $i) . strtolower(substr($controller, $i));
        }
        if (!$this->container->has($controller) || !$this->container->get($controller)->has($argument->get_name())) {
            return [];
        }
        try {
            return [$this->container->get($controller)->get($argument->get_name())];
        } catch (RuntimeException $e) {
            $what = 'argument $' . $argument->get_name();
            $message = str_replace(\sprintf('service "%s"', $argument->get_name()), $what, $e->get_message());
            $what .= \sprintf(' of "%s()"', $controller);
            $message = preg_replace('/service "\.service_locator\.[^"]++"/', $what, $message);
            if ($e->get_message() === $message) {
                $message = \sprintf('Cannot resolve %s: %s', $what, $message);
            }
            throw new Near_Miss_Value_Resolver_Exception($message, $e->get_code(), $e);
        }
    }
}