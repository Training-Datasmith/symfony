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
/**
 * Yields a variadic argument's values from the request attributes.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class Variadic_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(Request $request, Argument_Metadata $argument): array
    {
        if (!$argument->is_variadic() || !$request->attributes->has($argument->get_name())) {
            return [];
        }
        $values = $request->attributes->get($argument->get_name());
        if (!\is_array($values)) {
            throw new \InvalidArgumentException(\sprintf('The action argument "...$%1$s" is required to be an array, the request attribute "%1$s" contains a type of "%2$s" instead.', $argument->get_name(), get_debug_type($values)));
        }
        return $values;
    }
}