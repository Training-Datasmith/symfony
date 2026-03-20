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

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
/**
 * Attempt to resolve backed enum cases from request attributes, for a route path parameter,
 * leading to a 404 Not Found if the attribute value isn't a valid backing value for the enum type.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class Backed_Enum_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(Request $request, Argument_Metadata $argument): iterable
    {
        if (!is_subclass_of($argument->get_type(), \Backed_Enum::class)) {
            return [];
        }
        if ($argument->is_variadic()) {
            // only target route path parameters, which cannot be variadic.
            return [];
        }
        $name = $argument->get_name();
        // do not support if no value can be resolved at all
        // letting the \Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver be used
        // or \Symfony\Component\HttpKernel\Controller\ArgumentResolver fail with a meaningful error.
        if (!$request->attributes->has($name)) {
            return [];
        }
        if (null === $value = $request->attributes->get($name)) {
            return [null];
        }
        if ($value instanceof \Backed_Enum) {
            return [$value];
        }
        /** @var class-string<\BackedEnum> $type */
        $type = $argument->get_type();
        if (!\is_int($value) && !\is_string($value)) {
            throw new Not_Found_Http_Exception(\sprintf('Could not resolve the "%s $%s" controller argument: expecting an int or string, got "%s".', $type, $name, get_debug_type($value)));
        }
        try {
            return [$type::from($value)];
        } catch (\Value_Error|\TypeError $e) {
            throw new Not_Found_Http_Exception(\sprintf('Could not resolve the "%s $%s" controller argument: ', $type, $name) . $e->get_message(), $e);
        }
    }
}