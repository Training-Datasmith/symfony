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
use Symfony\Component\Console\Input\File\Input_File;
use Symfony\Component\Console\Input\Input_Interface;
/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class Input_File_Value_Resolver implements Value_Resolver_Interface
{
    public function resolve(string $argument_name, Input_Interface $input, Reflection_Member $member): iterable
    {
        $type = $member->get_type();
        if (!$type instanceof \ReflectionNamedType || Input_File::class !== $type->get_name()) {
            return [];
        }
        if ($argument = Argument::try_from($member->get_member())) {
            return $this->resolve_value($input->get_argument($argument->name), $member);
        }
        if ($option = Option::try_from($member->get_member())) {
            return $this->resolve_value($input->get_option($option->name), $member);
        }
        return [];
    }
    private function resolve_value(mixed $value, Reflection_Member $member): iterable
    {
        if (!$value) {
            if ($member->is_nullable()) {
                return [null];
            }
            return [];
        }
        if ($value instanceof Input_File) {
            return [$value];
        }
        return [Input_File::from_path($value)];
    }
}