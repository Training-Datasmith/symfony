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
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\File\Input_File;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Question\Confirmation_Question;
use Symfony\Component\Console\Question\File_Question;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Validator\Constraint;
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
class Ask implements Interactive_Attribute_Interface
{
    public ?\Closure $normalizer;
    public ?\Closure $validator;
    private \Closure $closure;
    /**
     * @param string                     $question    The question to ask the user
     * @param string|bool|int|float|null $default     The default answer to return if the user enters nothing
     * @param bool                       $hidden      Whether the user response must be hidden or not
     * @param bool                       $multiline   Whether the user response should accept newline characters
     * @param bool                       $trimmable   Whether the user response must be trimmed or not
     * @param int|null                   $timeout     The maximum time the user has to answer the question in seconds
     * @param callable|null              $validator   The validator for the question
     * @param int|null                   $maxAttempts The maximum number of attempts allowed to answer the question.
     *                                                Null means an unlimited number of attempts
     */
    public function __construct(
        public string $question,
        public string|bool|int|float|null $default = null,
        public bool $hidden = false,
        public bool $multiline = false,
        public bool $trimmable = true,
        public ?int $timeout = null,
        ?callable $normalizer = null,
        ?callable $validator = null,
        public ?int $max_attempts = null,
        /** @var Constraint[] */
        public array $constraints = []
    )
    {
        $this->normalizer = $normalizer ? $normalizer(...) : null;
        $this->validator = $validator ? $validator(...) : null;
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
            throw new LogicException(\sprintf('The %s "$%s" of "%s" must have a named type. Untyped, Union or Intersection types are not supported for interactive questions.', $reflection->get_member_name(), $name, $reflection->get_source_name()));
        }
        $self->closure = function (Symfony_Style $io, Input_Interface $input) use ($self, $reflection, $name, $type): void {
            if ($reflection->is_property() && isset($this->{$reflection->get_name()})) {
                return;
            }
            if ($reflection->is_parameter() && !\in_array($input->get_argument($name), [null, []], true)) {
                return;
            }
            $type_name = $type->get_name();
            if (Input_File::class === $type_name) {
                $question = new File_Question($self->question);
                $question->set_validator($self->validator);
                $question->set_max_attempts($self->max_attempts);
                $question->set_constraints($self->constraints);
                $value = $io->ask_question($question);
                if (null === $value && !$reflection->is_nullable()) {
                    return;
                }
                if ($reflection->is_property()) {
                    $this->{$reflection->get_name()} = $value;
                } else {
                    $input->set_argument($name, $value);
                }
                return;
            }
            if ('bool' === $type_name) {
                $self->default ??= false;
                if (!\is_bool($self->default)) {
                    throw new LogicException(\sprintf('The "%s::$default" value for the %s "$%s" of "%s" must be a boolean.', self::class, $reflection->get_member_name(), $name, $reflection->get_source_name()));
                }
                $question = new Confirmation_Question($self->question, $self->default);
            } else {
                $question = new Question($self->question, $self->default);
            }
            $question->set_hidden($self->hidden);
            $question->set_multiline($self->multiline);
            $question->set_trimmable($self->trimmable);
            $question->set_timeout($self->timeout);
            if (!$self->validator && $reflection->is_property() && 'array' !== $type_name) {
                $self->validator = fn(mixed $value): mixed => $this->{$reflection->get_name()} = $value;
            }
            $question->set_validator($self->validator);
            $question->set_max_attempts($self->max_attempts);
            $question->set_constraints($self->constraints);
            if ($self->normalizer) {
                $question->set_normalizer($self->normalizer);
            } elseif (is_subclass_of($type_name, \Backed_Enum::class)) {
                /** @var class-string<\BackedEnum> $backedType */
                $backed_type = $reflection->get_type()->get_name();
                $question->set_normalizer(static fn(string|int $value) => $backed_type::try_from($value) ?? throw InvalidArgumentException::from_enum_value($reflection->get_name(), $value, array_column($backed_type::cases(), 'value')));
            }
            if ('array' === $type_name) {
                $value = [];
                while ($v = $io->ask_question($question)) {
                    if ("\x04" === $v || \PHP_EOL === $v || $question->is_trimmable() && '' === $v = trim($v)) {
                        break;
                    }
                    $value[] = $v;
                }
            } else {
                $value = $io->ask_question($question);
            }
            if (null === $value && !$reflection->is_nullable()) {
                return;
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