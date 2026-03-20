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

use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
/**
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class Attribute_Autoconfiguration_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private array $class_attribute_configurators = [];
    private array $method_attribute_configurators = [];
    private array $property_attribute_configurators = [];
    private array $parameter_attribute_configurators = [];
    public function process(Container_Builder $container): void
    {
        if (!$container->get_attribute_autoconfigurators()) {
            return;
        }
        foreach ($container->get_attribute_autoconfigurators() as $attribute_name => $callables) {
            foreach ($callables as $callable) {
                $callable_reflector = new \ReflectionFunction($callable(...));
                if ($callable_reflector->get_number_of_parameters() <= 2) {
                    $this->class_attribute_configurators[$attribute_name][] = $callable;
                    continue;
                }
                $reflector_parameter = $callable_reflector->get_parameters()[2];
                $parameter_type = $reflector_parameter->get_type();
                $types = [];
                if ($parameter_type instanceof \ReflectionUnionType) {
                    foreach ($parameter_type->get_types() as $type) {
                        $types[] = $type->get_name();
                    }
                } elseif ($parameter_type instanceof \ReflectionNamedType) {
                    $types[] = $parameter_type->get_name();
                } else {
                    throw new LogicException(\sprintf('Argument "$%s" of attribute autoconfigurator should have a type, use one or more of "\ReflectionClass|\ReflectionMethod|\ReflectionProperty|\ReflectionParameter|\Reflector" in "%s" on line "%d".', $reflector_parameter->get_name(), $callable_reflector->get_file_name(), $callable_reflector->get_start_line()));
                }
                foreach (['Class', 'Method', 'Property', 'Parameter'] as $symbol) {
                    if (['Reflector'] === $types || \in_array('Reflection' . $symbol, $types, true)) {
                        $this->{lcfirst($symbol) . 'AttributeConfigurators'}[$attribute_name][] = $callable;
                    }
                }
            }
        }
        $this->container = $container;
        foreach ($container->get_definitions() as $id => $definition) {
            $this->current_id = $id;
            $this->process_value($definition, true);
        }
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if (!$value instanceof Definition || !$value->is_autoconfigured() || $value->is_abstract() && !$value->has_tag('container.excluded') || $value->has_tag('container.ignore_attributes') || !$class_reflector = $this->container->get_reflection_class($value->get_class(), false)) {
            return parent::process_value($value, $is_root);
        }
        $instanceof = $value->get_instanceof_conditionals();
        $conditionals = $instanceof[$class_reflector->get_name()] ?? new Child_Definition('');
        $this->call_configurators($this->class_attribute_configurators, $conditionals, $class_reflector);
        if ($this->parameter_attribute_configurators) {
            try {
                $constructor_reflector = $this->get_constructor($value, false);
            } catch (RuntimeException) {
                $constructor_reflector = null;
            }
            if ($constructor_reflector) {
                foreach ($constructor_reflector->get_parameters() as $parameter_reflector) {
                    $this->call_configurators($this->parameter_attribute_configurators, $conditionals, $parameter_reflector);
                }
            }
        }
        if ($this->method_attribute_configurators || $this->parameter_attribute_configurators) {
            foreach ($class_reflector->get_methods(\ReflectionMethod::IS_PUBLIC) as $method_reflector) {
                if ($method_reflector->is_constructor()) {
                    continue;
                }
                if ($method_reflector->is_destructor()) {
                    continue;
                }
                $this->call_configurators($this->method_attribute_configurators, $conditionals, $method_reflector);
                foreach ($method_reflector->get_parameters() as $parameter_reflector) {
                    $this->call_configurators($this->parameter_attribute_configurators, $conditionals, $parameter_reflector);
                }
            }
        }
        if ($this->property_attribute_configurators) {
            foreach ($class_reflector->get_properties(\ReflectionProperty::IS_PUBLIC) as $property_reflector) {
                if ($property_reflector->is_static()) {
                    continue;
                }
                $this->call_configurators($this->property_attribute_configurators, $conditionals, $property_reflector);
            }
        }
        if (!isset($instanceof[$class_reflector->get_name()]) && new Child_Definition('') != $conditionals) {
            $instanceof[$class_reflector->get_name()] = $conditionals;
            $value->set_instanceof_conditionals($instanceof);
        }
        return parent::process_value($value, $is_root);
    }
    /**
     * Call all the configurators for the given attribute.
     *
     * @param array<class-string, callable[]> $configurators
     */
    private function call_configurators(array &$configurators, Child_Definition $conditionals, \ReflectionClass|\ReflectionMethod|\ReflectionParameter|\ReflectionProperty $reflector): void
    {
        if (!$configurators) {
            return;
        }
        foreach ($reflector->get_attributes() as $attribute) {
            foreach ($this->find_configurators($configurators, $attribute->get_name()) as $configurator) {
                $configurator($conditionals, $attribute->new_instance(), $reflector);
            }
        }
    }
    /**
     * Find the first configurator for the given attribute name, looking up the class hierarchy.
     */
    private function find_configurators(array &$configurators, string $attribute_name): array
    {
        if (\array_key_exists($attribute_name, $configurators)) {
            return $configurators[$attribute_name];
        }
        if (class_exists($attribute_name) && $parent = get_parent_class($attribute_name)) {
            return $configurators[$attribute_name] = $this->find_configurators($configurators, $parent);
        }
        return $configurators[$attribute_name] = [];
    }
}