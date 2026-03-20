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

use Symfony\Component\Console\Exception\RuntimeException;
/**
 * ArgvInput represents an input coming from the CLI arguments.
 *
 * Usage:
 *
 *     $input = new ArgvInput();
 *
 * By default, the `$_SERVER['argv']` array is used for the input values.
 *
 * This can be overridden by explicitly passing the input values in the constructor:
 *
 *     $input = new ArgvInput($_SERVER['argv']);
 *
 * If you pass it yourself, don't forget that the first element of the array
 * is the name of the running application.
 *
 * When passing an argument to the constructor, be sure that it respects
 * the same rules as the argv one. It's almost always better to use the
 * `StringInput` when you want to provide your own input.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @see http://www.gnu.org/software/libc/manual/html_node/Argument-Syntax.html
 * @see http://www.opengroup.org/onlinepubs/009695399/basedefs/xbd_chap12.html#tag_12_02
 */
class Argv_Input extends Input
{
    /** @var list<string> */
    private array $tokens;
    private array $parsed;
    /** @param list<string>|null $argv */
    public function __construct(?array $argv = null, ?Input_Definition $definition = null)
    {
        $argv ??= $_SERVER['argv'] ?? [];
        foreach ($argv as $arg) {
            if (!\is_scalar($arg) && !$arg instanceof \Stringable) {
                throw new RuntimeException(\sprintf('Argument values expected to be all scalars, got "%s".', get_debug_type($arg)));
            }
        }
        // strip the application name
        array_shift($argv);
        $this->tokens = $argv;
        parent::__construct($definition);
    }
    /** @param list<string> $tokens */
    protected function set_tokens(array $tokens): void
    {
        $this->tokens = $tokens;
    }
    protected function parse(): void
    {
        $parse_options = true;
        $this->parsed = $this->tokens;
        while (null !== $token = array_shift($this->parsed)) {
            $parse_options = $this->parse_token($token, $parse_options);
        }
    }
    protected function parse_token(string $token, bool $parse_options): bool
    {
        if ($parse_options && '' == $token) {
            $this->parse_argument($token);
        } elseif ($parse_options && '--' == $token) {
            return false;
        } elseif ($parse_options && str_starts_with($token, '--')) {
            $this->parse_long_option($token);
        } elseif ($parse_options && '-' === $token[0] && '-' !== $token) {
            $this->parse_short_option($token);
        } else {
            $this->parse_argument($token);
        }
        return $parse_options;
    }
    /**
     * Parses a short option.
     */
    private function parse_short_option(string $token): void
    {
        $name = substr($token, 1);
        if (\strlen($name) > 1) {
            if ($this->definition->has_shortcut($name[0]) && $this->definition->get_option_for_shortcut($name[0])->accept_value()) {
                // an option with a value (with no space)
                $this->add_short_option($name[0], substr($name, 1));
            } else {
                $this->parse_short_option_set($name);
            }
        } else {
            $this->add_short_option($name, null);
        }
    }
    /**
     * Parses a short option set.
     *
     * @throws RuntimeException When option given doesn't exist
     */
    private function parse_short_option_set(string $name): void
    {
        $len = \strlen($name);
        for ($i = 0; $i < $len; ++$i) {
            if (!$this->definition->has_shortcut($name[$i])) {
                $encoding = mb_detect_encoding($name, null, true);
                throw new RuntimeException(\sprintf('The "-%s" option does not exist.', false === $encoding ? $name[$i] : mb_substr($name, $i, 1, $encoding)));
            }
            $option = $this->definition->get_option_for_shortcut($name[$i]);
            if ($option->accept_value()) {
                $this->add_long_option($option->get_name(), $i === $len - 1 ? null : substr($name, $i + 1));
                break;
            }
            $this->add_long_option($option->get_name(), null);
        }
    }
    /**
     * Parses a long option.
     */
    private function parse_long_option(string $token): void
    {
        $name = substr($token, 2);
        if (false !== $pos = strpos($name, '=')) {
            if ('' === $value = substr($name, $pos + 1)) {
                array_unshift($this->parsed, $value);
            }
            $this->add_long_option(substr($name, 0, $pos), $value);
        } else {
            $this->add_long_option($name, null);
        }
    }
    /**
     * Parses an argument.
     *
     * @throws RuntimeException When too many arguments are given
     */
    private function parse_argument(string $token): void
    {
        $c = \count($this->arguments);
        // if input is expecting another argument, add it
        if ($this->definition->has_argument($c)) {
            $arg = $this->definition->get_argument($c);
            $this->arguments[$arg->get_name()] = $arg->is_array() ? [$token] : $token;
            // if last argument isArray(), append token to last argument
        } elseif ($this->definition->has_argument($c - 1) && $this->definition->get_argument($c - 1)->is_array()) {
            $arg = $this->definition->get_argument($c - 1);
            $this->arguments[$arg->get_name()][] = $token;
            // unexpected argument
        } else {
            $all = $this->definition->get_arguments();
            $symfony_command_name = null;
            if (($input_argument = $all[$key = array_key_first($all) ?? ''] ?? null) && 'command' === $input_argument->get_name()) {
                $symfony_command_name = $this->arguments['command'] ?? null;
                unset($all[$key]);
            }
            if (\count($all)) {
                if ($symfony_command_name) {
                    $message = \sprintf('Too many arguments to "%s" command, expected arguments "%s".', $symfony_command_name, implode('" "', array_keys($all)));
                } else {
                    $message = \sprintf('Too many arguments, expected arguments "%s".', implode('" "', array_keys($all)));
                }
            } elseif ($symfony_command_name) {
                $message = \sprintf('No arguments expected for "%s" command, got "%s".', $symfony_command_name, $token);
            } else {
                $message = \sprintf('No arguments expected, got "%s".', $token);
            }
            throw new RuntimeException($message);
        }
    }
    /**
     * Adds a short option value.
     *
     * @throws RuntimeException When option given doesn't exist
     */
    private function add_short_option(string $shortcut, mixed $value): void
    {
        if (!$this->definition->has_shortcut($shortcut)) {
            throw new RuntimeException(\sprintf('The "-%s" option does not exist.', $shortcut));
        }
        $this->add_long_option($this->definition->get_option_for_shortcut($shortcut)->get_name(), $value);
    }
    /**
     * Adds a long option value.
     *
     * @throws RuntimeException When option given doesn't exist
     */
    private function add_long_option(string $name, mixed $value): void
    {
        if (!$this->definition->has_option($name)) {
            if (!$this->definition->has_negation($name)) {
                throw new RuntimeException(\sprintf('The "--%s" option does not exist.', $name));
            }
            $option_name = $this->definition->negation_to_name($name);
            if (null !== $value) {
                throw new RuntimeException(\sprintf('The "--%s" option does not accept a value.', $name));
            }
            $this->options[$option_name] = false;
            return;
        }
        $option = $this->definition->get_option($name);
        if (null !== $value && !$option->accept_value()) {
            throw new RuntimeException(\sprintf('The "--%s" option does not accept a value.', $name));
        }
        if (\in_array($value, ['', null], true) && $option->accept_value() && \count($this->parsed)) {
            // if option accepts an optional or mandatory argument
            // let's see if there is one provided
            $next = array_shift($this->parsed);
            if (isset($next[0]) && '-' !== $next[0] || \in_array($next, ['', null], true)) {
                $value = $next;
            } else {
                array_unshift($this->parsed, $next);
            }
        }
        if (null === $value) {
            if ($option->is_value_required()) {
                throw new RuntimeException(\sprintf('The "--%s" option requires a value.', $name));
            }
            if (!$option->is_array() && !$option->is_value_optional()) {
                $value = true;
            }
        }
        if ($option->is_array()) {
            $this->options[$name][] = $value;
        } else {
            $this->options[$name] = $value;
        }
    }
    public function get_first_argument(): ?string
    {
        $is_option = false;
        foreach ($this->tokens as $i => $token) {
            if ($token && '-' === $token[0]) {
                if (str_contains($token, '=')) {
                    continue;
                }
                if (!isset($this->tokens[$i + 1])) {
                    continue;
                }
                // If it's a long option, consider that everything after "--" is the option name.
                // Otherwise, use the last char (if it's a short option set, only the last one can take a value with space separator)
                $name = '-' === $token[1] ? substr($token, 2) : substr($token, -1);
                if (!isset($this->options[$name]) && !$this->definition->has_shortcut($name)) {
                    // noop
                } elseif ((isset($this->options[$name]) || isset($this->options[$name = $this->definition->shortcut_to_name($name)])) && $this->tokens[$i + 1] === $this->options[$name]) {
                    $is_option = true;
                }
                continue;
            }
            if ($is_option) {
                $is_option = false;
                continue;
            }
            return $token;
        }
        return null;
    }
    public function has_parameter_option(string|array $values, bool $only_params = false): bool
    {
        $values = (array) $values;
        foreach ($this->tokens as $token) {
            if ($only_params && '--' === $token) {
                return false;
            }
            foreach ($values as $value) {
                // Options with values:
                //   For long options, test for '--option=' at beginning
                //   For short options, test for '-o' at beginning
                $leading = str_starts_with((string) $value, '--') ? $value . '=' : $value;
                if ($token === $value || '' !== $leading && str_starts_with($token, (string) $leading)) {
                    return true;
                }
            }
        }
        return false;
    }
    public function get_parameter_option(string|array $values, string|bool|int|float|array|null $default = false, bool $only_params = false): mixed
    {
        $values = (array) $values;
        $tokens = $this->tokens;
        while (0 < \count($tokens)) {
            $token = array_shift($tokens);
            if ($only_params && '--' === $token) {
                return $default;
            }
            foreach ($values as $value) {
                if ($token === $value) {
                    return array_shift($tokens);
                }
                // Options with values:
                //   For long options, test for '--option=' at beginning
                //   For short options, test for '-o' at beginning
                $leading = str_starts_with((string) $value, '--') ? $value . '=' : $value;
                if ('' !== $leading && str_starts_with($token, (string) $leading)) {
                    return substr($token, \strlen((string) $leading));
                }
            }
        }
        return $default;
    }
    /**
     * Returns un-parsed and not validated tokens.
     *
     * @param bool $strip Whether to return the raw parameters (false) or the values after the command name (true)
     *
     * @return list<string>
     */
    public function get_raw_tokens(bool $strip = false): array
    {
        if (!$strip) {
            return $this->tokens;
        }
        $parameters = [];
        $keep = false;
        foreach ($this->tokens as $value) {
            if (!$keep && $value === $this->get_first_argument()) {
                $keep = true;
                continue;
            }
            if ($keep) {
                $parameters[] = $value;
            }
        }
        return $parameters;
    }
    /**
     * Returns a stringified representation of the args passed to the command.
     */
    public function __toString(): string
    {
        $tokens = array_map(function (string $token): string {
            if (preg_match('{^(-[^=]+=)(.+)}', $token, $match)) {
                return $match[1] . $this->escape_token($match[2]);
            }
            if ($token && '-' !== $token[0]) {
                return $this->escape_token($token);
            }
            return $token;
        }, $this->tokens);
        return implode(' ', $tokens);
    }
}