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

use Symfony\Component\Dependency_Injection\Argument\Abstract_Argument;
use Symfony\Component\Dependency_Injection\Argument\Bound_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
use Symfony\Component\Dependency_Injection\Attribute\Autowire;
use Symfony\Component\Dependency_Injection\Attribute\Target;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Typed_Reference;
use Symfony\Component\Var_Exporter\Proxy_Helper;
/**
 * @author Guilhem Niot <guilhem.niot@gmail.com>
 */
class Resolve_Bindings_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private array $used_bindings = [];
    private array $unused_bindings = [];
    private array $error_messages = [];
    public function process(Container_Builder $container): void
    {
        $this->used_bindings = $container->get_removed_binding_ids();
        try {
            parent::process($container);
            foreach ($this->unused_bindings as [$key, $service_id, $binding_type, $file]) {
                $argument_type = $argument_name = $message = null;
                if (str_contains((string) $key, ' ')) {
                    [$argument_type, $argument_name] = explode(' ', (string) $key, 2);
                } elseif ('$' === $key[0]) {
                    $argument_name = $key;
                } else {
                    $argument_type = $key;
                }
                if ($argument_type) {
                    $message .= \sprintf('of type "%s" ', $argument_type);
                }
                if ($argument_name) {
                    $message .= \sprintf('named "%s" ', $argument_name);
                }
                if (Bound_Argument::DEFAULTS_BINDING === $binding_type) {
                    $message .= 'under "_defaults"';
                } elseif (Bound_Argument::INSTANCEOF_BINDING === $binding_type) {
                    $message .= 'under "_instanceof"';
                } else {
                    $message .= \sprintf('for service "%s"', $service_id);
                }
                if ($file) {
                    $message .= \sprintf(' in file "%s"', $file);
                }
                $message = \sprintf('A binding is configured for an argument %s, but no corresponding argument has been found. It may be unused and should be removed, or it may have a typo.', $message);
                if ($this->error_messages) {
                    $message .= \sprintf("\nCould be related to%s:", 1 < \count($this->error_messages) ? ' one of' : '');
                }
                foreach ($this->error_messages as $m) {
                    $message .= "\n - " . $m;
                }
                throw new InvalidArgumentException($message);
            }
        } finally {
            $this->used_bindings = [];
            $this->unused_bindings = [];
            $this->error_messages = [];
        }
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Typed_Reference && $value->get_type() === (string) $value) {
            // Already checked
            $bindings = $this->container->get_definition($this->current_id)->get_bindings();
            $name = $value->get_name();
            if (isset($name, $bindings[$name = $value . ' $' . $name])) {
                return $this->get_binding_value($bindings[$name]);
            }
            if (isset($bindings[$value->get_type()])) {
                return $this->get_binding_value($bindings[$value->get_type()]);
            }
            return parent::process_value($value, $is_root);
        }
        if (!$value instanceof Definition || !$bindings = $value->get_bindings()) {
            return parent::process_value($value, $is_root);
        }
        $binding_names = [];
        foreach ($bindings as $key => $binding) {
            [$binding_value, $binding_id, $used, $binding_type, $file] = $binding->get_values();
            if ($used) {
                $this->used_bindings[$binding_id ?? ''] = true;
                unset($this->unused_bindings[$binding_id ?? '']);
            } elseif (!isset($this->used_bindings[$binding_id ?? ''])) {
                $this->unused_bindings[$binding_id ?? ''] = [$key, $this->current_id, $binding_type, $file];
            }
            if (preg_match('/^(?:(?:array|bool|float|int|string|iterable|([^ $]++)) )\$/', (string) $key, $m)) {
                $binding_names[substr((string) $key, \strlen($m[0]))] = $binding;
            }
            if (!isset($m[1])) {
                continue;
            }
            if (is_subclass_of($m[1], \Unit_Enum::class)) {
                $binding_names[substr((string) $key, \strlen($m[0]))] = $binding;
                continue;
            }
            if (null !== $binding_value && !$binding_value instanceof Reference && !$binding_value instanceof Definition && !$binding_value instanceof Tagged_Iterator_Argument && !$binding_value instanceof Service_Locator_Argument) {
                throw new InvalidArgumentException(\sprintf('Invalid value for binding key "%s" for service "%s": expected "%s", "%s", "%s", "%s" or null, "%s" given.', $key, $this->current_id, Reference::class, Definition::class, Tagged_Iterator_Argument::class, Service_Locator_Argument::class, get_debug_type($binding_value)));
            }
        }
        if ($value->is_abstract()) {
            return parent::process_value($value, $is_root);
        }
        $calls = $value->get_method_calls();
        try {
            if ($constructor = $this->get_constructor($value, false)) {
                $calls[] = [$constructor, $value->get_arguments()];
            }
        } catch (RuntimeException $e) {
            $this->error_messages[] = $e->get_message();
            $this->container->get_definition($this->current_id)->add_error($e->get_message());
            return parent::process_value($value, $is_root);
        }
        foreach ($calls as $i => $call) {
            [$method, $arguments] = $call;
            if ($method instanceof \Reflection_Function_Abstract) {
                $reflection_method = $method;
            } else {
                try {
                    $reflection_method = $this->get_reflection_method($value, $method);
                } catch (RuntimeException $e) {
                    if ($value->get_factory()) {
                        continue;
                    }
                    throw $e;
                }
            }
            $names = [];
            foreach ($reflection_method->get_parameters() as $key => $parameter) {
                $names[$key] = $parameter->name;
                if (\array_key_exists($key, $arguments) && '' !== $arguments[$key] && !$arguments[$key] instanceof Abstract_Argument) {
                    continue;
                }
                if (\array_key_exists($parameter->name, $arguments) && '' !== $arguments[$parameter->name] && !$arguments[$parameter->name] instanceof Abstract_Argument) {
                    continue;
                }
                if ($value->is_autowired() && !$value->has_tag('container.ignore_attributes') && $parameter->get_attributes(Autowire::class, \Reflection_Attribute::IS_INSTANCEOF)) {
                    continue;
                }
                $type_hint = ltrim(Proxy_Helper::export_type($parameter) ?? '', '?');
                $name = Target::parse_name($parameter, parsedName: $parsed_name);
                if ($type_hint && (\array_key_exists($k = preg_replace('/(^|[(|&])\\\\/', '\1', $type_hint) . ' $' . $name, $bindings) || \array_key_exists($k = preg_replace('/(^|[(|&])\\\\/', '\1', $type_hint) . ' $' . $parsed_name, $bindings) || $name !== $parameter->name && \array_key_exists($k = preg_replace('/(^|[(|&])\\\\/', '\1', $type_hint) . ' $' . $parameter->name, $bindings))) {
                    $arguments[$key] = $this->get_binding_value($bindings[$k]);
                    continue;
                }
                if (\array_key_exists($k = '$' . $name, $bindings) || \array_key_exists($k = '$' . $parsed_name, $bindings) || $name !== $parameter->name && \array_key_exists($k = '$' . $parameter->name, $bindings)) {
                    $arguments[$key] = $this->get_binding_value($bindings[$k]);
                    continue;
                }
                if ($type_hint && '\\' === $type_hint[0] && isset($bindings[$type_hint = substr($type_hint, 1)])) {
                    $arguments[$key] = $this->get_binding_value($bindings[$type_hint]);
                    continue;
                }
                if (null !== $binding = $binding_names[$name] ?? $binding_names[$parsed_name] ?? $binding_names[$parameter->name] ?? null) {
                    $binding_key = array_search($binding, $bindings, true);
                    $argument_type = substr($binding_key, 0, strpos($binding_key, ' '));
                    $this->error_messages[] = \sprintf('Did you forget to add the type "%s" to argument "$%s" of method "%s::%s()"?', $argument_type, $parameter->name, $reflection_method->class, $reflection_method->name);
                }
            }
            foreach ($names as $key => $name) {
                if (\array_key_exists($name, $arguments) && (0 === $key || \array_key_exists($key - 1, $arguments))) {
                    if (!\array_key_exists($key, $arguments)) {
                        $arguments[$key] = $arguments[$name];
                    }
                    unset($arguments[$name]);
                }
            }
            if ($arguments !== $call[1]) {
                ksort($arguments, \SORT_NATURAL);
                $calls[$i][1] = $arguments;
            }
        }
        if ($constructor) {
            [, $arguments] = array_pop($calls);
            if ($arguments !== $value->get_arguments()) {
                $value->set_arguments($arguments);
            }
        }
        if ($calls !== $value->get_method_calls()) {
            $value->set_method_calls($calls);
        }
        return parent::process_value($value, $is_root);
    }
    private function get_binding_value(Bound_Argument $binding): mixed
    {
        [$binding_value, $binding_id] = $binding->get_values();
        $this->used_bindings[$binding_id ?? ''] = true;
        unset($this->unused_bindings[$binding_id ?? '']);
        return $binding_value;
    }
}