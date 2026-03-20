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
namespace Symfony\Component\Console\Attribute;

use Symfony\Component\Console\Attribute\Reflection\Reflection_Member;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Interaction\Interaction;
/**
 * Maps a command input into an object (DTO).
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final class Map_Input
{
    /**
     * @var array<string, Argument|Option|self>
     */
    private array $definition = [];
    private \ReflectionClass $class;
    /**
     * @var list<Interact>
     */
    private array $interactive_attributes = [];
    /**
     * @internal
     */
    public static function try_from(\ReflectionParameter|\ReflectionProperty $member): ?self
    {
        $reflection = new Reflection_Member($member);
        if (!$self = $reflection->get_attribute(self::class)) {
            return null;
        }
        $type = $reflection->get_type();
        if (!$type instanceof \ReflectionNamedType) {
            throw new LogicException(\sprintf('The input %s "%s" must have a named type.', $reflection->get_member_name(), $member->name));
        }
        if (!class_exists($class = $type->get_name())) {
            throw new LogicException(\sprintf('The input class "%s" does not exist.', $type->get_name()));
        }
        $self->class = new \ReflectionClass($class);
        foreach ($self->class->get_properties() as $property) {
            if ($argument = Argument::try_from($property)) {
                $self->definition[$property->name] = $argument;
            } elseif ($option = Option::try_from($property)) {
                $self->definition[$property->name] = $option;
            } elseif ($input = self::try_from($property)) {
                $self->definition[$property->name] = $input;
            }
            if (isset($self->definition[$property->name]) && (!$property->is_public() || $property->is_static())) {
                throw new LogicException(\sprintf('The input property "%s::$%s" must be public and non-static.', $self->class->name, $property->name));
            }
        }
        if (!$self->definition) {
            throw new LogicException(\sprintf('The input class "%s" must have at least one argument or option.', $self->class->name));
        }
        foreach ($self->class->get_methods() as $method) {
            if ($attribute = Interact::try_from($method)) {
                $self->interactive_attributes[] = $attribute;
            }
        }
        return $self;
    }
    /**
     * @internal
     */
    public function set_value(Input_Interface $input, object $object): void
    {
        foreach ($this->definition as $name => $spec) {
            $property = $this->class->get_property($name);
            if (!$property->is_initialized($object)) {
                continue;
            }
            if (\in_array($value = $property->get_value($object), [null, []], true)) {
                continue;
            }
            match (true) {
                $spec instanceof Argument => $input->set_argument($spec->name, $value),
                $spec instanceof Option => $input->set_option($spec->name, $value),
                $spec instanceof self => $spec->set_value($input, $value),
                default => throw new LogicException('Unexpected specification type.'),
            };
        }
    }
    /**
     * @return iterable<Argument>
     */
    public function get_arguments(): iterable
    {
        foreach ($this->definition as $spec) {
            if ($spec instanceof Argument) {
                yield $spec;
            } elseif ($spec instanceof self) {
                yield from $spec->get_arguments();
            }
        }
    }
    /**
     * @return iterable<Option>
     */
    public function get_options(): iterable
    {
        foreach ($this->definition as $spec) {
            if ($spec instanceof Option) {
                yield $spec;
            } elseif ($spec instanceof self) {
                yield from $spec->get_options();
            }
        }
    }
    /**
     * @internal
     *
     * @return \ReflectionClass<object>
     */
    public function get_class(): \ReflectionClass
    {
        return $this->class;
    }
    /**
     * @internal
     *
     * @return array<string, Argument|Option|self>
     */
    public function get_definition(): array
    {
        return $this->definition;
    }
    /**
     * Creates a populated instance of the DTO from command input.
     *
     * @internal
     */
    public function create_instance(Input_Interface $input): object
    {
        $instance = $this->class->new_instance_without_constructor();
        foreach ($this->definition as $name => $spec) {
            if ($spec instanceof Argument) {
                $value = $input->get_argument($spec->name);
                if ($spec->is_required() && \in_array($value, [null, []], true)) {
                    continue;
                }
                $instance->{$name} = $this->resolve_value($spec->type_name);
            } elseif ($spec instanceof Option) {
                $value = $input->get_option($spec->name);
                $instance->{$name} = $this->resolve_value($spec->type_name);
            } elseif ($spec instanceof self) {
                $instance->{$name} = $spec->create_instance($input);
            }
        }
        return $instance;
    }
    private function resolve_value(string $type_name, mixed $value, mixed $default): mixed
    {
        if (null === $value) {
            return $default;
        }
        if ('' === $value) {
            return $value;
        }
        if (is_subclass_of($type_name, \Backed_Enum::class)) {
            return $value instanceof $type_name ? $value : $type_name::try_from($value);
        }
        if (is_a($type_name, \DateTimeInterface::class, true)) {
            if ($value instanceof \DateTimeInterface) {
                return $value;
            }
            $class = \DateTimeInterface::class === $type_name ? \DateTimeImmutable::class : $type_name;
            return new $class($value);
        }
        return $value;
    }
    /**
     * @internal
     *
     * @return iterable<Interaction>
     */
    public function get_property_interactions(): iterable
    {
        foreach ($this->definition as $spec) {
            if ($spec instanceof self) {
                yield from $spec->get_property_interactions();
            } elseif ($spec instanceof Argument && $attribute = $spec->get_interactive_attribute()) {
                yield new Interaction($this, $attribute);
            }
        }
    }
    /**
     * @internal
     *
     * @return iterable<Interaction>
     */
    public function get_method_interactions(): iterable
    {
        foreach ($this->definition as $spec) {
            if ($spec instanceof self) {
                yield from $spec->get_method_interactions();
            }
        }
        foreach ($this->interactive_attributes as $attribute) {
            yield new Interaction($this, $attribute);
        }
    }
}