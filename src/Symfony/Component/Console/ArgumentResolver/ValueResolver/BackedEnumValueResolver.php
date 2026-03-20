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
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\Invalid_Option_Exception;
use Symfony\Component\Console\Input\Input_Interface;
/**
 * Resolves a BackedEnum instance from a Command argument or option.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class Backed_Enum_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(string $argument_name, Input_Interface $input, Reflection_Member $member): iterable
    {
        if ($argument = Argument::try_from($member->get_member())) {
            if (!is_subclass_of($argument->type_name, \Backed_Enum::class)) {
                return [];
            }
            return [$this->resolve_argument($argument, $input)];
        }
        if ($option = Option::try_from($member->get_member())) {
            if (!is_subclass_of($option->type_name, \Backed_Enum::class)) {
                return [];
            }
            return [$this->resolve_option($option, $input)];
        }
        return [];
    }
    private function resolve_argument(Argument $argument, Input_Interface $input): ?\Backed_Enum
    {
        $value = $input->get_argument($argument->name);
        if (null === $value) {
            return null;
        }
        if ($value instanceof $argument->type_name) {
            return $value;
        }
        if (!\is_string($value) && !\is_int($value)) {
            throw InvalidArgumentException::from_enum_value($argument->name, get_debug_type($value), $argument->suggested_values);
        }
        return $argument->type_name::try_from($value) ?? throw InvalidArgumentException::from_enum_value($argument->name, $value, $argument->suggested_values);
    }
    private function resolve_option(Option $option, Input_Interface $input): ?\Backed_Enum
    {
        $value = $input->get_option($option->name);
        if (null === $value) {
            return null;
        }
        if ($value instanceof $option->type_name) {
            return $value;
        }
        if (!\is_string($value) && !\is_int($value)) {
            throw Invalid_Option_Exception::from_enum_value($option->name, get_debug_type($value), $option->suggested_values);
        }
        return $option->type_name::try_from($value) ?? throw Invalid_Option_Exception::from_enum_value($option->name, $value, $option->suggested_values);
    }
}