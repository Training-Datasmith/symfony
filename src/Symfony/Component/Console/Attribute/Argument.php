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
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\String\Unicode_String;
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
class Argument
{
    public mixed $default = null;
    public array|\Closure $suggested_values;
    /**
     * @internal
     *
     * @var string|class-string<\BackedEnum>
     */
    public string $type_name = '';
    private ?int $mode = null;
    private ?Interactive_Attribute_Interface $interactive_attribute = null;
    /**
     * Represents a console command <argument> definition.
     *
     * If unset, the `name` value will be inferred from the parameter definition.
     *
     * @param array<string|Suggestion>|callable(CompletionInput):list<string|Suggestion> $suggestedValues The values used for input completion
     */
    public function __construct(public string $description = '', public string $name = '', array|callable $suggested_values = [])
    {
        $this->suggested_values = \is_callable($suggested_values) ? $suggested_values(...) : $suggested_values;
    }
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
        $name = $reflection->get_name();
        if (!$type instanceof \ReflectionNamedType) {
            throw new LogicException(\sprintf('The %s "$%s" of "%s" must have a named type. Untyped, Union or Intersection types are not supported for command arguments.', $reflection->get_member_name(), $name, $reflection->get_source_name()));
        }
        $self->type_name = $type->get_name();
        if (!$self->name) {
            $self->name = (new Unicode_String($name))->kebab();
        }
        $self->default = $reflection->has_default_value() ? $reflection->get_default_value() : null;
        $is_optional = $reflection->has_default_value() || $reflection->is_nullable() || $reflection->is_variadic();
        $self->mode = $is_optional ? Input_Argument::OPTIONAL : Input_Argument::REQUIRED;
        if ('array' === $self->type_name || $reflection->is_variadic()) {
            $self->mode |= Input_Argument::IS_ARRAY;
        }
        if (\is_array($self->suggested_values) && !\is_callable($self->suggested_values) && 2 === \count($self->suggested_values) && ($instance = $reflection->get_source_this()) && $instance::class === $self->suggested_values[0] && \is_callable([$instance, $self->suggested_values[1]])) {
            // In case that the callback is declared as a static method `[Foo::class, 'methodName']` - yet it is not callable,
            // while non-static method `[Foo $instance, 'methodName']` would be callable, we transform the callback on the fly into a non-static version.
            $self->suggested_values = [$instance, $self->suggested_values[1]];
        }
        if (is_subclass_of($self->type_name, \Backed_Enum::class) && !$self->suggested_values) {
            $self->suggested_values = array_column($self->type_name::cases(), 'value');
        }
        $self->interactive_attribute = Ask::try_from($member, $self->name) ?? Ask_Choice::try_from($member, $self->name);
        if ($self->interactive_attribute && $is_optional) {
            throw new LogicException(\sprintf('The %s "$%s" argument of "%s" cannot be both interactive and optional.', $reflection->get_member_name(), $self->name, $reflection->get_source_name()));
        }
        return $self;
    }
    /**
     * @internal
     */
    public function to_input_argument(): Input_Argument
    {
        $suggested_values = \is_callable($this->suggested_values) ? ($this->suggested_values)(...) : $this->suggested_values;
        return new Input_Argument($this->name, $this->mode, $this->description, $this->default, $suggested_values);
    }
    /**
     * @internal
     */
    public function get_interactive_attribute(): ?Interactive_Attribute_Interface
    {
        return $this->interactive_attribute;
    }
    /**
     * @internal
     */
    public function is_required(): bool
    {
        return Input_Argument::REQUIRED === (Input_Argument::REQUIRED & $this->mode);
    }
}