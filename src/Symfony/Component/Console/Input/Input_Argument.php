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
namespace Symfony\Component\Console\Input;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
/**
 * Represents a command line argument.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Input_Argument
{
    /**
     * Providing an argument is required (e.g. just 'app:foo' is not allowed).
     */
    public const REQUIRED = 1;
    /**
     * Providing an argument is optional (e.g. 'app:foo' and 'app:foo bar' are both allowed). This is the default behavior of arguments.
     */
    public const OPTIONAL = 2;
    /**
     * The argument accepts multiple values and turn them into an array (e.g. 'app:foo bar baz' will result in value ['bar', 'baz']).
     */
    public const IS_ARRAY = 4;
    private readonly int $mode;
    private mixed $default;
    /**
     * @param string                                                                        $name            The argument name
     * @param int-mask-of<InputArgument::*>|null                                            $mode            The argument mode: a bit mask of self::REQUIRED, self::OPTIONAL and self::IS_ARRAY
     * @param string                                                                        $description     A description text
     * @param mixed                                                                         $default         The default value (for self::OPTIONAL mode only)
     * @param array|\Closure(CompletionInput,CompletionSuggestions):list<string|Suggestion> $suggestedValues The values used for input completion
     *
     * @throws InvalidArgumentException When argument mode is not valid
     */
    public function __construct(private readonly string $name, ?int $mode = null, private readonly string $description = '', mixed $default = null, private readonly \Closure|array $suggested_values = [])
    {
        // If not explicitly marked as required, we assume the value to be optional
        $mode = self::REQUIRED === (self::REQUIRED & $mode) ? $mode : self::OPTIONAL | $mode;
        if ($mode >= self::IS_ARRAY << 1 || $mode < 1) {
            throw new InvalidArgumentException(\sprintf('Argument mode "%s" is not valid.', $mode));
        }
        if ((self::REQUIRED | self::OPTIONAL) === ((self::REQUIRED | self::OPTIONAL) & $mode)) {
            trigger_deprecation('symfony/console', '8.1', 'Argument "%s" mode should specify either required or optional.', $name);
        }
        $this->mode = $mode;
        $this->set_default($default);
    }
    /**
     * Returns the argument name.
     */
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * Returns true if the argument is required.
     *
     * @return bool true if parameter mode is self::REQUIRED, false otherwise
     */
    public function is_required(): bool
    {
        return self::REQUIRED === (self::REQUIRED & $this->mode);
    }
    /**
     * Returns true if the argument can take multiple values.
     *
     * @return bool true if mode is self::IS_ARRAY, false otherwise
     */
    public function is_array(): bool
    {
        return self::IS_ARRAY === (self::IS_ARRAY & $this->mode);
    }
    /**
     * Sets the default value.
     */
    public function set_default(mixed $default): void
    {
        if ($this->is_required() && null !== $default) {
            throw new LogicException('Cannot set a default value except for InputArgument::OPTIONAL mode.');
        }
        if ($this->is_array()) {
            if (null === $default) {
                $default = [];
            } elseif (!\is_array($default)) {
                throw new LogicException('A default value for an array argument must be an array.');
            }
        }
        $this->default = $default;
    }
    /**
     * Returns the default value.
     */
    public function get_default(): mixed
    {
        return $this->default;
    }
    /**
     * Returns true if the argument has values for input completion.
     */
    public function has_completion(): bool
    {
        return [] !== $this->suggested_values;
    }
    /**
     * Supplies suggestions when command resolves possible completion options for input.
     *
     * @see Command::complete()
     */
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        $values = $this->suggested_values;
        if ($values instanceof \Closure && !\is_array($values = $values($input))) {
            throw new LogicException(\sprintf('Closure for argument "%s" must return an array. Got "%s".', $this->name, get_debug_type($values)));
        }
        if ($values) {
            $suggestions->suggest_values($values);
        }
    }
    /**
     * Returns the description text.
     */
    public function get_description(): string
    {
        return $this->description;
    }
}