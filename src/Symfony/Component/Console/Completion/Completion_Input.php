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
namespace Symfony\Component\Console\Completion;

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\Argv_Input;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Option;
/**
 * An input specialized for shell completion.
 *
 * This input allows unfinished option names or values and exposes what kind of
 * completion is expected.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class Completion_Input extends Argv_Input
{
    public const TYPE_ARGUMENT_VALUE = 'argument_value';
    public const TYPE_OPTION_VALUE = 'option_value';
    public const TYPE_OPTION_NAME = 'option_name';
    public const TYPE_NONE = 'none';
    private array $tokens;
    private int $current_index;
    private string $completion_type;
    private ?string $completion_name = null;
    private string $completion_value = '';
    /**
     * Converts a terminal string into tokens.
     *
     * This is required for shell completions without COMP_WORDS support.
     */
    public static function from_string(string $input_str, int $current_index): self
    {
        preg_match_all('/(?<=^|\s)([\'"]?)(.+?)(?<!\\\\)\1(?=$|\s)/', $input_str, $tokens);
        return self::from_tokens($tokens[0], $current_index);
    }
    /**
     * Create an input based on an COMP_WORDS token list.
     *
     * @param string[] $tokens       the set of split tokens (e.g. COMP_WORDS or argv)
     * @param int      $currentIndex the index of the cursor (e.g. COMP_CWORD)
     */
    public static function from_tokens(array $tokens, int $current_index): self
    {
        $input = new self($tokens);
        $input->tokens = $tokens;
        $input->current_index = $current_index;
        return $input;
    }
    public function bind(Input_Definition $definition): void
    {
        parent::bind($definition);
        $relevant_token = $this->get_relevant_token();
        if ('-' === $relevant_token[0]) {
            // the current token is an input option: complete either option name or option value
            [$option_token, $option_value] = explode('=', $relevant_token, 2) + ['', ''];
            $option = $this->get_option_from_token($option_token);
            if (null === $option && !$this->is_cursor_free()) {
                $this->completion_type = self::TYPE_OPTION_NAME;
                $this->completion_value = $relevant_token;
                return;
            }
            if ($option?->accept_value()) {
                $this->completion_type = self::TYPE_OPTION_VALUE;
                $this->completion_name = $option->get_name();
                $this->completion_value = $option_value ?: (!str_starts_with($option_token, '--') ? substr($option_token, 2) : '');
                return;
            }
        }
        $previous_token = $this->tokens[$this->current_index - 1];
        if ('-' === $previous_token[0] && '' !== trim((string) $previous_token, '-')) {
            // check if previous option accepted a value
            $previous_option = $this->get_option_from_token($previous_token);
            if ($previous_option?->accept_value()) {
                $this->completion_type = self::TYPE_OPTION_VALUE;
                $this->completion_name = $previous_option->get_name();
                $this->completion_value = $relevant_token;
                return;
            }
        }
        // complete argument value
        $this->completion_type = self::TYPE_ARGUMENT_VALUE;
        foreach ($this->definition->get_arguments() as $argument_name => $argument) {
            if (!isset($this->arguments[$argument_name])) {
                break;
            }
            $argument_value = $this->arguments[$argument_name];
            $this->completion_name = $argument_name;
            if (\is_array($argument_value)) {
                $this->completion_value = $argument_value ? $argument_value[array_key_last($argument_value)] : null;
            } else {
                $this->completion_value = $argument_value;
            }
        }
        if ($this->current_index >= \count($this->tokens)) {
            if (!isset($this->arguments[$argument_name]) || $this->definition->get_argument($argument_name)->is_array()) {
                $this->completion_name = $argument_name;
            } else {
                // we've reached the end
                $this->completion_type = self::TYPE_NONE;
                $this->completion_name = null;
            }
            $this->completion_value = '';
        }
    }
    /**
     * Returns the type of completion required.
     *
     * TYPE_ARGUMENT_VALUE when completing the value of an input argument
     * TYPE_OPTION_VALUE   when completing the value of an input option
     * TYPE_OPTION_NAME    when completing the name of an input option
     * TYPE_NONE           when nothing should be completed
     *
     * TYPE_OPTION_NAME and TYPE_NONE are already implemented by the Console component.
     *
     * @return self::TYPE_*
     */
    public function get_completion_type(): string
    {
        return $this->completion_type;
    }
    /**
     * The name of the input option or argument when completing a value.
     *
     * @return string|null returns null when completing an option name
     */
    public function get_completion_name(): ?string
    {
        return $this->completion_name;
    }
    /**
     * The value already typed by the user (or empty string).
     */
    public function get_completion_value(): string
    {
        return $this->completion_value;
    }
    public function must_suggest_option_values_for(string $option_name): bool
    {
        return self::TYPE_OPTION_VALUE === $this->get_completion_type() && $option_name === $this->get_completion_name();
    }
    public function must_suggest_argument_values_for(string $argument_name): bool
    {
        return self::TYPE_ARGUMENT_VALUE === $this->get_completion_type() && $argument_name === $this->get_completion_name();
    }
    protected function parse_token(string $token, bool $parse_options): bool
    {
        try {
            return parent::parse_token($token, $parse_options);
        } catch (RuntimeException) {
            // suppress errors, completed input is almost never valid
        }
        return $parse_options;
    }
    private function get_option_from_token(string $option_token): ?Input_Option
    {
        $option_name = ltrim($option_token, '-');
        if (!$option_name) {
            return null;
        }
        if ('-' === ($option_token[1] ?? ' ')) {
            // long option name
            return $this->definition->has_option($option_name) ? $this->definition->get_option($option_name) : null;
        }
        // short option name
        return $this->definition->has_shortcut($option_name[0]) ? $this->definition->get_option_for_shortcut($option_name[0]) : null;
    }
    /**
     * The token of the cursor, or the last token if the cursor is at the end of the input.
     */
    private function get_relevant_token(): string
    {
        return $this->tokens[$this->is_cursor_free() ? $this->current_index - 1 : $this->current_index];
    }
    /**
     * Whether the cursor is "free" (i.e. at the end of the input preceded by a space).
     */
    private function is_cursor_free(): bool
    {
        $nr_of_tokens = \count($this->tokens);
        if ($this->current_index > $nr_of_tokens) {
            throw new \LogicException('Current index is invalid, it must be the number of input tokens or one more.');
        }
        return $this->current_index >= $nr_of_tokens;
    }
    public function __toString(): string
    {
        $str = '';
        foreach ($this->tokens as $i => $token) {
            $str .= $token;
            if ($this->current_index === $i) {
                $str .= '|';
            }
            $str .= ' ';
        }
        if ($this->current_index > $i) {
            $str .= '|';
        }
        return rtrim($str);
    }
}