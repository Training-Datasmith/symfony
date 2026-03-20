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
namespace Symfony\Component\Console\Argument_Resolver\Value_Resolver;

use Symfony\Component\Console\Attribute\Reflection\Reflection_Member;
use Symfony\Component\Console\Input\Input_Interface;
/**
 * Yields the default value defined in the command signature when no input value has been explicitly passed.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class Default_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(string $argument_name, Input_Interface $input, Reflection_Member $member): iterable
    {
        if ($member->has_default_value()) {
            return [$member->get_default_value()];
        }
        if ($member->is_nullable() && !$member->is_variadic()) {
            return [null];
        }
        return [];
    }
}