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

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
/**
 * A InputDefinition represents a set of valid command line arguments and options.
 *
 * Usage:
 *
 *     $definition = new InputDefinition([
 *         new InputArgument('name', InputArgument::REQUIRED),
 *         new InputOption('foo', 'f', InputOption::VALUE_REQUIRED),
 *     ]);
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Input_Definition
{
    private array $arguments = [];
    private int $required_count = 0;
    private ?Input_Argument $last_array_argument = null;
    private ?Input_Argument $last_optional_argument = null;
    private array $options = [];
    private array $negations = [];
    private array $shortcuts = [];
    /**
     * @param array $definition An array of InputArgument and InputOption instance
     */
    public function __construct(array $definition = [])
    {
        $this->set_definition($definition);
    }
    /**
     * Sets the definition of the input.
     */
    public function set_definition(array $definition): void
    {
        $arguments = [];
        $options = [];
        foreach ($definition as $item) {
            if ($item instanceof Input_Option) {
                $options[] = $item;
            } else {
                $arguments[] = $item;
            }
        }
        $this->set_arguments($arguments);
        $this->set_options($options);
    }
    /**
     * Sets the InputArgument objects.
     *
     * @param InputArgument[] $arguments An array of InputArgument objects
     */
    public function set_arguments(array $arguments = []): void
    {
        $this->arguments = [];
        $this->required_count = 0;
        $this->last_optional_argument = null;
        $this->last_array_argument = null;
        $this->add_arguments($arguments);
    }
    /**
     * Adds an array of InputArgument objects.
     *
     * @param InputArgument[] $arguments An array of InputArgument objects
     */
    public function add_arguments(?array $arguments = []): void
    {
        if (null !== $arguments) {
            foreach ($arguments as $argument) {
                $this->add_argument($argument);
            }
        }
    }
    /**
     * @throws LogicException When incorrect argument is given
     */
    public function add_argument(Input_Argument $argument): void
    {
        if (isset($this->arguments[$argument->get_name()])) {
            throw new LogicException(\sprintf('An argument with name "%s" already exists.', $argument->get_name()));
        }
        if (null !== $this->last_array_argument) {
            throw new LogicException(\sprintf('Cannot add a required argument "%s" after an array argument "%s".', $argument->get_name(), $this->last_array_argument->get_name()));
        }
        if ($argument->is_required() && null !== $this->last_optional_argument) {
            throw new LogicException(\sprintf('Cannot add a required argument "%s" after an optional one "%s".', $argument->get_name(), $this->last_optional_argument->get_name()));
        }
        if ($argument->is_array()) {
            $this->last_array_argument = $argument;
        }
        if ($argument->is_required()) {
            ++$this->required_count;
        } else {
            $this->last_optional_argument = $argument;
        }
        $this->arguments[$argument->get_name()] = $argument;
    }
    /**
     * Returns an InputArgument by name or by position.
     *
     * @throws InvalidArgumentException When argument given doesn't exist
     */
    public function get_argument(string|int $name): Input_Argument
    {
        if (!$this->has_argument($name)) {
            throw new InvalidArgumentException(\sprintf('The "%s" argument does not exist.', $name));
        }
        $arguments = \is_int($name) ? array_values($this->arguments) : $this->arguments;
        return $arguments[$name];
    }
    /**
     * Returns true if an InputArgument object exists by name or position.
     */
    public function has_argument(string|int $name): bool
    {
        $arguments = \is_int($name) ? array_values($this->arguments) : $this->arguments;
        return isset($arguments[$name]);
    }
    /**
     * Gets the array of InputArgument objects.
     *
     * @return InputArgument[]
     */
    public function get_arguments(): array
    {
        return $this->arguments;
    }
    /**
     * Returns the number of InputArguments.
     */
    public function get_argument_count(): int
    {
        return null !== $this->last_array_argument ? \PHP_INT_MAX : \count($this->arguments);
    }
    /**
     * Returns the number of required InputArguments.
     */
    public function get_argument_required_count(): int
    {
        return $this->required_count;
    }
    /**
     * @return array<string|bool|int|float|array|null>
     */
    public function get_argument_defaults(): array
    {
        $values = [];
        foreach ($this->arguments as $argument) {
            $values[$argument->get_name()] = $argument->get_default();
        }
        return $values;
    }
    /**
     * Sets the InputOption objects.
     *
     * @param InputOption[] $options An array of InputOption objects
     */
    public function set_options(array $options = []): void
    {
        $this->options = [];
        $this->shortcuts = [];
        $this->negations = [];
        $this->add_options($options);
    }
    /**
     * Adds an array of InputOption objects.
     *
     * @param InputOption[] $options An array of InputOption objects
     */
    public function add_options(array $options = []): void
    {
        foreach ($options as $option) {
            $this->add_option($option);
        }
    }
    /**
     * @throws LogicException When option given already exist
     */
    public function add_option(Input_Option $option): void
    {
        if (isset($this->options[$option->get_name()]) && !$option->equals($this->options[$option->get_name()])) {
            throw new LogicException(\sprintf('An option named "%s" already exists.', $option->get_name()));
        }
        if (isset($this->negations[$option->get_name()])) {
            throw new LogicException(\sprintf('An option named "%s" already exists.', $option->get_name()));
        }
        if ($option->get_shortcut()) {
            foreach (explode('|', $option->get_shortcut()) as $shortcut) {
                if (isset($this->shortcuts[$shortcut]) && !$option->equals($this->options[$this->shortcuts[$shortcut]])) {
                    throw new LogicException(\sprintf('An option with shortcut "%s" already exists.', $shortcut));
                }
            }
        }
        $this->options[$option->get_name()] = $option;
        if ($option->get_shortcut()) {
            foreach (explode('|', $option->get_shortcut()) as $shortcut) {
                $this->shortcuts[$shortcut] = $option->get_name();
            }
        }
        if ($option->is_negatable()) {
            $negated_name = 'no-' . $option->get_name();
            if (isset($this->options[$negated_name])) {
                throw new LogicException(\sprintf('An option named "%s" already exists.', $negated_name));
            }
            $this->negations[$negated_name] = $option->get_name();
        }
    }
    /**
     * Returns an InputOption by name.
     *
     * @throws InvalidArgumentException When option given doesn't exist
     */
    public function get_option(string $name): Input_Option
    {
        if (!$this->has_option($name)) {
            throw new InvalidArgumentException(\sprintf('The "--%s" option does not exist.', $name));
        }
        return $this->options[$name];
    }
    /**
     * Returns true if an InputOption object exists by name.
     *
     * This method can't be used to check if the user included the option when
     * executing the command (use getOption() instead).
     */
    public function has_option(string $name): bool
    {
        return isset($this->options[$name]);
    }
    /**
     * Gets the array of InputOption objects.
     *
     * @return InputOption[]
     */
    public function get_options(): array
    {
        return $this->options;
    }
    /**
     * Returns true if an InputOption object exists by shortcut.
     */
    public function has_shortcut(string $name): bool
    {
        return isset($this->shortcuts[$name]);
    }
    /**
     * Returns true if an InputOption object exists by negated name.
     */
    public function has_negation(string $name): bool
    {
        return isset($this->negations[$name]);
    }
    /**
     * Gets an InputOption by shortcut.
     */
    public function get_option_for_shortcut(string $shortcut): Input_Option
    {
        return $this->get_option($this->shortcut_to_name($shortcut));
    }
    /**
     * @return array<string|bool|int|float|array|null>
     */
    public function get_option_defaults(): array
    {
        $values = [];
        foreach ($this->options as $option) {
            $values[$option->get_name()] = $option->get_default();
        }
        return $values;
    }
    /**
     * Returns the InputOption name given a shortcut.
     *
     * @throws InvalidArgumentException When option given does not exist
     *
     * @internal
     */
    public function shortcut_to_name(string $shortcut): string
    {
        if (!isset($this->shortcuts[$shortcut])) {
            throw new InvalidArgumentException(\sprintf('The "-%s" option does not exist.', $shortcut));
        }
        return $this->shortcuts[$shortcut];
    }
    /**
     * Returns the InputOption name given a negation.
     *
     * @throws InvalidArgumentException When option given does not exist
     *
     * @internal
     */
    public function negation_to_name(string $negation): string
    {
        if (!isset($this->negations[$negation])) {
            throw new InvalidArgumentException(\sprintf('The "--%s" option does not exist.', $negation));
        }
        return $this->negations[$negation];
    }
    /**
     * Gets the synopsis.
     */
    public function get_synopsis(bool $short = false): string
    {
        $elements = [];
        if ($short && $this->get_options()) {
            $elements[] = '[options]';
        } elseif (!$short) {
            foreach ($this->get_options() as $option) {
                $value = '';
                if ($option->accept_value()) {
                    $value = \sprintf(' %s%s%s', $option->is_value_optional() ? '[' : '', strtoupper($option->get_name()), $option->is_value_optional() ? ']' : '');
                }
                $shortcut = $option->get_shortcut() ? \sprintf('-%s|', $option->get_shortcut()) : '';
                $negation = $option->is_negatable() ? \sprintf('|--no-%s', $option->get_name()) : '';
                $elements[] = \sprintf('[%s--%s%s%s]', $shortcut, $option->get_name(), $value, $negation);
            }
        }
        if (\count($elements) && $this->get_arguments()) {
            $elements[] = '[--]';
        }
        $tail = '';
        foreach ($this->get_arguments() as $argument) {
            $element = '<' . $argument->get_name() . '>';
            if ($argument->is_array()) {
                $element .= '...';
            }
            if (!$argument->is_required()) {
                $element = '[' . $element;
                $tail .= ']';
            }
            $elements[] = $element;
        }
        return implode(' ', $elements) . $tail;
    }
}