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
use Symfony\Component\Console\Question\Choice_Question;
use Symfony\Component\Console\Style\Symfony_Style;
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
class Ask_Choice implements Interactive_Attribute_Interface
{
    public ?\Closure $validator;
    public array|\Closure $choices;
    private \Closure $closure;
    /**
     * @param string                                                     $question     The question to ask the user
     * @param array<string|int|float>|callable():array<string|int|float> $choices      The list of available choices (leave empty to use enum cases)
     * @param string|int|float|null                                      $default      The default answer to return if the user enters nothing
     * @param string                                                     $errorMessage The error message when the answer is invalid
     * @param string                                                     $prompt       The prompt displayed before the user input
     * @param callable|null                                              $validator    The validator for the answer
     * @param int|null                                                   $maxAttempts  The maximum number of attempts allowed to answer the question.
     *                                                                                 Null means an unlimited number of attempts
     */
    public function __construct(public string $question, array|callable $choices = [], public string|int|float|null $default = null, public string $error_message = 'Value "%s" is invalid', public string $prompt = ' > ', ?callable $validator = null, public ?int $max_attempts = null)
    {
        $this->validator = $validator ? $validator(...) : null;
        $this->choices = \is_callable($choices) ? $choices(...) : $choices;
    }
    /**
     * @internal
     */
    public static function try_from(\ReflectionParameter|\ReflectionProperty $member, string $name): ?self
    {
        $reflection = new Reflection_Member($member);
        if (!$self = $reflection->get_attribute(self::class)) {
            return null;
        }
        $type = $reflection->get_type();
        if (!$type instanceof \ReflectionNamedType) {
            throw new LogicException(\sprintf('The %s "$%s" of "%s" must have a named type. Untyped, Union or Intersection types are not supported for choice questions.', $reflection->get_member_name(), $name, $reflection->get_source_name()));
        }
        $is_backed_enum = is_subclass_of($type->get_name(), \Backed_Enum::class);
        // Validate that choices are provided or can be derived from enum
        if (!$self->choices && !$is_backed_enum) {
            throw new LogicException(\sprintf('The #[AskChoice] attribute for the %s "$%s" of "%s" requires either explicit choices or a BackedEnum type.', $reflection->get_member_name(), $name, $reflection->get_source_name()));
        }
        $self->closure = function (Symfony_Style $io, Input_Interface $input) use ($self, $reflection, $name, $type, $is_backed_enum): void {
            if ($reflection->is_property() && isset($this->{$reflection->get_name()})) {
                return;
            }
            if ($reflection->is_parameter() && !\in_array($input->get_argument($name), [null, []], true)) {
                return;
            }
            $choices = $self->choices instanceof \Closure ? ($self->choices)() : $self->choices;
            // Derive choices from enum cases if not provided
            if (!$choices && $is_backed_enum) {
                /** @var class-string<\BackedEnum> $enumClass */
                $enum_class = $type->get_name();
                $choices = array_column($enum_class::cases(), 'value');
            }
            $question = new Choice_Question($self->question, $choices, $self->default);
            $question->set_multiselect('array' === $type->get_name());
            $question->set_error_message($self->error_message);
            $question->set_prompt($self->prompt);
            $question->set_max_attempts($self->max_attempts);
            if (!$self->validator && $reflection->is_property() && !$is_backed_enum && 'array' !== $type->get_name()) {
                $self->validator = fn(mixed $value): mixed => $this->{$reflection->get_name()} = $value;
            }
            if ($self->validator) {
                $question->set_validator($self->validator);
            }
            $value = $io->ask_question($question);
            if (null === $value && !$reflection->is_nullable()) {
                return;
            }
            // Convert back to enum if needed
            if ($is_backed_enum) {
                /** @var class-string<\BackedEnum> $enumClass */
                $enum_class = $type->get_name();
                if ($question->is_multiselect() && \is_array($value)) {
                    $value = array_map(static fn($v) => $enum_class::from($v), $value);
                } else {
                    $value = $enum_class::from($value);
                }
            }
            if ($reflection->is_property()) {
                $this->{$reflection->get_name()} = $value;
            } else {
                $input->set_argument($name, $value);
            }
        };
        return $self;
    }
    /**
     * @internal
     */
    public function get_function(object $instance): \ReflectionFunction
    {
        return new \ReflectionFunction($this->closure->bind_to($instance, $instance::class));
    }
}