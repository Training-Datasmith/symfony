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
 * Input is the base class for all concrete Input classes.
 *
 * Three concrete classes are provided by default:
 *
 *  * `ArgvInput`: The input comes from the CLI arguments (argv)
 *  * `StringInput`: The input is provided as a string
 *  * `ArrayInput`: The input is provided as an array
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Input implements Input_Interface, Streamable_Input_Interface
{
    protected Input_Definition $definition;
    /** @var resource */
    protected $stream;
    protected array $options = [];
    protected array $arguments = [];
    protected bool $interactive = true;
    public function __construct(?Input_Definition $definition = null)
    {
        if (null === $definition) {
            $this->definition = new Input_Definition();
        } else {
            $this->bind($definition);
            $this->validate();
        }
    }
    public function bind(Input_Definition $definition): void
    {
        $this->arguments = [];
        $this->options = [];
        $this->definition = $definition;
        $this->parse();
    }
    /**
     * Processes command line arguments.
     */
    abstract protected function parse(): void;
    public function validate(): void
    {
        $definition = $this->definition;
        $given_arguments = $this->arguments;
        $missing_arguments = array_filter(array_keys($definition->get_arguments()), static fn(int|string $argument): bool => !\array_key_exists($argument, $given_arguments) && $definition->get_argument($argument)->is_required());
        if (\count($missing_arguments) > 0) {
            throw new RuntimeException(\sprintf('Not enough arguments (missing: "%s").', implode(', ', $missing_arguments)));
        }
    }
    public function is_interactive(): bool
    {
        return $this->interactive;
    }
    public function set_interactive(bool $interactive): void
    {
        $this->interactive = $interactive;
    }
    public function get_arguments(): array
    {
        return array_merge($this->definition->get_argument_defaults(), $this->arguments);
    }
    public function get_argument(string $name): mixed
    {
        if (!$this->definition->has_argument($name)) {
            throw new InvalidArgumentException(\sprintf('The "%s" argument does not exist.', $name));
        }
        return $this->arguments[$name] ?? $this->definition->get_argument($name)->get_default();
    }
    public function set_argument(string $name, mixed $value): void
    {
        if (!$this->definition->has_argument($name)) {
            throw new InvalidArgumentException(\sprintf('The "%s" argument does not exist.', $name));
        }
        $this->arguments[$name] = $value;
    }
    public function has_argument(string $name): bool
    {
        return $this->definition->has_argument($name);
    }
    public function get_options(): array
    {
        return array_merge($this->definition->get_option_defaults(), $this->options);
    }
    public function get_option(string $name): mixed
    {
        if ($this->definition->has_negation($name)) {
            if (null === $value = $this->get_option($this->definition->negation_to_name($name))) {
                return $value;
            }
            return !$value;
        }
        if (!$this->definition->has_option($name)) {
            throw new InvalidArgumentException(\sprintf('The "%s" option does not exist.', $name));
        }
        return \array_key_exists($name, $this->options) ? $this->options[$name] : $this->definition->get_option($name)->get_default();
    }
    public function set_option(string $name, mixed $value): void
    {
        if ($this->definition->has_negation($name)) {
            $this->options[$this->definition->negation_to_name($name)] = !$value;
            return;
        }
        if (!$this->definition->has_option($name)) {
            throw new InvalidArgumentException(\sprintf('The "%s" option does not exist.', $name));
        }
        $this->options[$name] = $value;
    }
    public function has_option(string $name): bool
    {
        if ($this->definition->has_option($name)) {
            return true;
        }
        return $this->definition->has_negation($name);
    }
    /**
     * Escapes a token through escapeshellarg if it contains unsafe chars.
     */
    public function escape_token(string $token): string
    {
        return preg_match('{^[\w-]+$}', $token) ? $token : escapeshellarg($token);
    }
    /**
     * @param resource $stream
     */
    public function set_stream($stream): void
    {
        $this->stream = $stream;
    }
    /**
     * @return resource
     */
    public function get_stream()
    {
        return $this->stream;
    }
}