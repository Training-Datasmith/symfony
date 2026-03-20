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
 * Resolves values from #[Argument] or #[Option] attributes for built-in PHP types.
 *
 * Handles: string, bool, int, float, array
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class Builtin_Type_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(string $argument_name, Input_Interface $input, Reflection_Member $member): iterable
    {
        if ($member->is_variadic()) {
            return [];
        }
        if ($argument = Argument::try_from($member->get_member())) {
            if (is_subclass_of($argument->type_name, \Backed_Enum::class)) {
                return [];
            }
            return [$input->get_argument($argument->name)];
        }
        if ($option = Option::try_from($member->get_member())) {
            if (is_subclass_of($option->type_name, \Backed_Enum::class)) {
                return [];
            }
            return [$this->resolve_option($option, $input)];
        }
        return [];
    }
    private function resolve_option(Option $option, Input_Interface $input): mixed
    {
        $value = $input->get_option($option->name);
        if (null === $value && \in_array($option->type_name, Option::ALLOWED_UNION_TYPES, true)) {
            return true;
        }
        if ('array' === $option->type_name && $option->allow_null && [] === $value) {
            return null;
        }
        if ('bool' === $option->type_name) {
            if ($option->allow_null && null === $value) {
                return null;
            }
            return $value ?? $option->default;
        }
        return $value;
    }
}