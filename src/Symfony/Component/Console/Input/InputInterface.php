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
use Symfony\Component\Console\Exception\RuntimeException;
/**
 * InputInterface is the interface implemented by all input classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Input_Interface
{
    /**
     * Returns the first argument from the raw parameters (not parsed).
     */
    public function get_first_argument(): ?string;
    /**
     * Returns true if the raw parameters (not parsed) contain a value.
     *
     * This method is to be used to introspect the input parameters
     * before they have been validated. It must be used carefully.
     * Does not necessarily return the correct result for short options
     * when multiple flags are combined in the same option.
     *
     * @param string|array $values     The values to look for in the raw parameters (can be an array)
     * @param bool         $onlyParams Only check real parameters, skip those following an end of options (--) signal
     */
    public function has_parameter_option(string|array $values, bool $only_params = false): bool;
    /**
     * Returns the value of a raw option (not parsed).
     *
     * This method is to be used to introspect the input parameters
     * before they have been validated. It must be used carefully.
     * Does not necessarily return the correct result for short options
     * when multiple flags are combined in the same option.
     *
     * @param string|array                     $values     The value(s) to look for in the raw parameters (can be an array)
     * @param string|bool|int|float|array|null $default    The default value to return if no result is found
     * @param bool                             $onlyParams Only check real parameters, skip those following an end of options (--) signal
     */
    public function get_parameter_option(string|array $values, string|bool|int|float|array|null $default = false, bool $only_params = false): mixed;
    /**
     * Binds the current Input instance with the given arguments and options.
     *
     * @throws RuntimeException
     */
    public function bind(Input_Definition $definition): void;
    /**
     * Validates the input.
     *
     * @throws RuntimeException When not enough arguments are given
     */
    public function validate(): void;
    /**
     * Returns all the given arguments merged with the default values.
     *
     * @return array<string|bool|int|float|array|null>
     */
    public function get_arguments(): array;
    /**
     * Returns the argument value for a given argument name.
     *
     * @throws InvalidArgumentException When argument given doesn't exist
     */
    public function get_argument(string $name): mixed;
    /**
     * Sets an argument value by name.
     *
     * @throws InvalidArgumentException When argument given doesn't exist
     */
    public function set_argument(string $name, mixed $value): void;
    /**
     * Returns true if an InputArgument object exists by name or position.
     */
    public function has_argument(string $name): bool;
    /**
     * Returns all the given options merged with the default values.
     *
     * @return array<string|bool|int|float|array|null>
     */
    public function get_options(): array;
    /**
     * Returns the option value for a given option name.
     *
     * @throws InvalidArgumentException When option given doesn't exist
     */
    public function get_option(string $name): mixed;
    /**
     * Sets an option value by name.
     *
     * @throws InvalidArgumentException When option given doesn't exist
     */
    public function set_option(string $name, mixed $value): void;
    /**
     * Returns true if an InputOption object exists by name.
     */
    public function has_option(string $name): bool;
    /**
     * Is this input means interactive?
     */
    public function is_interactive(): bool;
    /**
     * Sets the input interactivity.
     */
    public function set_interactive(bool $interactive): void;
    /**
     * Returns a stringified representation of the args passed to the command.
     *
     * InputArguments MUST be escaped as well as the InputOption values passed to the command.
     */
    public function __toString(): string;
}