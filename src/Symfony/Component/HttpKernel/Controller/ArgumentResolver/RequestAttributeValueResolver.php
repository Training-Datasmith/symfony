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
 * Yields a non-variadic argument's value from the request attributes.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class Request_Attribute_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(Request $request, Argument_Metadata $argument): array
    {
        if ($argument->is_variadic()) {
            return [];
        }
        $name = $argument->get_name();
        if (!$request->attributes->has($name)) {
            return [];
        }
        $value = $request->attributes->get($name);
        if (null === $value && $argument->is_nullable()) {
            return [null];
        }
        $type = $argument->get_type();
        // Skip when no type declaration or complex types; fall back to other resolvers/defaults
        if (null === $type || str_contains($type, '|') || str_contains($type, '&')) {
            return [$value];
        }
        if ('string' === $type) {
            if (!\is_scalar($value) && !$value instanceof \Stringable) {
                throw new Not_Found_Http_Exception(\sprintf('The value for the "%s" route parameter is invalid.', $name));
            }
            $value = (string) $value;
        } elseif ($filter = match ($type) {
            'int' => \FILTER_VALIDATE_INT,
            'float' => \FILTER_VALIDATE_FLOAT,
            'bool' => \FILTER_VALIDATE_BOOL,
            default => null,
        }) {
            if (null === $value = $request->attributes->filter($name, null, $filter, ['flags' => \FILTER_NULL_ON_FAILURE | \FILTER_REQUIRE_SCALAR])) {
                throw new Not_Found_Http_Exception(\sprintf('The value for the "%s" route parameter is invalid.', $name));
            }
        }
        return [$value];
    }
}