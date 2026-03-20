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
namespace Symfony\Component\Dotenv;

use Symfony\Component\Dotenv\Exception\Exception_Interface;
use Symfony\Component\Dotenv\Exception\Format_Exception;
use Symfony\Component\Dotenv\Exception\Format_Exception_Context;
use Symfony\Component\Dotenv\Exception\Path_Exception;
use Symfony\Component\Process\Exception\Exception_Interface as ProcessException;
use Symfony\Component\Process\Process;
/**
 * Manages .env files.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class Dotenv
{
    public const VARNAME_REGEX = '(?i:_?[A-Z][A-Z0-9_]*+)';
    public const STATE_VARNAME = 0;
    public const STATE_VALUE = 1;
    private string $path;
    private int $cursor;
    private int $lineno;
    private string $data;
    private int $end;
    private array $values = [];
    private array $prod_envs = ['prod'];
    private bool $use_putenv = false;
    private bool $resolve_vars = true;
    public function __construct(private readonly string $env_key = 'APP_ENV', private readonly string $debug_key = 'APP_DEBUG')
    {
    }
    /**
     * @return $this
     */
    public function set_prod_envs(array $prod_envs): static
    {
        $this->prod_envs = $prod_envs;
        return $this;
    }
    /**
     * @param bool $usePutenv If `putenv()` should be used to define environment variables or not.
     *                        Beware that `putenv()` is not thread safe, that's why it's not enabled by default
     *
     * @return $this
     */
    public function use_putenv(bool $use_putenv = true): static
    {
        $this->use_putenv = $use_putenv;
        return $this;
    }
    /**
     * Loads one or several .env files.
     *
     * @param string $path          A file to load
     * @param string ...$extraPaths A list of additional files to load
     *
     * @throws FormatException when a file has a syntax error
     * @throws PathException   when a file does not exist or is not readable
     */
    public function load(string $path, string ...$extra_paths): void
    {
        if ($extra_paths) {
            $previous_resolve_vars = $this->resolve_vars;
            $this->resolve_vars = false;
            try {
                $this->do_load(false, \func_get_args());
            } finally {
                $this->resolve_vars = $previous_resolve_vars;
            }
            $this->resolve_loaded_vars();
        } else {
            $this->do_load(false, [$path]);
        }
    }
    /**
     * Loads a .env file and the corresponding .env.local, .env.$env and .env.$env.local files if they exist.
     *
     * .env.local is always ignored in test env because tests should produce the same results for everyone.
     * .env.dist is loaded when it exists and .env is not found.
     *
     * @param string      $path                 A file to load
     * @param string|null $envKey               The name of the env vars that defines the app env
     * @param string      $defaultEnv           The app env to use when none is defined
     * @param array       $testEnvs             A list of app envs for which .env.local should be ignored
     * @param bool        $overrideExistingVars Whether existing environment variables set by the system should be overridden
     *
     * @throws FormatException when a file has a syntax error
     * @throws PathException   when a file does not exist or is not readable
     */
    public function load_env(string $path, ?string $env_key = null, string $default_env = 'dev', array $test_envs = ['test'], bool $override_existing_vars = false): void
    {
        $this->populate_path($path);
        $previous_resolve_vars = $this->resolve_vars;
        $this->resolve_vars = false;
        try {
            $k = $env_key ?? $this->env_key;
            if (is_file($path) || !is_file($p = "{$path}.dist")) {
                $this->do_load($override_existing_vars, [$path]);
            } else {
                $this->do_load($override_existing_vars, [$p]);
            }
            if (null === $env = $_SERVER[$k] ?? $_ENV[$k] ?? null) {
                $this->populate([$k => $env = $default_env], $override_existing_vars);
            }
            if (!\in_array($env, $test_envs, true) && is_file($p = "{$path}.local")) {
                $this->do_load($override_existing_vars, [$p]);
                $env = $_SERVER[$k] ?? $_ENV[$k] ?? $env;
            }
            if ('local' === $env) {
                return;
            }
            if (is_file($p = "{$path}.{$env}")) {
                $this->do_load($override_existing_vars, [$p]);
            }
            if (is_file($p = "{$path}.{$env}.local")) {
                $this->do_load($override_existing_vars, [$p]);
            }
        } finally {
            $this->resolve_vars = $previous_resolve_vars;
            $this->resolve_loaded_vars();
        }
    }
    /**
     * Loads env vars from .env.local.php if the file exists or from the other .env files otherwise.
     *
     * This method also configures the APP_DEBUG env var according to the current APP_ENV.
     *
     * See method loadEnv() for rules related to .env files.
     */
    public function boot_env(string $path, string $default_env = 'dev', array $test_envs = ['test'], bool $override_existing_vars = false): void
    {
        $p = $path . '.local.php';
        $env = is_file($p) ? include $p : null;
        $k = $this->env_key;
        if (\is_array($env) && ($override_existing_vars || !isset($env[$k]) || ($_SERVER[$k] ?? $_ENV[$k] ?? $env[$k]) === $env[$k])) {
            $this->populate_path($path);
            $this->populate($env, $override_existing_vars);
        } else {
            $this->load_env($path, $k, $default_env, $test_envs, $override_existing_vars);
        }
        $_SERVER += $_ENV;
        $k = $this->debug_key;
        $debug = $_SERVER[$k] ?? !\in_array($_SERVER[$this->env_key], $this->prod_envs, true);
        $_SERVER[$k] = $_ENV[$k] = (int) $debug || !\is_bool($debug) && filter_var($debug, \FILTER_VALIDATE_BOOL) ? '1' : '0';
    }
    /**
     * Loads one or several .env files and enables override existing vars.
     *
     * @param string $path          A file to load
     * @param string ...$extraPaths A list of additional files to load
     *
     * @throws FormatException when a file has a syntax error
     * @throws PathException   when a file does not exist or is not readable
     */
    public function overload(string $path, string ...$extra_paths): void
    {
        if ($extra_paths) {
            $previous_resolve_vars = $this->resolve_vars;
            $this->resolve_vars = false;
            try {
                $this->do_load(true, \func_get_args());
            } finally {
                $this->resolve_vars = $previous_resolve_vars;
            }
            $this->resolve_loaded_vars();
        } else {
            $this->do_load(true, [$path]);
        }
    }
    /**
     * Sets values as environment variables (via putenv, $_ENV, and $_SERVER).
     *
     * @param array $values               An array of env variables
     * @param bool  $overrideExistingVars Whether existing environment variables set by the system should be overridden
     */
    public function populate(array $values, bool $override_existing_vars = false): void
    {
        $update_loaded_vars = false;
        $loaded_vars = array_flip(explode(',', (string) ($_SERVER['SYMFONY_DOTENV_VARS'] ?? $_ENV['SYMFONY_DOTENV_VARS'] ?? '')));
        foreach ($values as $name => $value) {
            $not_http_name = !str_starts_with((string) $name, 'HTTP_');
            if (isset($_SERVER[$name]) && $not_http_name && !isset($_ENV[$name])) {
                $_ENV[$name] = $_SERVER[$name];
            }
            // don't check existence with getenv() because of thread safety issues
            if (!isset($loaded_vars[$name]) && !$override_existing_vars && isset($_ENV[$name])) {
                continue;
            }
            if ($this->use_putenv) {
                putenv("{$name}={$value}");
            }
            $_ENV[$name] = $value;
            if ($not_http_name) {
                $_SERVER[$name] = $value;
            }
            if (!isset($loaded_vars[$name])) {
                $loaded_vars[$name] = $update_loaded_vars = true;
            }
        }
        if ($update_loaded_vars) {
            unset($loaded_vars['']);
            $loaded_vars = implode(',', array_keys($loaded_vars));
            $_ENV['SYMFONY_DOTENV_VARS'] = $_SERVER['SYMFONY_DOTENV_VARS'] = $loaded_vars;
            if ($this->use_putenv) {
                putenv('SYMFONY_DOTENV_VARS=' . $loaded_vars);
            }
        }
    }
    /**
     * Parses the contents of an .env file.
     *
     * @param string $data The data to be parsed
     * @param string $path The original file name where data where stored (used for more meaningful error messages)
     *
     * @throws FormatException when a file has a syntax error
     */
    public function parse(string $data, string $path = '.env'): array
    {
        $this->path = $path;
        $this->data = str_replace(["\r\n", "\r"], "\n", $data);
        $this->lineno = 1;
        $this->cursor = 0;
        $this->end = \strlen($this->data);
        $state = self::STATE_VARNAME;
        $this->values = [];
        $name = '';
        $this->skip_empty_lines();
        while ($this->cursor < $this->end) {
            switch ($state) {
                case self::STATE_VARNAME:
                    $name = $this->lex_varname();
                    $state = self::STATE_VALUE;
                    break;
                case self::STATE_VALUE:
                    $this->values[$name] = $this->lex_value();
                    $state = self::STATE_VARNAME;
                    break;
            }
        }
        if (self::STATE_VALUE === $state) {
            $this->values[$name] = '';
        }
        try {
            return $this->values;
        } finally {
            $this->values = [];
            unset($this->path, $this->cursor, $this->lineno, $this->data, $this->end);
        }
    }
    private function lex_varname(): string
    {
        // var name + optional export
        if (!preg_match('/(export[ \t]++)?(' . self::VARNAME_REGEX . ')/A', $this->data, $matches, 0, $this->cursor)) {
            throw $this->create_format_exception('Invalid character in variable name');
        }
        $this->move_cursor($matches[0]);
        if ($this->cursor === $this->end || "\n" === $this->data[$this->cursor] || '#' === $this->data[$this->cursor]) {
            if ($matches[1]) {
                throw $this->create_format_exception('Unable to unset an environment variable');
            }
            throw $this->create_format_exception('Missing = in the environment variable declaration');
        }
        if (' ' === $this->data[$this->cursor] || "\t" === $this->data[$this->cursor]) {
            throw $this->create_format_exception('Whitespace characters are not supported after the variable name');
        }
        if ('=' !== $this->data[$this->cursor]) {
            throw $this->create_format_exception('Missing = in the environment variable declaration');
        }
        ++$this->cursor;
        return $matches[2];
    }
    private function lex_value(): string
    {
        if (preg_match('/[ \t]*+(?:#.*)?$/Am', $this->data, $matches, 0, $this->cursor)) {
            $this->move_cursor($matches[0]);
            $this->skip_empty_lines();
            return '';
        }
        if (' ' === $this->data[$this->cursor] || "\t" === $this->data[$this->cursor]) {
            throw $this->create_format_exception('Whitespace are not supported before the value');
        }
        $loaded_vars = array_flip(explode(',', (string) ($_SERVER['SYMFONY_DOTENV_VARS'] ?? $_ENV['SYMFONY_DOTENV_VARS'] ?? '')));
        unset($loaded_vars['']);
        $v = '';
        do {
            if ("'" === $this->data[$this->cursor]) {
                $len = 0;
                do {
                    if ($this->cursor + ++$len === $this->end) {
                        $this->cursor += $len;
                        throw $this->create_format_exception('Missing quote to end the value');
                    }
                } while ("'" !== $this->data[$this->cursor + $len]);
                $single_quoted = substr($this->data, 1 + $this->cursor, $len - 1);
                if (!$this->resolve_vars) {
                    $single_quoted = str_replace('$', "\x00", $single_quoted);
                }
                $v .= $single_quoted;
                $this->cursor += 1 + $len;
            } elseif ('"' === $this->data[$this->cursor]) {
                $value = '';
                if (++$this->cursor === $this->end) {
                    throw $this->create_format_exception('Missing quote to end the value');
                }
                while ('"' !== $this->data[$this->cursor] || '\\' === $this->data[$this->cursor - 1] && '\\' !== $this->data[$this->cursor - 2]) {
                    $value .= $this->data[$this->cursor];
                    ++$this->cursor;
                    if ($this->cursor === $this->end) {
                        throw $this->create_format_exception('Missing quote to end the value');
                    }
                }
                ++$this->cursor;
                $value = str_replace(['\"', '\r', '\n'], ['"', "\r", "\n"], $value);
                $resolved_value = $value;
                if ($this->resolve_vars) {
                    $resolved_value = $this->resolve_commands($resolved_value, $loaded_vars);
                    $resolved_value = $this->resolve_variables($resolved_value, $loaded_vars);
                    $resolved_value = str_replace('\\\\', '\\', $resolved_value);
                }
                $v .= $resolved_value;
            } else {
                $value = '';
                $prev_chr = $this->data[$this->cursor - 1];
                while ($this->cursor < $this->end && !\in_array($this->data[$this->cursor], ["\n", '"', "'"], true) && !((' ' === $prev_chr || "\t" === $prev_chr) && '#' === $this->data[$this->cursor])) {
                    if ('\\' === $this->data[$this->cursor] && isset($this->data[$this->cursor + 1]) && ('"' === $this->data[$this->cursor + 1] || "'" === $this->data[$this->cursor + 1])) {
                        ++$this->cursor;
                    }
                    $value .= $prev_chr = $this->data[$this->cursor];
                    if ('$' === $this->data[$this->cursor] && isset($this->data[$this->cursor + 1]) && '(' === $this->data[$this->cursor + 1]) {
                        ++$this->cursor;
                        $value .= '(' . $this->lex_nested_expression() . ')';
                    }
                    ++$this->cursor;
                }
                $value = rtrim($value);
                $resolved_value = $value;
                if ($this->resolve_vars) {
                    $resolved_value = $this->resolve_commands($resolved_value, $loaded_vars);
                    $resolved_value = $this->resolve_variables($resolved_value, $loaded_vars);
                    $resolved_value = str_replace('\\\\', '\\', $resolved_value);
                }
                if ($resolved_value === $value && preg_match('/\s+/', $value) && !str_contains($value, '$')) {
                    throw $this->create_format_exception('A value containing spaces must be surrounded by quotes');
                }
                $v .= $resolved_value;
                if ($this->cursor < $this->end && '#' === $this->data[$this->cursor]) {
                    break;
                }
            }
        } while ($this->cursor < $this->end && "\n" !== $this->data[$this->cursor]);
        $this->skip_empty_lines();
        return $v;
    }
    private function lex_nested_expression(): string
    {
        ++$this->cursor;
        $value = '';
        while ("\n" !== $this->data[$this->cursor] && ')' !== $this->data[$this->cursor]) {
            $value .= $this->data[$this->cursor];
            if ('(' === $this->data[$this->cursor]) {
                $value .= $this->lex_nested_expression() . ')';
            }
            ++$this->cursor;
            if ($this->cursor === $this->end) {
                throw $this->create_format_exception('Missing closing parenthesis.');
            }
        }
        if ("\n" === $this->data[$this->cursor]) {
            throw $this->create_format_exception('Missing closing parenthesis.');
        }
        return $value;
    }
    private function skip_empty_lines(): void
    {
        if (preg_match('/(?:\s*+(?:#[^\n]*+)?+)++/A', $this->data, $match, 0, $this->cursor)) {
            $this->move_cursor($match[0]);
        }
    }
    private function resolve_commands(string $value, array $loaded_vars): string
    {
        if (!str_contains($value, '$')) {
            return $value;
        }
        $regex = '/
            (\\\\)?               # escaped with a backslash?
            \$
            (?<cmd>
                \(                # require opening parenthesis
                ([^()]|\g<cmd>)+  # allow any number of non-parens, or balanced parens (by nesting the <cmd> expression recursively)
                \)                # require closing paren
            )
        /x';
        return preg_replace_callback($regex, function ($matches) use ($loaded_vars): string {
            if ('\\' === $matches[1]) {
                return substr((string) $matches[0], 1);
            }
            if ('\\' === \DIRECTORY_SEPARATOR) {
                throw new \LogicException('Resolving commands is not supported on Windows.');
            }
            if (!class_exists(Process::class)) {
                throw new \LogicException('Resolving commands requires the Symfony Process component. Try running "composer require symfony/process".');
            }
            $process = Process::from_shell_commandline('echo ' . $matches[0]);
            $env = [];
            foreach ($this->values as $name => $value) {
                if (isset($loaded_vars[$name]) || !isset($_ENV[$name]) && !(isset($_SERVER[$name]) && !str_starts_with($name, 'HTTP_'))) {
                    $env[$name] = $value;
                }
            }
            $process->set_env($env);
            try {
                $process->must_run();
            } catch (Process_Exception) {
                throw $this->create_format_exception(\sprintf('Issue expanding a command (%s)', $process->get_error_output()));
            }
            return rtrim($process->get_output(), "\n\r");
        }, $value);
    }
    private function resolve_variables(string $value, array $loaded_vars): string
    {
        if (!str_contains($value, '$')) {
            return $value;
        }
        $regex = '/
            (?<!\\\\)
            (?P<backslashes>\\\\*)             # escaped with a backslash?
            \$
            (?!\()                             # no opening parenthesis
            (?P<opening_brace>\{)?             # optional brace
            (?P<name>' . self::VARNAME_REGEX . ')? # var name
            (?P<default_value>:[-=][^\}]*+)?   # optional default value
            (?P<closing_brace>\})?             # optional closing brace
        /x';
        return preg_replace_callback($regex, function (array $matches) use ($loaded_vars): string {
            // odd number of backslashes means the $ character is escaped
            if (1 === \strlen((string) $matches['backslashes']) % 2) {
                return substr((string) $matches[0], 1);
            }
            // unescaped $ not followed by variable name
            if (!isset($matches['name'])) {
                return $matches[0];
            }
            if ('{' === $matches['opening_brace'] && !isset($matches['closing_brace'])) {
                throw $this->create_format_exception('Unclosed braces on variable expansion');
            }
            $name = $matches['name'];
            if (isset($loaded_vars[$name]) && isset($this->values[$name])) {
                $value = $this->values[$name];
            } elseif (isset($_ENV[$name])) {
                $value = $_ENV[$name];
            } elseif (isset($_SERVER[$name]) && !str_starts_with($name, 'HTTP_')) {
                $value = $_SERVER[$name];
            } elseif (isset($this->values[$name])) {
                $value = $this->values[$name];
            } else {
                $value = (string) getenv($name);
            }
            if ('' === $value && isset($matches['default_value']) && '' !== $matches['default_value']) {
                $unsupported_chars = strpbrk((string) $matches['default_value'], '\'"{$');
                if (false !== $unsupported_chars) {
                    throw $this->create_format_exception(\sprintf('Unsupported character "%s" found in the default value of variable "$%s".', $unsupported_chars[0], $name));
                }
                $value = substr((string) $matches['default_value'], 2);
                if ('=' === $matches['default_value'][1]) {
                    $this->values[$name] = $value;
                }
            }
            if (!$matches['opening_brace'] && isset($matches['closing_brace'])) {
                $value .= '}';
            }
            return $matches['backslashes'] . $value;
        }, $value);
    }
    private function move_cursor(string $text): void
    {
        $this->cursor += \strlen($text);
        $this->lineno += substr_count($text, "\n");
    }
    private function create_format_exception(string $message): Format_Exception
    {
        return new Format_Exception($message, new Format_Exception_Context($this->data, $this->path, $this->lineno, $this->cursor));
    }
    private function do_load(bool $override_existing_vars, array $paths): void
    {
        foreach ($paths as $path) {
            if (!is_readable($path) || is_dir($path)) {
                throw new Path_Exception($path);
            }
            $data = file_get_contents($path);
            if (str_starts_with($data, "﻿")) {
                throw new Format_Exception('Loading files starting with a byte-order-mark (BOM) is not supported.', new Format_Exception_Context($data, $path, 1, 0));
            }
            if (str_contains($data, "\x00")) {
                throw new Format_Exception('Loading files containing NUL bytes is not supported.', new Format_Exception_Context($data, $path, 1, 0));
            }
            $this->populate($this->parse($data, $path), $override_existing_vars);
        }
    }
    private function resolve_loaded_vars(): void
    {
        $loaded_vars = array_flip(explode(',', (string) ($_SERVER['SYMFONY_DOTENV_VARS'] ?? $_ENV['SYMFONY_DOTENV_VARS'] ?? '')));
        unset($loaded_vars['']);
        $this->values = [];
        $this->path = '';
        $this->data = '';
        $this->lineno = 0;
        $this->cursor = 0;
        $this->end = 0;
        for ($pass = 0; $pass < 5; ++$pass) {
            $resolved = [];
            foreach ($loaded_vars as $name => $_) {
                if ('SYMFONY_DOTENV_VARS' === $name) {
                    continue;
                }
                if (!str_contains((string) $value = $_ENV[$name] ?? '', '$')) {
                    continue;
                }
                $resolved_value = $this->resolve_commands($value, $loaded_vars);
                $resolved_value = $this->resolve_variables($resolved_value, $loaded_vars);
                $resolved_value = str_replace('\\\\', '\\', $resolved_value);
                if ($value !== $resolved_value) {
                    $resolved[$name] = $resolved_value;
                }
            }
            if (!$resolved) {
                break;
            }
            $this->populate($resolved, true);
        }
        if (5 === $pass && $resolved) {
            throw new class('Too many levels of variable indirection in env vars: ' . implode(', ', array_keys($resolved)) . '.') extends \LogicException implements Exception_Interface
            {
            };
        }
        // Restore literal $ signs that were protected from resolution (from single-quoted strings)
        $restored = [];
        foreach ($loaded_vars as $name => $_) {
            if ('SYMFONY_DOTENV_VARS' !== $name && str_contains((string) $value = $_ENV[$name] ?? '', "\x00")) {
                $restored[$name] = str_replace("\x00", '$', $value);
            }
        }
        if ($restored) {
            $this->populate($restored, true);
        }
        $this->values = [];
        unset($this->path, $this->data, $this->lineno, $this->cursor, $this->end);
    }
    private function populate_path(string $path): void
    {
        $_ENV['SYMFONY_DOTENV_PATH'] = $_SERVER['SYMFONY_DOTENV_PATH'] = $path;
        if ($this->use_putenv) {
            putenv('SYMFONY_DOTENV_PATH=' . $path);
        }
    }
}