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
namespace Symfony\Component\Console\Question;

use Symfony\Component\Console\Exception\InvalidArgumentException;
/**
 * Represents a choice question.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Choice_Question extends Question
{
    private bool $multiselect = false;
    private string $prompt = ' > ';
    private string $error_message = 'Value "%s" is invalid';
    /**
     * @param string                                   $question The question to ask to the user
     * @param array<string|bool|int|float|\Stringable> $choices  The list of available choices
     * @param string|bool|int|float|null               $default  The default answer to return
     */
    public function __construct(string $question, private readonly array $choices, string|bool|int|float|null $default = null)
    {
        if (!$choices) {
            throw new \LogicException('Choice question must have at least 1 choice available.');
        }
        parent::__construct($question, $default);
        $this->set_validator($this->get_default_validator());
        $this->set_autocompleter_values($choices);
    }
    /**
     * @return array<string|bool|int|float|\Stringable>
     */
    public function get_choices(): array
    {
        return $this->choices;
    }
    /**
     * Sets multiselect option.
     *
     * When multiselect is set to true, multiple choices can be answered.
     *
     * @return $this
     */
    public function set_multiselect(bool $multiselect): static
    {
        $this->multiselect = $multiselect;
        $this->set_validator($this->get_default_validator());
        return $this;
    }
    /**
     * Returns whether the choices are multiselect.
     */
    public function is_multiselect(): bool
    {
        return $this->multiselect;
    }
    /**
     * Gets the prompt for choices.
     */
    public function get_prompt(): string
    {
        return $this->prompt;
    }
    /**
     * Sets the prompt for choices.
     *
     * @return $this
     */
    public function set_prompt(string $prompt): static
    {
        $this->prompt = $prompt;
        return $this;
    }
    /**
     * Sets the error message for invalid values.
     *
     * The error message has a string placeholder (%s) for the invalid value.
     *
     * @return $this
     */
    public function set_error_message(string $error_message): static
    {
        $this->error_message = $error_message;
        $this->set_validator($this->get_default_validator());
        return $this;
    }
    private function get_default_validator(): callable
    {
        $choices = $this->choices;
        $error_message = $this->error_message;
        $multiselect = $this->multiselect;
        $is_assoc = $this->is_assoc($choices);
        return function ($selected) use ($choices, $error_message, $multiselect, $is_assoc) {
            if ($multiselect) {
                // Check for a separated comma values
                if (!preg_match('/^[^,]+(?:,[^,]+)*$/', (string) $selected, $matches)) {
                    throw new InvalidArgumentException(\sprintf($error_message, $selected));
                }
                $selected_choices = explode(',', (string) $selected);
            } else {
                $selected_choices = [$selected];
            }
            if ($this->is_trimmable()) {
                foreach ($selected_choices as $k => $v) {
                    $selected_choices[$k] = trim((string) $v);
                }
            }
            $multiselect_choices = [];
            foreach ($selected_choices as $value) {
                $results = [];
                foreach ($choices as $key => $choice) {
                    if ($choice === $value) {
                        $results[] = $key;
                    }
                }
                if (\count($results) > 1) {
                    throw new InvalidArgumentException(\sprintf('The provided answer is ambiguous. Value should be one of "%s".', implode('" or "', $results)));
                }
                $result = array_search($value, $choices);
                if (!$is_assoc) {
                    if (false !== $result) {
                        $result = $choices[$result];
                    } elseif (isset($choices[$value])) {
                        $result = $choices[$value];
                    }
                } elseif (false === $result && isset($choices[$value])) {
                    $result = $value;
                }
                if (false === $result) {
                    throw new InvalidArgumentException(\sprintf($error_message, $value));
                }
                // For associative choices, consistently return the key as string:
                $multiselect_choices[] = $is_assoc ? (string) $result : $result;
            }
            if ($multiselect) {
                return $multiselect_choices;
            }
            return current($multiselect_choices);
        };
    }
}