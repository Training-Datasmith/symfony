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

use Symfony\Component\Dependency_Injection\Attribute\Autowire_Inline;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Var_Exporter\Proxy_Helper;
/**
 * Inspects existing autowired services for {@see AutowireInline} attributes and registers the definitions for reuse.
 *
 * @author Ismail Özgün Turan <oezguen.turan@dadadev.com>
 */
class Resolve_Autowire_Inline_Attributes_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private int $counter;
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        $value = parent::process_value($value, $is_root);
        if (!$value instanceof Definition || !$value->is_autowired() || !$value->get_class() || $value->has_tag('container.ignore_attributes')) {
            return $value;
        }
        if ($is_root) {
            $this->counter = 0;
        }
        $is_child_definition = $value instanceof Child_Definition;
        try {
            $constructor = $this->get_constructor($value, false);
        } catch (RuntimeException) {
            return $value;
        }
        if ($constructor) {
            $arguments = $this->register_autowire_inline_attributes($constructor, $value->get_arguments(), $is_child_definition);
            if ($arguments !== $value->get_arguments()) {
                $value->set_arguments($arguments);
            }
        }
        $method_calls = $value->get_method_calls();
        foreach ($method_calls as $i => $call) {
            [$method, $arguments] = $call;
            try {
                $method = $this->get_reflection_method($value, $method);
            } catch (RuntimeException) {
                continue;
            }
            $arguments = $this->register_autowire_inline_attributes($method, $arguments, $is_child_definition);
            if ($arguments !== $call[1]) {
                $method_calls[$i][1] = $arguments;
            }
        }
        if ($method_calls !== $value->get_method_calls()) {
            $value->set_method_calls($method_calls);
        }
        return $value;
    }
    private function register_autowire_inline_attributes(\Reflection_Function_Abstract $method, array $arguments, bool $is_child_definition): array
    {
        $parameters = $method->get_parameters();
        if ($method->is_variadic()) {
            array_pop($parameters);
        }
        $param_resolver_container = new Container_Builder($this->container->get_parameter_bag());
        foreach ($parameters as $index => $parameter) {
            if ($is_child_definition) {
                $index = 'index_' . $index;
            }
            if (\array_key_exists('$' . $parameter->name, $arguments) || \array_key_exists($index, $arguments) && '' !== $arguments[$index]) {
                $attribute = \array_key_exists('$' . $parameter->name, $arguments) ? $arguments['$' . $parameter->name] : $arguments[$index];
                if (!$attribute instanceof Autowire_Inline) {
                    continue;
                }
            } elseif (!$attribute = $parameter->get_attributes(Autowire_Inline::class, \Reflection_Attribute::IS_INSTANCEOF)[0] ?? null) {
                continue;
            } else {
                $attribute = $attribute->new_instance();
            }
            $type = Proxy_Helper::export_type($parameter, true);
            if (!$type && isset($arguments[$index])) {
                continue;
            }
            $definition = $attribute->build_definition($attribute->value, $type, $parameter);
            $param_resolver_container->set_definition('.autowire_inline', $definition);
            (new Resolve_Parameter_Place_Holders_Pass(false, false))->process($param_resolver_container);
            $id = '.autowire_inline.' . $this->current_id . '.' . ++$this->counter;
            $this->container->set_definition($id, $definition);
            $arguments[$is_child_definition ? '$' . $parameter->name : $index] = new Reference($id);
            if ($definition->is_autowired()) {
                $this->process_value($definition);
            }
        }
        return $arguments;
    }
}