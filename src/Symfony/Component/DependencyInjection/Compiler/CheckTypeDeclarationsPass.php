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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Rewindable_Generator;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Container;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\Invalid_Parameter_Type_Exception;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Expression_Language;
use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Env_Placeholder_Parameter_Bag;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Service_Locator;
use Symfony\Component\Expression_Language\Expression;
/**
 * Checks whether injected parameters are compatible with type declarations.
 *
 * This pass should be run after all optimization passes.
 *
 * It can be added either:
 *  * before removing passes to check all services even if they are not currently used,
 *  * after removing passes to check only services are used in the app.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Julien Maulny <jmaulny@darkmira.fr>
 */
final class Check_Type_Declarations_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private const SCALAR_TYPES = ['int' => true, 'float' => true, 'bool' => true, 'string' => true];
    private const BUILTIN_TYPES = ['array' => true, 'bool' => true, 'callable' => true, 'float' => true, 'int' => true, 'iterable' => true, 'object' => true, 'string' => true];
    private Expression_Language $expression_language;
    /**
     * @param bool  $autoload   Whether services who's class in not loaded should be checked or not.
     *                          Defaults to false to save loading code during compilation.
     * @param array $skippedIds An array indexed by the service ids to skip
     */
    public function __construct(private readonly bool $autoload = false, private array $skipped_ids = [])
    {
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if (isset($this->skipped_ids[$this->current_id ?? ''])) {
            return $value;
        }
        if (!$value instanceof Definition || $value->has_errors() || $value->is_deprecated()) {
            return parent::process_value($value, $is_root);
        }
        if (!$this->autoload) {
            if (!$class = $value->get_class()) {
                return parent::process_value($value, $is_root);
            }
            if (!class_exists($class, false) && !interface_exists($class, false)) {
                return parent::process_value($value, $is_root);
            }
        }
        if (Service_Locator::class === $value->get_class()) {
            return parent::process_value($value, $is_root);
        }
        if ($constructor = $this->get_constructor($value, false)) {
            $this->check_type_declarations($value, $constructor, $value->get_arguments());
        }
        foreach ($value->get_method_calls() as $method_call) {
            try {
                $reflection_method = $this->get_reflection_method($value, $method_call[0]);
            } catch (RuntimeException $e) {
                if ($value->get_factory()) {
                    continue;
                }
                throw $e;
            }
            $this->check_type_declarations($value, $reflection_method, $method_call[1]);
        }
        return parent::process_value($value, $is_root);
    }
    /**
     * @throws InvalidArgumentException When not enough parameters are defined for the method
     */
    private function check_type_declarations(Definition $checked_definition, \Reflection_Function_Abstract $reflection_function, array $values): void
    {
        $number_of_required_parameters = $reflection_function->get_number_of_required_parameters();
        if (\count($values) < $number_of_required_parameters) {
            throw new InvalidArgumentException(\sprintf('Invalid definition for service "%s": "%s::%s()" requires %d arguments, %d passed.', $this->current_id, $reflection_function->class, $reflection_function->name, $number_of_required_parameters, \count($values)));
        }
        $reflection_parameters = $reflection_function->get_parameters();
        $checks_count = min($reflection_function->get_number_of_parameters(), \count($values));
        $env_placeholder_unique_prefix = $this->container->get_parameter_bag() instanceof Env_Placeholder_Parameter_Bag ? $this->container->get_parameter_bag()->get_env_placeholder_unique_prefix() : null;
        for ($i = 0; $i < $checks_count; ++$i) {
            $p = $reflection_parameters[$i];
            if (!$p->has_type()) {
                continue;
            }
            if ($p->is_variadic()) {
                continue;
            }
            $key = $i;
            if (\array_key_exists($p->name, $values)) {
                $key = $p->name;
            } elseif (!\array_key_exists($i, $values)) {
                continue;
            }
            $this->check_type($checked_definition, $values[$key], $p, $env_placeholder_unique_prefix);
        }
        if ($reflection_function->is_variadic() && ($last_parameter = end($reflection_parameters))->has_type()) {
            $variadic_parameters = \array_slice($values, $last_parameter->get_position());
            foreach ($variadic_parameters as $variadic_parameter) {
                $this->check_type($checked_definition, $variadic_parameter, $last_parameter, $env_placeholder_unique_prefix);
            }
        }
    }
    /**
     * @throws InvalidParameterTypeException When a parameter is not compatible with the declared type
     */
    private function check_type(Definition $checked_definition, mixed $value, \ReflectionParameter $parameter, ?string $env_placeholder_unique_prefix, ?\Reflection_Type $reflection_type = null): void
    {
        $reflection_type ??= $parameter->get_type();
        if ($reflection_type instanceof \ReflectionUnionType) {
            foreach ($reflection_type->get_types() as $t) {
                try {
                    $this->check_type($checked_definition, $value, $parameter, $env_placeholder_unique_prefix, $t);
                    return;
                } catch (Invalid_Parameter_Type_Exception) {
                }
            }
            throw new Invalid_Parameter_Type_Exception($this->current_id, $e->get_code(), $parameter);
        }
        if ($reflection_type instanceof \ReflectionIntersectionType) {
            foreach ($reflection_type->get_types() as $t) {
                $this->check_type($checked_definition, $value, $parameter, $env_placeholder_unique_prefix, $t);
            }
            return;
        }
        if (!$reflection_type instanceof \ReflectionNamedType) {
            return;
        }
        $type = $reflection_type->get_name();
        if ($value instanceof Reference) {
            if (!$this->container->has($value = (string) $value)) {
                return;
            }
            if ('service_container' === $value && is_a($type, Container::class, true)) {
                return;
            }
            $value = $this->container->find_definition($value);
        }
        if ('self' === $type) {
            $type = $parameter->get_declaring_class()->name;
        }
        if ('static' === $type) {
            $type = $checked_definition->get_class();
        }
        $class = null;
        if ($value instanceof Definition) {
            if ($value->has_errors() || $value->get_factory()) {
                return;
            }
            $class = $value->get_class();
            if ($class && isset(self::BUILTIN_TYPES[strtolower($class)])) {
                $class = strtolower($class);
            } elseif (!$class || !$this->autoload && !class_exists($class, false) && !interface_exists($class, false)) {
                return;
            }
        } elseif ($value instanceof Parameter) {
            $value = $this->container->get_parameter($value);
        } elseif ($value instanceof Expression) {
            try {
                $value = $this->get_expression_language()->evaluate($value, ['container' => $this->container]);
            } catch (\Exception) {
                // If a service from the expression cannot be fetched from the container, we skip the validation.
                return;
            }
        } elseif (\is_string($value)) {
            if ('%' === ($value[0] ?? '') && preg_match('/^%([^%]+)%$/', $value, $match)) {
                $value = $this->container->get_parameter(substr($value, 1, -1));
            }
            if ($env_placeholder_unique_prefix && \is_string($value) && str_contains($value, 'env_')) {
                // If the value is an env placeholder that is either mixed with a string or with another env placeholder, then its resolved value will always be a string, so we don't need to resolve it.
                // We don't need to change the value because it is already a string.
                if ('' === preg_replace('/' . $env_placeholder_unique_prefix . '_\w+_[a-f0-9]{32}/U', '', $value, -1, $c) && 1 === $c) {
                    try {
                        $value = $this->container->resolve_env_placeholders($value, true);
                    } catch (\Exception) {
                        // If an env placeholder cannot be resolved, we skip the validation.
                        return;
                    }
                }
            }
        }
        if (null === $value && $parameter->allows_null()) {
            return;
        }
        if (null === $class) {
            if ($value instanceof Iterator_Argument) {
                $class = Rewindable_Generator::class;
            } elseif ($value instanceof Service_Closure_Argument) {
                $class = \Closure::class;
            } elseif ($value instanceof Service_Locator_Argument) {
                $class = Service_Locator::class;
            } elseif (\is_object($value)) {
                $class = $value::class;
            } else {
                $class = \gettype($value);
                $class = ['integer' => 'int', 'double' => 'float', 'boolean' => 'bool'][$class] ?? $class;
            }
        }
        if (isset(self::SCALAR_TYPES[$type]) && isset(self::SCALAR_TYPES[$class])) {
            return;
        }
        if ('string' === $type && is_a($class, \Stringable::class, true)) {
            return;
        }
        if ('callable' === $type && (\Closure::class === $class || method_exists($class, '__invoke'))) {
            return;
        }
        if ('callable' === $type && \is_array($value) && isset($value[0]) && ($value[0] instanceof Reference || $value[0] instanceof Definition || \is_string($value[0]))) {
            return;
        }
        if ('iterable' === $type && (\is_array($value) || 'array' === $class || is_subclass_of($class, \Traversable::class))) {
            return;
        }
        if ($type === $class) {
            return;
        }
        if ('object' === $type && !isset(self::BUILTIN_TYPES[$class])) {
            return;
        }
        if ('mixed' === $type) {
            return;
        }
        if (is_a($class, $type, true)) {
            return;
        }
        if ('false' === $type) {
            if (false === $value) {
                return;
            }
        } elseif ('true' === $type) {
            if (true === $value) {
                return;
            }
        } elseif ($reflection_type->is_builtin()) {
            $check_function = \sprintf('is_%s', $type);
            if ($check_function($value)) {
                return;
            }
        }
        throw new Invalid_Parameter_Type_Exception($this->current_id, \is_object($value) ? $class : get_debug_type($value), $parameter);
    }
    private function get_expression_language(): Expression_Language
    {
        return $this->expression_language ??= new Expression_Language(null, $this->container->get_expression_language_providers());
    }
}