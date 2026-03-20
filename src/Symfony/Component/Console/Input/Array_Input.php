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
use Symfony\Component\Console\Exception\Invalid_Option_Exception;
/**
 * ArrayInput represents an input provided as an array.
 *
 * Usage:
 *
 *     $input = new ArrayInput(['command' => 'foo:bar', 'foo' => 'bar', '--bar' => 'foobar']);
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Array_Input extends Input
{
    public function __construct(private readonly array $parameters, ?Input_Definition $definition = null)
    {
        parent::__construct($definition);
    }
    public function get_first_argument(): ?string
    {
        foreach ($this->parameters as $param => $value) {
            if ($param && \is_string($param) && '-' === $param[0]) {
                continue;
            }
            return $value;
        }
        return null;
    }
    public function has_parameter_option(string|array $values, bool $only_params = false): bool
    {
        $values = (array) $values;
        foreach ($this->parameters as $k => $v) {
            if (!\is_int($k)) {
                $v = $k;
            }
            if ($only_params && '--' === $v) {
                return false;
            }
            if (\in_array($v, $values)) {
                return true;
            }
        }
        return false;
    }
    public function get_parameter_option(string|array $values, string|bool|int|float|array|null $default = false, bool $only_params = false): mixed
    {
        $values = (array) $values;
        foreach ($this->parameters as $k => $v) {
            if ($only_params && ('--' === $k || \is_int($k) && '--' === $v)) {
                return $default;
            }
            if (\is_int($k)) {
                if (\in_array($v, $values)) {
                    return true;
                }
            } elseif (\in_array($k, $values)) {
                return $v;
            }
        }
        return $default;
    }
    /**
     * Returns a stringified representation of the args passed to the command.
     */
    public function __toString(): string
    {
        $params = [];
        foreach ($this->parameters as $param => $val) {
            if ($param && \is_string($param) && '-' === $param[0]) {
                $glue = '-' === $param[1] ? '=' : ' ';
                if (\is_array($val)) {
                    foreach ($val as $v) {
                        $params[] = $param . ('' != $v ? $glue . $this->escape_token($v) : '');
                    }
                } else {
                    $params[] = $param . ('' != $val ? $glue . $this->escape_token($val) : '');
                }
            } else {
                $params[] = \is_array($val) ? implode(' ', array_map($this->escape_token(...), $val)) : $this->escape_token($val);
            }
        }
        return implode(' ', $params);
    }
    protected function parse(): void
    {
        foreach ($this->parameters as $key => $value) {
            if ('--' === $key) {
                return;
            }
            if (str_starts_with((string) $key, '--')) {
                $this->add_long_option(substr((string) $key, 2), $value);
            } elseif (str_starts_with((string) $key, '-')) {
                $this->add_short_option(substr((string) $key, 1), $value);
            } else {
                $this->add_argument($key, $value);
            }
        }
    }
    /**
     * Adds a short option value.
     *
     * @throws InvalidOptionException When option given doesn't exist
     */
    private function add_short_option(string $shortcut, mixed $value): void
    {
        if (!$this->definition->has_shortcut($shortcut)) {
            throw new Invalid_Option_Exception(\sprintf('The "-%s" option does not exist.', $shortcut));
        }
        $this->add_long_option($this->definition->get_option_for_shortcut($shortcut)->get_name(), $value);
    }
    /**
     * Adds a long option value.
     *
     * @throws InvalidOptionException When option given doesn't exist
     * @throws InvalidOptionException When a required value is missing
     */
    private function add_long_option(string $name, mixed $value): void
    {
        if (!$this->definition->has_option($name)) {
            if (!$this->definition->has_negation($name)) {
                throw new Invalid_Option_Exception(\sprintf('The "--%s" option does not exist.', $name));
            }
            $option_name = $this->definition->negation_to_name($name);
            $this->options[$option_name] = false;
            return;
        }
        $option = $this->definition->get_option($name);
        if (null === $value) {
            if ($option->is_value_required()) {
                throw new Invalid_Option_Exception(\sprintf('The "--%s" option requires a value.', $name));
            }
            if (!$option->is_value_optional()) {
                $value = true;
            }
        }
        $this->options[$name] = $value;
    }
    /**
     * Adds an argument value.
     *
     * @throws InvalidArgumentException When argument given doesn't exist
     */
    private function add_argument(string|int $name, mixed $value): void
    {
        if (!$this->definition->has_argument($name)) {
            throw new InvalidArgumentException(\sprintf('The "%s" argument does not exist.', $name));
        }
        $this->arguments[$name] = $value;
    }
}