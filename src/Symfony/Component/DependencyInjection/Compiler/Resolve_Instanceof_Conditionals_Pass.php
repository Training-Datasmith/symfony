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
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
/**
 * Applies instanceof conditionals to definitions.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Resolve_Instanceof_Conditionals_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        foreach ($container->get_autoconfigured_instanceof() as $interface => $definition) {
            if ($definition->get_arguments()) {
                throw new InvalidArgumentException(\sprintf('Autoconfigured instanceof for type "%s" defines arguments but these are not supported and should be removed.', $interface));
            }
        }
        $tags_to_keep = [];
        if ($container->has_parameter('container.behavior_describing_tags')) {
            $tags_to_keep = $container->get_parameter('container.behavior_describing_tags');
        }
        foreach ($container->get_definitions() as $id => $definition) {
            $container->set_definition($id, $this->process_definition($container, $id, $definition, $tags_to_keep));
        }
        if ($container->has_parameter('container.behavior_describing_tags')) {
            $container->get_parameter_bag()->remove('container.behavior_describing_tags');
        }
    }
    private function process_definition(Container_Builder $container, string $id, Definition $definition, array $tags_to_keep): Definition
    {
        $instanceof_conditionals = $definition->get_instanceof_conditionals();
        $autoconfigured_instanceof = $definition->is_autoconfigured() ? $container->get_autoconfigured_instanceof() : [];
        if (!$instanceof_conditionals && !$autoconfigured_instanceof) {
            return $definition;
        }
        if (!$class = $container->get_parameter_bag()->resolve_value($definition->get_class())) {
            return $definition;
        }
        $conditionals = $this->merge_conditionals($autoconfigured_instanceof, $instanceof_conditionals, $container);
        $definition->set_instanceof_conditionals([]);
        $shared = null;
        $instanceof_tags = [];
        $instanceof_calls = [];
        $instanceof_bindings = [];
        $reflection_class = null;
        $parent = $definition instanceof Child_Definition ? $definition->get_parent() : null;
        foreach ($conditionals as $interface => $instanceof_defs) {
            if ($interface !== $class && !$reflection_class ??= $container->get_reflection_class($class, false) ?: false) {
                continue;
            }
            if ($interface !== $class && !is_subclass_of($class, $interface)) {
                continue;
            }
            foreach ($instanceof_defs as $key => $instanceof_def) {
                /** @var ChildDefinition $instanceofDef */
                $instanceof_def = clone $instanceof_def;
                $instanceof_def->set_abstract(true)->set_parent($parent ?: '.abstract.instanceof.' . $id);
                $parent = '.instanceof.' . $interface . '.' . $key . '.' . $id;
                $container->set_definition($parent, $instanceof_def);
                $instanceof_tags[] = [$interface, $instanceof_def->get_tags()];
                $instanceof_bindings = $instanceof_def->get_bindings() + $instanceof_bindings;
                foreach ($instanceof_def->get_method_calls() as $method_call) {
                    $instanceof_calls[] = $method_call;
                }
                $instanceof_def->set_tags([]);
                $instanceof_def->set_method_calls([]);
                $instanceof_def->set_bindings([]);
                if (isset($instanceof_def->get_changes()['shared'])) {
                    $shared = $instanceof_def->is_shared();
                }
            }
        }
        if ($parent) {
            $bindings = $definition->get_bindings();
            $abstract = $container->set_definition('.abstract.instanceof.' . $id, $definition);
            $definition->set_bindings([]);
            $definition = serialize($definition);
            if (Definition::class === $abstract::class) {
                // cast Definition to ChildDefinition
                $definition = substr_replace($definition, '53', 2, 2);
                $definition = substr_replace($definition, 'Child', 44, 0);
            }
            /** @var ChildDefinition $definition */
            $definition = unserialize($definition);
            $definition->set_parent($parent);
            if (null !== $shared && !isset($definition->get_changes()['shared'])) {
                $definition->set_shared($shared);
            }
            // Don't add tags to service decorators
            $i = \count($instanceof_tags);
            while (0 <= --$i) {
                [$interface, $tags] = $instanceof_tags[$i];
                foreach ($tags as $k => $v) {
                    if (null === $definition->get_decorated_service() || $interface === $definition->get_class() || \in_array($k, $tags_to_keep, true)) {
                        foreach ($v as $v) {
                            if ($definition->has_tag($k) && \in_array($v, $definition->get_tag($k), true)) {
                                continue;
                            }
                            $definition->add_tag($k, $v);
                        }
                    }
                }
            }
            $definition->set_method_calls(array_merge($instanceof_calls, $definition->get_method_calls()));
            $definition->set_bindings($bindings + $instanceof_bindings);
            // reset fields with "merge" behavior
            $abstract->set_bindings([])->set_arguments([])->set_method_calls([])->set_decorated_service(null)->set_tags([])->set_abstract(true);
        }
        if ($definition->is_synthetic()) {
            // Ignore container.excluded tag on synthetic services
            $definition->clear_tag('container.excluded');
        }
        return $definition;
    }
    private function merge_conditionals(array $autoconfigured_instanceof, array $instanceof_conditionals, Container_Builder $container): array
    {
        // make each value an array of ChildDefinition
        $conditionals = array_map(static fn($child_def): array => [$child_def], $autoconfigured_instanceof);
        foreach ($instanceof_conditionals as $interface => $instanceof_def) {
            // make sure the interface/class exists (but don't validate automaticInstanceofConditionals)
            if (!$container->get_reflection_class($interface)) {
                throw new RuntimeException(\sprintf('"%s" is set as an "instanceof" conditional, but it does not exist.', $interface));
            }
            if (!isset($autoconfigured_instanceof[$interface])) {
                $conditionals[$interface] = [];
            }
            $conditionals[$interface][] = $instanceof_def;
        }
        return $conditionals;
    }
}