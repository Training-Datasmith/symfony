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
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\String\Unicode_String;
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
class Option
{
    public const ALLOWED_UNION_TYPES = ['bool|string', 'bool|int', 'bool|float'];
    public mixed $default = null;
    public array|\Closure $suggested_values;
    /**
     * @internal
     *
     * @var string|class-string<\BackedEnum>
     */
    public string $type_name = '';
    /** @internal */
    public bool $allow_null = false;
    private ?int $mode = null;
    private string $member_name = '';
    private string $source_name = '';
    /**
     * Represents a console command --option definition.
     *
     * If unset, the `name` value will be inferred from the parameter definition.
     *
     * @param array|string|null                                                          $shortcut        The shortcuts, can be null, a string of shortcuts delimited by | or an array of shortcuts
     * @param array<string|Suggestion>|callable(CompletionInput):list<string|Suggestion> $suggestedValues The values used for input completion
     */
    public function __construct(public string $description = '', public string $name = '', public array|string|null $shortcut = null, array|callable $suggested_values = [])
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
        $self->member_name = $reflection->get_member_name();
        $self->source_name = $reflection->get_source_name();
        $name = $reflection->get_name();
        $type = $reflection->get_type();
        // Variadic parameters implicitly default to an empty array
        if (!$reflection->is_variadic() && !$reflection->has_default_value()) {
            throw new LogicException(\sprintf('The option %s "$%s" of "%s" must declare a default value.', $self->member_name, $name, $self->source_name));
        }
        if (!$self->name) {
            $self->name = (new Unicode_String($name))->kebab();
        }
        $self->default = $reflection->is_variadic() ? [] : $reflection->get_default_value();
        $self->allow_null = $reflection->is_nullable();
        if ($type instanceof \ReflectionUnionType) {
            return $self->handle_union($type);
        }
        if (!$type instanceof \ReflectionNamedType) {
            throw new LogicException(\sprintf('The %s "$%s" of "%s" must have a named type. Untyped or Intersection types are not supported for command options.', $self->member_name, $name, $self->source_name));
        }
        $self->type_name = $type->get_name();
        if ('bool' === $self->type_name && $self->allow_null && \in_array($self->default, [true, false], true)) {
            throw new LogicException(\sprintf('The option %s "$%s" of "%s" must not be nullable when it has a default boolean value.', $self->member_name, $name, $self->source_name));
        }
        if ($self->allow_null && null !== $self->default) {
            throw new LogicException(\sprintf('The option %s "$%s" of "%s" must either be not-nullable or have a default of null.', $self->member_name, $name, $self->source_name));
        }
        if ('bool' === $self->type_name) {
            $self->mode = Input_Option::VALUE_NONE;
            if (false !== $self->default) {
                $self->mode |= Input_Option::VALUE_NEGATABLE;
            }
        } elseif ('array' === $self->type_name || $reflection->is_variadic()) {
            $self->mode = Input_Option::VALUE_REQUIRED | Input_Option::VALUE_IS_ARRAY;
        } else {
            $self->mode = Input_Option::VALUE_REQUIRED;
        }
        if (\is_array($self->suggested_values) && !\is_callable($self->suggested_values) && 2 === \count($self->suggested_values) && ($instance = $reflection->get_source_this()) && $instance::class === $self->suggested_values[0] && \is_callable([$instance, $self->suggested_values[1]])) {
            $self->suggested_values = [$instance, $self->suggested_values[1]];
        }
        if (is_subclass_of($self->type_name, \Backed_Enum::class) && !$self->suggested_values) {
            $self->suggested_values = array_column($self->type_name::cases(), 'value');
        }
        return $self;
    }
    /**
     * @internal
     */
    public function to_input_option(): Input_Option
    {
        $default = Input_Option::VALUE_NONE === (Input_Option::VALUE_NONE & $this->mode) ? null : $this->default;
        $suggested_values = \is_callable($this->suggested_values) ? ($this->suggested_values)(...) : $this->suggested_values;
        return new Input_Option($this->name, $this->shortcut, $this->mode, $this->description, $default, $suggested_values);
    }
    private function handle_union(\ReflectionUnionType $type): self
    {
        $types = array_map(static fn(\Reflection_Type $t) => $t instanceof \ReflectionNamedType ? $t->get_name() : null, $type->get_types());
        sort($types);
        $this->type_name = implode('|', array_filter($types));
        if (!\in_array($this->type_name, self::ALLOWED_UNION_TYPES, true)) {
            throw new LogicException(\sprintf('The union type for %s "$%s" of "%s" is not supported as a command option. Only "%s" types are allowed.', $this->member_name, $this->name, $this->source_name, implode('", "', self::ALLOWED_UNION_TYPES)));
        }
        if (false !== $this->default) {
            throw new LogicException(\sprintf('The option %s "$%s" of "%s" must have a default value of false.', $this->member_name, $this->name, $this->source_name));
        }
        $this->mode = Input_Option::VALUE_OPTIONAL;
        return $this;
    }
}