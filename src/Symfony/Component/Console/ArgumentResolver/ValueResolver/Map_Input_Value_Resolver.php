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
use Symfony\Component\Console\Attribute\Map_Input;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\Reflection_Member;
use Symfony\Component\Console\Input\Input_Interface;
/**
 * Resolves the value of a input argument/option to an object holding the #[MapInput] attribute.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final readonly class Map_Input_Value_Resolver implements Value_Resolver_Interface
{
    public function __construct(private Value_Resolver_Interface $builtin_type_resolver, private Value_Resolver_Interface $backed_enum_resolver, private Value_Resolver_Interface $date_time_resolver)
    {
    }
    public function resolve(string $argument_name, Input_Interface $input, Reflection_Member $member): iterable
    {
        if (!$attribute = Map_Input::try_from($member->get_member())) {
            return [];
        }
        return [$this->resolve_map_input($attribute, $input)];
    }
    private function resolve_map_input(Map_Input $map_input, Input_Interface $input): object
    {
        $instance = $map_input->get_class()->new_instance_without_constructor();
        foreach ($map_input->get_definition() as $name => $spec) {
            // ignore required arguments that are not set yet (may happen in interactive mode)
            if ($spec instanceof Argument && $spec->is_required() && \in_array($input->get_argument($spec->name), [null, []], true)) {
                continue;
            }
            $instance->{$name} = match (true) {
                $spec instanceof Argument => $this->resolve_argument_spec($spec, $map_input->get_class()->get_property($name), $input),
                $spec instanceof Option => $this->resolve_option_spec($spec, $map_input->get_class()->get_property($name), $input),
                $spec instanceof Map_Input => $this->resolve_map_input($spec, $input),
            };
        }
        return $instance;
    }
    private function resolve_argument_spec(Argument $argument, \ReflectionProperty $property, Input_Interface $input): mixed
    {
        if (is_subclass_of($argument->type_name, \Backed_Enum::class)) {
            return iterator_to_array($this->backed_enum_resolver->resolve($property->name, $input, new Reflection_Member($property)))[0] ?? null;
        }
        if (is_a($argument->type_name, \DateTimeInterface::class, true)) {
            return iterator_to_array($this->date_time_resolver->resolve($property->name, $input, new Reflection_Member($property)))[0] ?? null;
        }
        return iterator_to_array($this->builtin_type_resolver->resolve($property->name, $input, new Reflection_Member($property)))[0] ?? null;
    }
    private function resolve_option_spec(Option $option, \ReflectionProperty $property, Input_Interface $input): mixed
    {
        if (is_subclass_of($option->type_name, \Backed_Enum::class)) {
            return iterator_to_array($this->backed_enum_resolver->resolve($property->name, $input, new Reflection_Member($property)))[0] ?? null;
        }
        if (is_a($option->type_name, \DateTimeInterface::class, true)) {
            return iterator_to_array($this->date_time_resolver->resolve($property->name, $input, new Reflection_Member($property)))[0] ?? null;
        }
        return iterator_to_array($this->builtin_type_resolver->resolve($property->name, $input, new Reflection_Member($property)))[0] ?? null;
    }
}