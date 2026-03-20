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
namespace Symfony\Component\Console\Attribute\Reflection;

use Symfony\Component\String\Unicode_String;
/**
 * @internal
 */
class Reflection_Member
{
    public function __construct(private readonly \ReflectionParameter|\ReflectionProperty $member)
    {
    }
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public function get_attribute(string $class): ?object
    {
        return ($this->member->get_attributes($class, \Reflection_Attribute::IS_INSTANCEOF)[0] ?? null)?->new_instance();
    }
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function get_attributes(string $class): array
    {
        return array_map(static fn(\Reflection_Attribute $attribute): object => $attribute->new_instance(), $this->member->get_attributes($class, \Reflection_Attribute::IS_INSTANCEOF));
    }
    public function get_source_name(): string
    {
        if ($this->member instanceof \ReflectionProperty) {
            return $this->member->class;
        }
        $function = $this->member->get_declaring_function();
        if ($function instanceof \ReflectionMethod) {
            return $function->class . '::' . $function->name . '()';
        }
        return $function->name . '()';
    }
    public function get_source_this(): ?object
    {
        if ($this->member instanceof \ReflectionParameter) {
            return $this->member->get_declaring_function()->get_closure_this();
        }
        return null;
    }
    public function get_type(): ?\Reflection_Type
    {
        return $this->member->get_type();
    }
    public function get_name(): string
    {
        return $this->member->get_name();
    }
    public function has_default_value(): bool
    {
        if ($this->member instanceof \ReflectionParameter) {
            return $this->member->is_default_value_available();
        }
        return $this->member->has_default_value();
    }
    public function get_default_value(): mixed
    {
        $default_value = $this->member->get_default_value();
        if ($default_value instanceof \Backed_Enum) {
            return $default_value->value;
        }
        return $default_value;
    }
    public function is_nullable(): bool
    {
        return (bool) $this->member->get_type()?->allows_null();
    }
    public function get_member_name(): string
    {
        return $this->member instanceof \ReflectionParameter ? 'parameter' : 'property';
    }
    public function is_parameter(): bool
    {
        return $this->member instanceof \ReflectionParameter;
    }
    public function is_variadic(): bool
    {
        return $this->member instanceof \ReflectionParameter && $this->member->is_variadic();
    }
    public function is_property(): bool
    {
        return $this->member instanceof \ReflectionProperty;
    }
    public function get_member(): \ReflectionParameter|\ReflectionProperty
    {
        return $this->member;
    }
    public function get_input_name(): string
    {
        return (new Unicode_String($this->member->get_name()))->kebab()->to_string();
    }
}