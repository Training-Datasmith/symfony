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
namespace Symfony\Component\Dependency_Injection\Parameter_Bag;

use Symfony\Component\Dependency_Injection\Exception\Empty_Parameter_Value_Exception;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\Parameter_Circular_Reference_Exception;
use Symfony\Component\Dependency_Injection\Exception\Parameter_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
/**
 * Holds parameters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Parameter_Bag implements Parameter_Bag_Interface
{
    protected array $parameters = [];
    protected bool $resolved = false;
    protected array $deprecated_parameters = [];
    protected array $non_empty_parameters = [];
    public function __construct(array $parameters = [])
    {
        $this->add($parameters);
    }
    public function clear(): void
    {
        $this->parameters = [];
    }
    public function add(array $parameters): void
    {
        foreach ($parameters as $key => $value) {
            $this->set($key, $value);
        }
    }
    public function all(): array
    {
        return $this->parameters;
    }
    public function all_deprecated(): array
    {
        return $this->deprecated_parameters;
    }
    public function all_non_empty(): array
    {
        return $this->non_empty_parameters;
    }
    public function get(string $name): array|bool|string|int|float|\Unit_Enum|null
    {
        if (!\array_key_exists($name, $this->parameters)) {
            if (!$name) {
                throw new Parameter_Not_Found_Exception($name);
            }
            if (\array_key_exists($name, $this->non_empty_parameters)) {
                throw new Parameter_Not_Found_Exception($name, extraMessage: $this->non_empty_parameters[$name]);
            }
            $alternatives = [];
            foreach ($this->parameters as $key => $parameter_value) {
                $lev = levenshtein($name, $key);
                if ($lev <= \strlen($name) / 3 || str_contains((string) $key, $name)) {
                    $alternatives[] = $key;
                }
            }
            $non_nested_alternative = null;
            if (!\count($alternatives) && str_contains($name, '.')) {
                $name_parts_length = array_map(strlen(...), explode('.', $name));
                $key = substr($name, 0, -1 * (1 + array_pop($name_parts_length)));
                while (\count($name_parts_length)) {
                    if ($this->has($key)) {
                        if (\is_array($this->get($key))) {
                            $non_nested_alternative = $key;
                        }
                        break;
                    }
                    $key = substr($key, 0, -1 * (1 + array_pop($name_parts_length)));
                }
            }
            throw new Parameter_Not_Found_Exception($name, null, null, null, $alternatives, $non_nested_alternative);
        }
        if (isset($this->deprecated_parameters[$name])) {
            trigger_deprecation(...$this->deprecated_parameters[$name]);
        }
        if (\array_key_exists($name, $this->non_empty_parameters) && (null === $this->parameters[$name] || '' === $this->parameters[$name] || [] === $this->parameters[$name])) {
            throw new Empty_Parameter_Value_Exception($this->non_empty_parameters[$name]);
        }
        return $this->parameters[$name];
    }
    public function set(string $name, array|bool|string|int|float|\Unit_Enum|null $value): void
    {
        if (is_numeric($name)) {
            throw new InvalidArgumentException(\sprintf('The parameter name "%s" cannot be numeric.', $name));
        }
        $this->parameters[$name] = $value;
    }
    /**
     * Deprecates a service container parameter.
     *
     * @throws ParameterNotFoundException if the parameter is not defined
     */
    public function deprecate(string $name, string $package, string $version, string $message = 'The parameter "%s" is deprecated.'): void
    {
        if (!\array_key_exists($name, $this->parameters)) {
            throw new Parameter_Not_Found_Exception($name);
        }
        $this->deprecated_parameters[$name] = [$package, $version, $message, $name];
    }
    public function cannot_be_empty(string $name, string $message): void
    {
        $this->non_empty_parameters[$name] = $message;
    }
    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->parameters);
    }
    public function remove(string $name): void
    {
        unset($this->parameters[$name], $this->deprecated_parameters[$name], $this->non_empty_parameters[$name]);
    }
    public function resolve(): void
    {
        if ($this->resolved) {
            return;
        }
        $parameters = [];
        foreach ($this->parameters as $key => $value) {
            try {
                $value = $this->resolve_value($value);
                $parameters[$key] = $this->unescape_value($value);
            } catch (Parameter_Not_Found_Exception $e) {
                $e->set_source_key($key);
                throw $e;
            }
        }
        $this->parameters = $parameters;
        $this->resolved = true;
    }
    /**
     * Replaces parameter placeholders (%name%) by their values.
     *
     * @template TValue of array<array|scalar>|scalar
     *
     * @param TValue $value
     * @param array  $resolving An array of keys that are being resolved (used internally to detect circular references)
     *
     * @psalm-return (TValue is scalar ? array|scalar : array<array|scalar>)
     *
     * @throws ParameterNotFoundException          if a placeholder references a parameter that does not exist
     * @throws ParameterCircularReferenceException if a circular reference if detected
     * @throws RuntimeException                    when a given parameter has a type problem
     */
    public function resolve_value(mixed $value, array $resolving = []): mixed
    {
        if (\is_array($value)) {
            $args = [];
            foreach ($value as $key => $v) {
                $resolved_key = \is_string($key) ? $this->resolve_value($key, $resolving) : $key;
                if (!\is_scalar($resolved_key) && !$resolved_key instanceof \Stringable) {
                    throw new RuntimeException(\sprintf('Array keys must be a scalar-value, but found key "%s" to resolve to type "%s".', $key, get_debug_type($resolved_key)));
                }
                $args[$resolved_key] = $this->resolve_value($v, $resolving);
            }
            return $args;
        }
        if (!\is_string($value) || '' === $value || !str_contains($value, '%')) {
            return $value;
        }
        return $this->resolve_string($value, $resolving);
    }
    /**
     * Resolves parameters inside a string.
     *
     * @param array $resolving An array of keys that are being resolved (used internally to detect circular references)
     *
     * @throws ParameterNotFoundException          if a placeholder references a parameter that does not exist
     * @throws ParameterCircularReferenceException if a circular reference if detected
     * @throws RuntimeException                    when a given parameter has a type problem
     */
    public function resolve_string(string $value, array $resolving = []): mixed
    {
        // we do this to deal with non string values (Boolean, integer, ...)
        // as the preg_replace_callback throw an exception when trying
        // a non-string in a parameter value
        if (preg_match('/^%([^%\s]+)%$/', $value, $match)) {
            $key = $match[1];
            if (isset($resolving[$key])) {
                throw new Parameter_Circular_Reference_Exception(array_keys($resolving));
            }
            $resolving[$key] = true;
            return $this->resolved ? $this->get($key) : $this->resolve_value($this->get($key), $resolving);
        }
        return preg_replace_callback('/%%|%([^%\s]+)%/', function (array $match) use ($resolving, $value) {
            // skip %%
            if (!isset($match[1])) {
                return '%%';
            }
            $key = $match[1];
            if (isset($resolving[$key])) {
                throw new Parameter_Circular_Reference_Exception(array_keys($resolving));
            }
            $resolved = $this->get($key);
            if (!\is_string($resolved) && !is_numeric($resolved)) {
                throw new RuntimeException(\sprintf('A string value must be composed of strings and/or numbers, but found parameter "%s" of type "%s" inside string value "%s".', $key, get_debug_type($resolved), $value));
            }
            $resolved = (string) $resolved;
            $resolving[$key] = true;
            return $this->is_resolved() ? $resolved : $this->resolve_string($resolved, $resolving);
        }, $value);
    }
    public function is_resolved(): bool
    {
        return $this->resolved;
    }
    public function escape_value(mixed $value): mixed
    {
        if (\is_string($value)) {
            return str_replace('%', '%%', $value);
        }
        if (\is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->escape_value($v);
            }
            return $result;
        }
        return $value;
    }
    public function unescape_value(mixed $value): mixed
    {
        if (\is_string($value)) {
            return str_replace('%%', '%', $value);
        }
        if (\is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->unescape_value($v);
            }
            return $result;
        }
        return $value;
    }
}