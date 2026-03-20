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

use Symfony\Component\Console\Input\Input_Option;
/**
 * Stores all completion suggestions for the current input.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class Completion_Suggestions
{
    private array $value_suggestions = [];
    private array $option_suggestions = [];
    /**
     * Add a suggested value for an input option or argument.
     *
     * @return $this
     */
    public function suggest_value(string|Suggestion $value): static
    {
        $this->value_suggestions[] = !$value instanceof Suggestion ? new Suggestion($value) : $value;
        return $this;
    }
    /**
     * Add multiple suggested values at once for an input option or argument.
     *
     * @param list<string|Suggestion> $values
     *
     * @return $this
     */
    public function suggest_values(array $values): static
    {
        foreach ($values as $value) {
            $this->suggest_value($value);
        }
        return $this;
    }
    /**
     * Add a suggestion for an input option name.
     *
     * @return $this
     */
    public function suggest_option(Input_Option $option): static
    {
        $this->option_suggestions[] = $option;
        return $this;
    }
    /**
     * Add multiple suggestions for input option names at once.
     *
     * @param InputOption[] $options
     *
     * @return $this
     */
    public function suggest_options(array $options): static
    {
        foreach ($options as $option) {
            $this->suggest_option($option);
        }
        return $this;
    }
    /**
     * @return InputOption[]
     */
    public function get_option_suggestions(): array
    {
        return $this->option_suggestions;
    }
    /**
     * @return Suggestion[]
     */
    public function get_value_suggestions(): array
    {
        return $this->value_suggestions;
    }
}