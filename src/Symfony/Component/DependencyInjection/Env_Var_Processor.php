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
namespace Symfony\Component\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Exception\Env_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Exception\Parameter_Circular_Reference_Exception;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Env_Var_Processor implements Env_Var_Processor_Interface, Reset_Interface
{
    /** @var \Traversable<EnvVarLoaderInterface> */
    private \Traversable $loaders;
    /** @var \Traversable<EnvVarLoaderInterface> */
    private readonly \Traversable $original_loaders;
    private array $loaded_vars = [];
    /**
     * @param \Traversable<EnvVarLoaderInterface>|null $loaders
     */
    public function __construct(private readonly Container_Interface $container, ?\Traversable $loaders = null)
    {
        $this->original_loaders = $this->loaders = $loaders ?? new \ArrayIterator();
    }
    public static function get_provided_types(): array
    {
        return ['base64' => 'string', 'bool' => 'bool', 'not' => 'bool', 'const' => 'bool|int|float|string|array', 'csv' => 'array', 'file' => 'string', 'float' => 'float', 'int' => 'int', 'json' => 'array', 'key' => 'bool|int|float|string|array', 'url' => 'array', 'query_string' => 'array', 'resolve' => 'string', 'default' => 'bool|int|float|string|array', 'string' => 'string', 'trim' => 'string', 'require' => 'bool|int|float|string|array', 'enum' => \Backed_Enum::class, 'shuffle' => 'array', 'defined' => 'bool', 'urlencode' => 'string'];
    }
    public function get_env(string $prefix, string $name, \Closure $get_env): mixed
    {
        $i = strpos($name, ':');
        if ('key' === $prefix) {
            if (false === $i) {
                throw new RuntimeException(\sprintf('Invalid env "key:%s": a key specifier should be provided.', $name));
            }
            $next = substr($name, $i + 1);
            $key = substr($name, 0, $i);
            $array = $get_env($next);
            if (!\is_array($array)) {
                throw new RuntimeException(\sprintf('Resolved value of "%s" did not result in an array value.', $next));
            }
            if (!isset($array[$key]) && !\array_key_exists($key, $array)) {
                throw new Env_Not_Found_Exception(\sprintf('Key "%s" not found in %s (resolved from "%s").', $key, json_encode($array), $next));
            }
            return $array[$key];
        }
        if ('enum' === $prefix) {
            if (false === $i) {
                throw new RuntimeException(\sprintf('Invalid env "enum:%s": a "%s" class-string should be provided.', $name, \Backed_Enum::class));
            }
            $next = substr($name, $i + 1);
            $backed_enum_class_name = substr($name, 0, $i);
            $backed_enum_value = $get_env($next);
            if (!\is_string($backed_enum_value) && !\is_int($backed_enum_value)) {
                throw new RuntimeException(\sprintf('Resolved value of "%s" did not result in a string or int value.', $next));
            }
            if (!is_subclass_of($backed_enum_class_name, \Backed_Enum::class)) {
                throw new RuntimeException(\sprintf('"%s" is not a "%s".', $backed_enum_class_name, \Backed_Enum::class));
            }
            return $backed_enum_class_name::try_from($backed_enum_value) ?? throw new RuntimeException(\sprintf('Enum value "%s" is not backed by "%s".', $backed_enum_value, $backed_enum_class_name));
        }
        if ('defined' === $prefix) {
            try {
                return '' !== ($get_env($name) ?? '');
            } catch (Env_Not_Found_Exception) {
                return false;
            }
        }
        if ('default' === $prefix) {
            if (false === $i) {
                throw new RuntimeException(\sprintf('Invalid env "default:%s": a fallback parameter should be provided.', $name));
            }
            $next = substr($name, $i + 1);
            $default = substr($name, 0, $i);
            if ('' !== $default && !$this->container->has_parameter($default)) {
                throw new RuntimeException(\sprintf('Invalid env fallback in "default:%s": parameter "%s" not found.', $name, $default));
            }
            try {
                $env = $get_env($next);
                if ('' !== $env && null !== $env) {
                    return $env;
                }
            } catch (Env_Not_Found_Exception) {
                // no-op
            }
            return '' === $default ? null : $this->container->get_parameter($default);
        }
        if ('file' === $prefix || 'require' === $prefix) {
            if (!\is_scalar($file = $get_env($name))) {
                throw new RuntimeException(\sprintf('Invalid file name: env var "%s" is non-scalar.', $name));
            }
            if (!is_file($file)) {
                throw new Env_Not_Found_Exception(\sprintf('File "%s" not found (resolved from "%s").', $file, $name));
            }
            if ('file' === $prefix) {
                return file_get_contents($file);
            }
            return require $file;
        }
        $return_null = false;
        if ('' === $prefix) {
            if ('' === $name) {
                return null;
            }
            $return_null = true;
            $prefix = 'string';
        }
        if (false !== $i || 'string' !== $prefix) {
            $env = $get_env($name);
        } elseif ('' === ($env = $_ENV[$name] ?? (str_starts_with($name, 'HTTP_') ? null : $_SERVER[$name] ?? null)) || false !== $env && false === $env ??= getenv($name) ?? false) {
            foreach ($this->loaded_vars as $i => $vars) {
                if (false === $env = $vars[$name] ?? $env) {
                    continue;
                }
                if ($env instanceof \Stringable) {
                    $this->loaded_vars[$i][$name] = $env = (string) $env;
                }
                if ('' !== ($env ?? '')) {
                    break;
                }
            }
            if (false === $env || '' === $env) {
                $loaders = $this->loaders;
                $this->loaders = new \ArrayIterator();
                try {
                    $i = 0;
                    $ended = true;
                    $count = $loaders instanceof \Countable ? $loaders->count() : 0;
                    foreach ($loaders as $loader) {
                        if (\count($this->loaded_vars) > $i++) {
                            continue;
                        }
                        $this->loaded_vars[] = $vars = $loader->load_env_vars();
                        if (false === $env = $vars[$name] ?? $env) {
                            continue;
                        }
                        if ($env instanceof \Stringable) {
                            $this->loaded_vars[array_key_last($this->loaded_vars)][$name] = $env = (string) $env;
                        }
                        if ('' !== ($env ?? '')) {
                            $ended = false;
                            break;
                        }
                    }
                    if ($ended || $count === $i) {
                        $loaders = $this->loaders;
                    }
                } catch (Parameter_Circular_Reference_Exception) {
                    // skip loaders that need an env var that is not defined
                } finally {
                    $this->loaders = $loaders;
                }
            }
            if (false === $env) {
                if (!$this->container->has_parameter("env({$name})")) {
                    throw new Env_Not_Found_Exception(\sprintf('Environment variable not found: "%s".', $name));
                }
                $env = $this->container->get_parameter("env({$name})");
            }
        }
        if (null === $env) {
            if ($return_null) {
                return null;
            }
            if (!isset(static::get_provided_types()[$prefix])) {
                throw new RuntimeException(\sprintf('Unsupported env var prefix "%s".', $prefix));
            }
            if (!\in_array($prefix, ['string', 'bool', 'not', 'int', 'float'], true)) {
                return null;
            }
        }
        if ('shuffle' === $prefix) {
            \is_array($env) ? shuffle($env) : throw new RuntimeException(\sprintf('Env var "%s" cannot be shuffled, expected array, got "%s".', $name, get_debug_type($env)));
            return $env;
        }
        if (null !== $env && !\is_scalar($env)) {
            throw new RuntimeException(\sprintf('Non-scalar env var "%s" cannot be cast to "%s".', $name, $prefix));
        }
        if ('string' === $prefix) {
            return (string) $env;
        }
        if (\in_array($prefix, ['bool', 'not'], true)) {
            $env = (bool) ((filter_var($env, \FILTER_VALIDATE_BOOL) ?: filter_var($env, \FILTER_VALIDATE_INT)) ?: filter_var($env, \FILTER_VALIDATE_FLOAT));
            return 'not' === $prefix xor $env;
        }
        if ('int' === $prefix) {
            if (null !== $env && false === $env = filter_var($env, \FILTER_VALIDATE_INT) ?: filter_var($env, \FILTER_VALIDATE_FLOAT)) {
                throw new RuntimeException(\sprintf('Non-numeric env var "%s" cannot be cast to int.', $name));
            }
            return (int) $env;
        }
        if ('float' === $prefix) {
            if (null !== $env && false === $env = filter_var($env, \FILTER_VALIDATE_FLOAT)) {
                throw new RuntimeException(\sprintf('Non-numeric env var "%s" cannot be cast to float.', $name));
            }
            return (float) $env;
        }
        if ('const' === $prefix) {
            if (!\defined($env)) {
                throw new RuntimeException(\sprintf('Env var "%s" maps to undefined constant "%s".', $name, $env));
            }
            return \constant($env);
        }
        if ('base64' === $prefix) {
            return base64_decode(strtr($env, '-_', '+/'));
        }
        if ('json' === $prefix) {
            $env = json_decode($env, true);
            if (\JSON_ERROR_NONE !== json_last_error()) {
                throw new RuntimeException(\sprintf('Invalid JSON in env var "%s": ', $name) . json_last_error_msg());
            }
            if (null !== $env && !\is_array($env)) {
                throw new RuntimeException(\sprintf('Invalid JSON env var "%s": array or null expected, "%s" given.', $name, get_debug_type($env)));
            }
            return $env;
        }
        if ('url' === $prefix) {
            $params = parse_url($env);
            if (false === $params) {
                throw new RuntimeException(\sprintf('Invalid URL in env var "%s".', $name));
            }
            if (!isset($params['scheme'], $params['host'])) {
                throw new RuntimeException(\sprintf('Invalid URL in env var "%s": scheme and host expected.', $name));
            }
            if (('\\' !== \DIRECTORY_SEPARATOR || 'file' !== $params['scheme']) && false !== ($i = strpos($env, '\\')) && $i < strcspn($env, '?#')) {
                throw new RuntimeException(\sprintf('Invalid URL in env var "%s": backslashes are not allowed.', $name));
            }
            if (\ord($env[0]) <= 32 || \ord($env[-1]) <= 32 || \strlen($env) !== strcspn($env, "\r\n\t")) {
                throw new RuntimeException(\sprintf('Invalid URL in env var "%s": leading/trailing ASCII control characters or whitespaces are not allowed.', $name));
            }
            $params += ['port' => null, 'user' => null, 'pass' => null, 'path' => null, 'query' => null, 'fragment' => null];
            $params['user'] = null !== $params['user'] ? rawurldecode($params['user']) : null;
            $params['pass'] = null !== $params['pass'] ? rawurldecode($params['pass']) : null;
            // remove the '/' separator
            $params['path'] = '/' === ($params['path'] ?? '/') ? '' : substr((string) $params['path'], 1);
            return $params;
        }
        if ('query_string' === $prefix) {
            $query_string = parse_url($env, \PHP_URL_QUERY) ?: (parse_url($env, \PHP_URL_SCHEME) ? '' : $env);
            parse_str($query_string, $result);
            return $result;
        }
        if ('resolve' === $prefix) {
            return preg_replace_callback('/%%|%([^%\s]+)%/', function ($match) use ($name, $get_env): int|float|string|bool {
                if (!isset($match[1])) {
                    return '%';
                }
                if (str_starts_with((string) $match[1], 'env(') && str_ends_with((string) $match[1], ')') && 'env()' !== $match[1]) {
                    $value = $get_env(substr((string) $match[1], 4, -1));
                } else {
                    $value = $this->container->get_parameter($match[1]);
                }
                if (!\is_scalar($value)) {
                    throw new RuntimeException(\sprintf('Parameter "%s" found when resolving env var "%s" must be scalar, "%s" given.', $match[1], $name, get_debug_type($value)));
                }
                return $value;
            }, $env);
        }
        if ('csv' === $prefix) {
            return '' === $env ? [] : str_getcsv($env, ',', '"', '');
        }
        if ('trim' === $prefix) {
            return trim($env);
        }
        if ('urlencode' === $prefix) {
            return rawurlencode($env);
        }
        throw new RuntimeException(\sprintf('Unsupported env var prefix "%s" for env name "%s".', $prefix, $name));
    }
    public function reset(): void
    {
        $this->loaded_vars = [];
        $this->loaders = $this->original_loaders;
        if ($this->container instanceof Container) {
            $this->container->reset_env_cache();
        }
    }
}