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

use Symfony\Component\Config\Resource\Class_Existence_Resource;
use Symfony\Component\Dependency_Injection\Attribute\Autowire;
use Symfony\Component\Dependency_Injection\Attribute\Autowire_Decorated;
use Symfony\Component\Dependency_Injection\Attribute\Autowire_Inline;
use Symfony\Component\Dependency_Injection\Attribute\Lazy;
use Symfony\Component\Dependency_Injection\Attribute\Target;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\Autowiring_Failed_Exception;
use Symfony\Component\Dependency_Injection\Exception\Parameter_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Typed_Reference;
use Symfony\Component\Var_Exporter\Proxy_Helper;
/**
 * Inspects existing service definitions and wires the autowired ones using the type hints of their classes.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Autowire_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private array $types;
    private array $ambiguous_service_types;
    private array $autowiring_aliases;
    private ?string $last_failure = null;
    private ?string $decorated_class = null;
    private ?string $decorated_id = null;
    private object $default_argument;
    private ?\Closure $restore_previous_value = null;
    private ?self $types_clone = null;
    public function __construct(private bool $throw_on_autowiring_exception = true)
    {
        $this->default_argument = new class
        {
            public $value;
            public $names;
            public $bag;
            public function with_value(\ReflectionParameter $parameter): self
            {
                $clone = clone $this;
                $clone->value = $this->bag->escape_value($parameter->get_default_value());
                return $clone;
            }
        };
    }
    public function process(Container_Builder $container): void
    {
        $this->default_argument->bag = $container->get_parameter_bag();
        try {
            $this->types_clone = clone $this;
            parent::process($container);
        } finally {
            $this->decorated_class = null;
            $this->decorated_id = null;
            $this->default_argument->bag = null;
            $this->default_argument->names = null;
            $this->restore_previous_value = null;
            $this->types_clone = null;
        }
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Autowire) {
            return $this->process_value($this->container->get_parameter_bag()->resolve_value($value->value));
        }
        if ($value instanceof Autowire_Decorated) {
            $definition = $this->container->get_definition($this->current_id);
            return new Reference($definition->inner_service_id ?? $this->current_id . '.inner', $definition->decoration_on_invalid ?? Container_Interface::NULL_ON_INVALID_REFERENCE);
        }
        try {
            return $this->do_process_value($value, $is_root);
        } catch (Autowiring_Failed_Exception $e) {
            if ($this->throw_on_autowiring_exception) {
                throw $e;
            }
            $this->container->get_definition($this->current_id)->add_error($e->get_message_callback() ?? $e->get_message());
            return parent::process_value($value, $is_root);
        }
    }
    private function do_process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Typed_Reference) {
            foreach ($value->get_attributes() as $attribute) {
                if ($attribute === $v = $this->process_value($attribute)) {
                    continue;
                }
                if (!$attribute instanceof Autowire || !$v instanceof Reference) {
                    return $v;
                }
                $invalid_behavior = Container_Builder::EXCEPTION_ON_INVALID_REFERENCE !== $v->get_invalid_behavior() ? $v->get_invalid_behavior() : $value->get_invalid_behavior();
                $value = $v instanceof Typed_Reference ? new Typed_Reference($v, $v->get_type(), $invalid_behavior, $v->get_name() ?? $value->get_name(), array_merge($v->get_attributes(), $value->get_attributes())) : new Typed_Reference($v, $value->get_type(), $invalid_behavior, $value->get_name(), $value->get_attributes());
                break;
            }
            if ($ref = $this->get_autowired_reference($value, true)) {
                return $ref;
            }
            if (Container_Builder::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE === $value->get_invalid_behavior()) {
                $message = $this->create_type_not_found_message_callback($value, 'it');
                // since the error message varies by referenced id and $this->currentId, so should the id of the dummy errored definition
                $this->container->register($id = \sprintf('.errored.%s.%s', $this->current_id, (string) $value), $value->get_type())->add_error($message);
                return new Typed_Reference($id, $value->get_type(), $value->get_invalid_behavior(), $value->get_name());
            }
        }
        $value = parent::process_value($value, $is_root);
        if (!$value instanceof Definition || !$value->is_autowired() || $value->is_abstract() || !$value->get_class()) {
            return $value;
        }
        if (!$reflection_class = $this->container->get_reflection_class($value->get_class(), false)) {
            $this->container->log($this, \sprintf('Skipping service "%s": Class or interface "%s" cannot be loaded.', $this->current_id, $value->get_class()));
            return $value;
        }
        $method_calls = $value->get_method_calls();
        try {
            $constructor = $this->get_constructor($value, false);
        } catch (RuntimeException $e) {
            throw new Autowiring_Failed_Exception($this->current_id, $e->get_message(), 0, $e);
        }
        if ($constructor) {
            array_unshift($method_calls, [$constructor, $value->get_arguments()]);
        }
        $check_attributes = !$value->has_tag('container.ignore_attributes');
        $method_calls = $this->autowire_calls($method_calls, $reflection_class, $is_root, $check_attributes);
        if ($constructor) {
            [, $arguments] = array_shift($method_calls);
            if ($arguments !== $value->get_arguments()) {
                $value->set_arguments($arguments);
            }
        }
        if ($method_calls !== $value->get_method_calls()) {
            $value->set_method_calls($method_calls);
        }
        return $value;
    }
    private function autowire_calls(array $method_calls, \ReflectionClass $reflection_class, bool $is_root, bool $check_attributes): array
    {
        if ($is_root) {
            $this->decorated_id = null;
            $this->decorated_class = null;
            $this->restore_previous_value = null;
            if (($definition = $this->container->get_definition($this->current_id)) && null !== ($this->decorated_id = $definition->inner_service_id) && $this->container->has($this->decorated_id)) {
                $this->decorated_class = $this->container->find_definition($this->decorated_id)->get_class();
            }
        }
        $patched_indexes = [];
        foreach ($method_calls as $i => $call) {
            [$method, $arguments] = $call;
            if ($method instanceof \Reflection_Function_Abstract) {
                $reflection_method = $method;
            } else {
                $definition = new Definition($reflection_class->name);
                try {
                    $reflection_method = $this->get_reflection_method($definition, $method);
                } catch (RuntimeException $e) {
                    if ($definition->get_factory()) {
                        continue;
                    }
                    throw $e;
                }
            }
            $arguments = $this->autowire_method($reflection_method, $arguments, $check_attributes);
            if ($arguments !== $call[1]) {
                $method_calls[$i][1] = $arguments;
                $patched_indexes[] = $i;
            }
        }
        // use named arguments to skip complex default values
        foreach ($patched_indexes as $i) {
            $named_arguments = null;
            $arguments = $method_calls[$i][1];
            foreach ($arguments as $j => $value) {
                if ($named_arguments && !$value instanceof $this->default_argument) {
                    unset($arguments[$j]);
                    $arguments[$named_arguments[$j]] = $value;
                }
                if (!$value instanceof $this->default_argument) {
                    continue;
                }
                if (\is_array($value->value) ? $value->value : \is_object($value->value)) {
                    unset($arguments[$j]);
                    $named_arguments = $value->names;
                }
                if ($named_arguments) {
                    unset($arguments[$j]);
                } else {
                    $arguments[$j] = $value->value;
                }
            }
            $method_calls[$i][1] = $arguments;
        }
        return $method_calls;
    }
    /**
     * Autowires the constructor or a method.
     *
     * @throws AutowiringFailedException
     */
    private function autowire_method(\Reflection_Function_Abstract $reflection_method, array $arguments, bool $check_attributes): array
    {
        $class = $reflection_method instanceof \ReflectionMethod ? $reflection_method->class : $this->current_id;
        $method = $reflection_method->name;
        $parameters = $reflection_method->get_parameters();
        if ($reflection_method->is_variadic()) {
            array_pop($parameters);
        }
        $default_argument = clone $this->default_argument;
        $default_argument->names = new \ArrayObject();
        foreach ($parameters as $index => $parameter) {
            $default_argument->names[$index] = $parameter->name;
            if (\array_key_exists($parameter->name, $arguments)) {
                $arguments[$index] = $arguments[$parameter->name];
                unset($arguments[$parameter->name]);
            }
            if (\array_key_exists($index, $arguments) && '' !== $arguments[$index]) {
                continue;
            }
            $type = Proxy_Helper::export_type($parameter, true);
            $target = null;
            $name = Target::parse_name($parameter, $target);
            $target = $target ? [$target] : [];
            $current_id = $this->current_id;
            $get_value = function () use ($type, $parameter, $class, $method, $name, $target, $default_argument, $current_id) {
                if (!$target && null !== ($named_alias = $this->get_combined_alias($type, $name)) && $this->can_definition_be_autowired($named_alias)) {
                    trigger_deprecation('symfony/dependency-injection', '8.1', 'Relying solely on the name of parameter "$%s" of "%s()" to match a named autowiring alias is deprecated; use the "#[Target]" attribute.', $parameter->name, $class !== $current_id ? $class . '::' . $method : $method);
                }
                if (!$value = $this->get_autowired_reference($ref = new Typed_Reference($type, $type, Container_Builder::EXCEPTION_ON_INVALID_REFERENCE, $name, $target), false)) {
                    $failure_message = $this->create_type_not_found_message_callback($ref, \sprintf('argument "$%s" of method "%s()"', $parameter->name, $class !== $current_id ? $class . '::' . $method : $method));
                    if ($parameter->is_default_value_available()) {
                        $value = $default_argument->with_value($parameter);
                    } elseif (!$parameter->allows_null()) {
                        throw new Autowiring_Failed_Exception($current_id, $failure_message);
                    }
                }
                return $value;
            };
            if ($check_attributes) {
                $attributes = array_merge($parameter->get_attributes(Autowire::class, \Reflection_Attribute::IS_INSTANCEOF), $parameter->get_attributes(Lazy::class, \Reflection_Attribute::IS_INSTANCEOF));
                if (1 < \count($attributes)) {
                    throw new Autowiring_Failed_Exception($this->current_id, 'Using both attributes #[Lazy] and #[Autowire] on an argument is not allowed; use the "lazy" parameter of #[Autowire] instead.');
                }
                foreach ($attributes as $attribute) {
                    $attribute = $attribute->new_instance();
                    $value = $attribute instanceof Autowire ? $attribute->value : null;
                    if (\is_string($value) && str_starts_with($value, '%env(') && str_ends_with($value, ')%')) {
                        if ($parameter->get_type() instanceof \ReflectionNamedType && 'bool' === $parameter->get_type()->get_name() && !str_starts_with($value, '%env(bool:')) {
                            $attribute = new Autowire(substr_replace($value, 'bool:', 5, 0));
                        }
                        if ($parameter->is_default_value_available() && $parameter->allows_null() && null === $parameter->get_default_value() && !preg_match('/(^|:)default:/', $value)) {
                            $attribute = new Autowire(substr_replace($value, 'default::', 5, 0));
                        }
                    }
                    $invalid_behavior = $parameter->allows_null() ? Container_Interface::NULL_ON_INVALID_REFERENCE : Container_Builder::EXCEPTION_ON_INVALID_REFERENCE;
                    try {
                        $value = $this->process_value(new Typed_Reference($type ?: '?', $type ?: 'mixed', $invalid_behavior, $name, [$attribute, ...$target]));
                    } catch (Parameter_Not_Found_Exception $e) {
                        if (!$parameter->is_default_value_available()) {
                            throw new Autowiring_Failed_Exception($this->current_id, $e->get_message(), 0, $e);
                        }
                        $arguments[$index] = clone $default_argument;
                        $arguments[$index]->value = $parameter->get_default_value();
                        continue 2;
                    }
                    if ($attribute instanceof Autowire_Inline) {
                        $value = $attribute->build_definition($value, $type, $parameter);
                        $value = $this->do_process_value($value);
                    } elseif ($lazy = $attribute->lazy) {
                        $value ??= $get_value();
                        if (!\is_array($lazy)) {
                            if (str_contains((string) $type, '|')) {
                                throw new Autowiring_Failed_Exception($this->current_id, \sprintf('Cannot use #[Autowire] with option "lazy: true" on union types for service "%s"; set the option to the interface(s) that should be proxied instead.', $this->current_id));
                            }
                            $lazy = str_contains((string) $type, '&') ? explode('&', (string) $type) : [];
                        }
                        $proxy_type = $lazy ? $type : $this->resolve_proxy_type($type, $value);
                        $definition = (new Definition($proxy_type))->set_factory('current')->set_arguments([[$value]])->set_lazy(true);
                        if ($lazy) {
                            if (!$this->container->get_reflection_class($proxy_type, false)) {
                                $definition->set_class('object');
                            }
                            foreach ($lazy as $v) {
                                $definition->add_tag('proxy', ['interface' => $v]);
                            }
                        }
                        if ($definition->get_class() !== (string) $value || $definition->get_tag('proxy')) {
                            $value .= '.' . $this->container->hash([$definition->get_class(), $definition->get_tag('proxy')]);
                        }
                        $this->container->set_definition($value = '.lazy.' . $value, $definition);
                        $value = new Reference($value);
                    }
                    $arguments[$index] = $value;
                    continue 2;
                }
                foreach ($parameter->get_attributes(Autowire_Decorated::class) as $attribute) {
                    $arguments[$index] = $this->process_value($attribute->new_instance());
                    continue 2;
                }
            }
            if (!$type) {
                if (isset($arguments[$index])) {
                    continue;
                }
                // no default value? Then fail
                if (!$parameter->is_default_value_available()) {
                    // For core classes, isDefaultValueAvailable() can
                    // be false when isOptional() returns true. If the
                    // argument *is* optional, allow it to be missing
                    if ($parameter->is_optional()) {
                        --$index;
                        break;
                    }
                    $type = Proxy_Helper::export_type($parameter);
                    $type = $type ? \sprintf('is type-hinted "%s"', preg_replace('/(^|[(|&])\\\\|^\?\\\\?/', '\1', $type)) : 'has no type-hint';
                    throw new Autowiring_Failed_Exception($this->current_id, \sprintf('Cannot autowire service "%s": argument "$%s" of method "%s()" %s, you should configure its value explicitly.', $this->current_id, $parameter->name, $class !== $this->current_id ? $class . '::' . $method : $method, $type));
                }
                // specifically pass the default value
                $arguments[$index] = $default_argument->with_value($parameter);
                continue;
            }
            if ($this->decorated_class && is_a($this->decorated_class, $type, true)) {
                if ($this->restore_previous_value) {
                    // The inner service is injected only if there is only 1 argument matching the type of the decorated class
                    // across all arguments of all autowired methods.
                    // If a second matching argument is found, the default behavior is restored.
                    ($this->restore_previous_value)();
                    $this->decorated_class = $this->restore_previous_value = null;
                    // Prevent further checks
                } else {
                    $arguments[$index] = new Typed_Reference($this->decorated_id, $this->decorated_class);
                    $argument_at_index =& $arguments[$index];
                    $this->restore_previous_value = static function () use (&$argument_at_index, $get_value): void {
                        $argument_at_index = $get_value();
                    };
                    continue;
                }
            }
            $arguments[$index] = $get_value();
        }
        if ($parameters && !isset($arguments[++$index])) {
            while (0 <= --$index) {
                if (!$arguments[$index] instanceof $default_argument) {
                    break;
                }
                unset($arguments[$index]);
            }
        }
        // it's possible index 1 was set, then index 0, then 2, etc
        // make sure that we re-order so they're injected as expected
        ksort($arguments, \SORT_NATURAL);
        return $arguments;
    }
    /**
     * Returns a reference to the service matching the given type, if any.
     */
    private function get_autowired_reference(Typed_Reference $reference, bool $filter_type): ?Typed_Reference
    {
        $this->last_failure = null;
        $type = $reference->get_type();
        if ($type !== (string) $reference) {
            return $reference;
        }
        if ($filter_type && false !== $m = strpbrk($type, '&|')) {
            $types = array_diff(explode($m[0], $type), ['int', 'string', 'array', 'bool', 'float', 'iterable', 'object', 'callable', 'null']);
            sort($types);
            $type = implode($m[0], $types);
        }
        $name = $target = (array_filter($reference->get_attributes(), static fn($a): bool => $a instanceof Target)[0] ?? null)?->name;
        if (null !== $name ??= $reference->get_name()) {
            if (null !== ($alias = $this->get_combined_alias($type, $name, $target)) && $this->can_definition_be_autowired($alias)) {
                return new Typed_Reference($alias, $type, $reference->get_invalid_behavior());
            }
            if ($this->container->has($name) && $this->can_definition_be_autowired($name)) {
                foreach ($this->container->get_aliases() as $id => $alias) {
                    if ($name === (string) $alias && str_starts_with($id, $type . ' $')) {
                        return new Typed_Reference($name, $type, $reference->get_invalid_behavior());
                    }
                }
            }
            if (null !== $target) {
                return null;
            }
        }
        if (null !== ($alias = $this->get_combined_alias($type)) && $this->can_definition_be_autowired($alias)) {
            return new Typed_Reference($alias, $type, $reference->get_invalid_behavior());
        }
        return null;
    }
    private function can_definition_be_autowired(string $id): bool
    {
        $definition = $this->container->find_definition($id);
        return !$definition->is_abstract() && !$definition->has_tag('container.excluded');
    }
    /**
     * Populates the list of available types.
     */
    private function populate_available_types(Container_Builder $container): void
    {
        $this->types = [];
        $this->ambiguous_service_types = [];
        $this->autowiring_aliases = [];
        foreach ($container->get_definitions() as $id => $definition) {
            $this->populate_available_type($container, $id, $definition);
        }
        $prev = null;
        foreach ($container->get_aliases() as $id => $alias) {
            $this->populate_autowiring_alias($id, $prev);
            $prev = $id;
        }
    }
    /**
     * Populates the list of available types for a given definition.
     */
    private function populate_available_type(Container_Builder $container, string $id, Definition $definition): void
    {
        // Never use abstract services
        if ($definition->is_abstract()) {
            return;
        }
        if ('' === $id || '.' === $id[0] || $definition->is_deprecated() || !$reflection_class = $container->get_reflection_class($definition->get_class(), false)) {
            return;
        }
        foreach ($reflection_class->get_interfaces() as $reflection_interface) {
            $this->set($reflection_interface->name, $id);
        }
        do {
            $this->set($reflection_class->name, $id);
        } while ($reflection_class = $reflection_class->get_parent_class());
        $this->populate_autowiring_alias($id);
    }
    /**
     * Associates a type and a service id if applicable.
     */
    private function set(string $type, string $id): void
    {
        // is this already a type/class that is known to match multiple services?
        if (isset($this->ambiguous_service_types[$type])) {
            $this->ambiguous_service_types[$type][] = $id;
            return;
        }
        // check to make sure the type doesn't match multiple services
        if (!isset($this->types[$type]) || $this->types[$type] === $id) {
            $this->types[$type] = $id;
            return;
        }
        // keep an array of all services matching this type
        if (!isset($this->ambiguous_service_types[$type])) {
            $this->ambiguous_service_types[$type] = [$this->types[$type]];
            unset($this->types[$type]);
        }
        $this->ambiguous_service_types[$type][] = $id;
    }
    private function create_type_not_found_message_callback(Typed_Reference $reference, string $label): \Closure
    {
        if (!isset($this->types_clone->container)) {
            $this->types_clone->container = new Container_Builder($this->container->get_parameter_bag());
            $this->types_clone->container->set_aliases($this->container->get_aliases());
            $this->types_clone->container->set_definitions($this->container->get_definitions());
            $this->types_clone->container->set_resource_tracking(false);
        }
        $current_id = $this->current_id;
        return (fn(): string => $this->create_type_not_found_message($reference, $label, $current_id))->bind_to($this->types_clone);
    }
    private function create_type_not_found_message(Typed_Reference $reference, string $label, string $current_id): string
    {
        $type = $reference->get_type();
        $i = null;
        $namespace = $type;
        do {
            $namespace = substr($namespace, 0, $i);
            if ($this->container->has_definition($namespace) && $tag = $this->container->get_definition($namespace)->get_tag('container.excluded')) {
                return \sprintf('Cannot autowire service "%s": %s needs an instance of "%s" but this type has been excluded %s.', $current_id, $label, $type, $tag[0]['source'] ?? 'from autowiring');
            }
        } while (false !== $i = strrpos($namespace, '\\'));
        if (!$r = $this->container->get_reflection_class($type, false)) {
            // either $type does not exist or a parent class does not exist
            try {
                if (class_exists(Class_Existence_Resource::class)) {
                    $resource = new Class_Existence_Resource($type, false);
                    // isFresh() will explode ONLY if a parent class/trait does not exist
                    $resource->is_fresh(0);
                    $parent_msg = false;
                } else {
                    $parent_msg = "couldn't be loaded. Either it was not found or it is missing a parent class or a trait";
                }
            } catch (\Reflection_Exception $e) {
                $parent_msg = \sprintf('is missing a parent class (%s)', $e->get_message());
            }
            $message = \sprintf('has type "%s" but this class %s.', $type, $parent_msg ?: 'was not found');
        } else {
            $alternatives = $this->create_type_alternatives($this->container, $reference);
            if (null !== $target = array_filter($reference->get_attributes(), static fn($a): bool => $a instanceof Target)[0] ?? null) {
                $target = null !== $target->name ? "('{$target->name}')" : '';
                $message = \sprintf('has "#[Target%s]" but no such target exists.%s', $target, $alternatives);
            } else {
                $message = $this->container->has($type) ? 'this service is abstract' : 'no such service exists';
                $message = \sprintf('references %s "%s" but %s.%s', $r->is_interface() ? 'interface' : 'class', $type, $message, $alternatives);
            }
            if ($r->is_interface() && !$alternatives) {
                $message .= ' Did you create an instantiable class that implements this interface?';
            }
        }
        $message = \sprintf('Cannot autowire service "%s": %s %s', $current_id, $label, $message);
        if (null !== $this->last_failure) {
            $message = $this->last_failure . "\n" . $message;
            $this->last_failure = null;
        }
        return $message;
    }
    private function create_type_alternatives(Container_Builder $container, Typed_Reference $reference): string
    {
        // try suggesting available aliases first
        if ($message = $this->get_aliases_suggestion_for_type($container, $type = $reference->get_type())) {
            return ' ' . $message;
        }
        if (!isset($this->ambiguous_service_types)) {
            $this->populate_available_types($container);
        }
        $services_and_aliases = $container->get_service_ids();
        $autowiring_aliases = $this->autowiring_aliases[$type] ?? [];
        unset($autowiring_aliases['']);
        if ($autowiring_aliases) {
            return \sprintf(' Did you mean to target%s "%s" instead?', 1 < \count($autowiring_aliases) ? ' one of' : '', implode('", "', $autowiring_aliases));
        }
        if (!$container->has($type) && false !== $key = array_search(strtolower($type), array_map(strtolower(...), $services_and_aliases))) {
            return \sprintf(' Did you mean "%s"?', $services_and_aliases[$key]);
        }
        if (isset($this->ambiguous_service_types[$type])) {
            $message = \sprintf('one of these existing services: "%s"', implode('", "', $this->ambiguous_service_types[$type]));
        } elseif (isset($this->types[$type])) {
            $message = \sprintf('the existing "%s" service', $this->types[$type]);
        } else {
            return '';
        }
        return \sprintf(' You should maybe alias this %s to %s.', class_exists($type, false) ? 'class' : 'interface', $message);
    }
    private function get_aliases_suggestion_for_type(Container_Builder $container, string $type): ?string
    {
        $aliases = [];
        foreach (class_parents($type) + class_implements($type) as $parent) {
            if ($container->has($parent) && $this->can_definition_be_autowired($parent)) {
                $aliases[] = $parent;
            }
        }
        if (1 < $len = \count($aliases)) {
            $message = 'Try changing the type-hint to one of its parents: ';
            for ($i = 0, --$len; $i < $len; ++$i) {
                $message .= \sprintf('%s "%s", ', class_exists($aliases[$i], false) ? 'class' : 'interface', $aliases[$i]);
            }
            return $message . \sprintf('or %s "%s".', class_exists($aliases[$i], false) ? 'class' : 'interface', $aliases[$i]);
        }
        if ($aliases) {
            return \sprintf('Try changing the type-hint to "%s" instead.', $aliases[0]);
        }
        return null;
    }
    private function populate_autowiring_alias(string $id, ?string $target = null): void
    {
        if (!preg_match('/(?(DEFINE)(?<V>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+))^((?&V)(?:\\\\(?&V))*+)(?: \$((?&V)))?$/', $id, $m)) {
            return;
        }
        $type = $m[2];
        $name = $m[3] ?? '';
        if (class_exists($type, false) || interface_exists($type, false)) {
            if (null !== $target && str_starts_with($target, '.' . $type . ' $')) {
                $name = substr($target, \strlen($type) + 3);
            }
            $this->autowiring_aliases[$type][$name] = $name;
        }
    }
    private function get_combined_alias(string $type, ?string $name = null, ?string $target = null): ?string
    {
        $prefix = $target && $name ? '.' : '';
        $suffix = $name ? ' $' . ($target ?? $name) : '';
        $parsed_name = $target ?? ($name ? (new Target($name))->get_parsed_name() : null);
        if ($this->container->has($alias = $prefix . $type . $suffix) && $this->can_definition_be_autowired($alias)) {
            return $alias;
        }
        if (str_contains($type, '&')) {
            $types = explode('&', $type);
        } elseif (str_contains($type, '|')) {
            $types = explode('|', $type);
        } else {
            return $prefix || $name !== $parsed_name && ($name = $parsed_name) ? $this->get_combined_alias($type, $name) : null;
        }
        $alias = null;
        foreach ($types as $type) {
            if (!$this->container->has_alias($prefix . $type . $suffix)) {
                return $prefix || $name !== $parsed_name && ($name = $parsed_name) ? $this->get_combined_alias($type, $name) : null;
            }
            if (null === $alias) {
                $alias = (string) $this->container->get_alias($prefix . $type . $suffix);
            } elseif ((string) $this->container->get_alias($prefix . $type . $suffix) !== $alias) {
                return $prefix || $name !== $parsed_name && ($name = $parsed_name) ? $this->get_combined_alias($type, $name) : null;
            }
        }
        return $alias;
    }
    /**
     * Resolves the class name that should be proxied for a lazy service.
     *
     * @param string $originalType The original parameter type-hint (e.g., the interface)
     * @param string $serviceId    The service ID the type-hint resolved to (e.g., the alias)
     */
    private function resolve_proxy_type(string $original_type, string $service_id): string
    {
        if (!$this->container->has($service_id)) {
            return $original_type;
        }
        $resolved_type = $this->container->find_definition($service_id)->get_class();
        $resolved_type = $this->container->get_parameter_bag()->resolve_value($resolved_type);
        if (!$resolved_type || !$this->container->get_reflection_class($resolved_type, false)) {
            return $original_type;
        }
        return $resolved_type;
    }
}