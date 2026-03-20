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

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\Reflection_Member;
use Symfony\Component\Console\Input\Input_Interface;
/**
 * Yields a variadic argument's values from the input.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class Variadic_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(string $argument_name, Input_Interface $input, Reflection_Member $member): iterable
    {
        if (!$member->is_variadic()) {
            return [];
        }
        if ($argument = Argument::try_from($member->get_member())) {
            $values = $input->get_argument($argument->name);
            if (!\is_array($values)) {
                throw new \InvalidArgumentException(\sprintf('The action argument "...$%1$s" is required to be an array, the input argument "%1$s" contains a type of "%2$s" instead.', $argument->name, get_debug_type($values)));
            }
            return $values;
        }
        if ($option = Option::try_from($member->get_member())) {
            $values = $input->get_option($option->name);
            if (!\is_array($values)) {
                throw new \InvalidArgumentException(\sprintf('The action argument "...$%1$s" is required to be an array, the input option "--%1$s" contains a type of "%2$s" instead.', $option->name, get_debug_type($values)));
            }
            return $values;
        }
        return [];
    }
}