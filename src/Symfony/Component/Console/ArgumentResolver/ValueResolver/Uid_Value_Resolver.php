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
use Symfony\Component\Uid\Abstract_Uid;
/**
 * Resolves an AbstractUid instance from a Command argument or option.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class Uid_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(string $argument_name, Input_Interface $input, Reflection_Member $member): iterable
    {
        if ($argument = Argument::try_from($member->get_member())) {
            if (!is_subclass_of($argument->type_name, Abstract_Uid::class)) {
                return [];
            }
            return [$this->resolve_argument($argument, $input)];
        }
        if ($option = Option::try_from($member->get_member())) {
            if (!is_subclass_of($option->type_name, Abstract_Uid::class)) {
                return [];
            }
            return [$this->resolve_option($option, $input)];
        }
        return [];
    }
    private function resolve_argument(Argument $argument, Input_Interface $input): ?Abstract_Uid
    {
        $value = $input->get_argument($argument->name);
        if (null === $value) {
            return null;
        }
        if ($value instanceof $argument->type_name) {
            return $value;
        }
        if (!\is_string($value) || !$argument->type_name::is_valid($value)) {
            throw new InvalidArgumentException(\sprintf('The uid for the "%s" argument is invalid.', $argument->name));
        }
        return $argument->type_name::from_string($value);
    }
    private function resolve_option(Option $option, Input_Interface $input): ?Abstract_Uid
    {
        $value = $input->get_option($option->name);
        if (null === $value) {
            return null;
        }
        if ($value instanceof $option->type_name) {
            return $value;
        }
        if (!\is_string($value) || !$option->type_name::is_valid($value)) {
            throw new Invalid_Option_Exception(\sprintf('The uid for the "--%s" option is invalid.', $option->name));
        }
        return $option->type_name::from_string($value);
    }
}