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
 * Yields the default value defined in the action signature when no value has been given.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class Default_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(Request $request, Argument_Metadata $argument): array
    {
        if ($argument->has_default_value()) {
            return [$argument->get_default_value()];
        }
        if (null !== $argument->get_type() && $argument->is_nullable() && !$argument->is_variadic()) {
            return [null];
        }
        return [];
    }
}