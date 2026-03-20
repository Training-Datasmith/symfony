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

use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\Service_Circular_Reference_Exception;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Resolve_Decorator_Stack_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        $stacks = [];
        foreach ($container->find_tagged_service_ids('container.stack') as $id => $tags) {
            $definition = $container->get_definition($id);
            if (!$definition instanceof Child_Definition) {
                throw new InvalidArgumentException(\sprintf('Invalid service "%s": only definitions with a "parent" can have the "container.stack" tag.', $id));
            }
            if (!$stack = $definition->get_arguments()) {
                throw new InvalidArgumentException(\sprintf('Invalid service "%s": the stack of decorators is empty.', $id));
            }
            $stacks[$id] = $stack;
        }
        if (!$stacks) {
            return;
        }
        $resolved_definitions = [];
        foreach ($container->get_definitions() as $id => $definition) {
            if (!isset($stacks[$id])) {
                $resolved_definitions[$id] = $definition;
                continue;
            }
            foreach (array_reverse($this->resolve_stack($stacks, [$id]), true) as $k => $v) {
                $resolved_definitions[$k] = $v;
            }
            $alias = $container->set_alias($id, $k);
            if ($definition->get_changes()['public'] ?? false) {
                $alias->set_public($definition->is_public());
            }
            if ($definition->is_deprecated()) {
                $alias->set_deprecated(...array_values($definition->get_deprecation('%alias_id%')));
            }
        }
        $container->set_definitions($resolved_definitions);
    }
    private function resolve_stack(array $stacks, array $path): array
    {
        $definitions = [];
        $id = end($path);
        $prefix = '.' . $id . '.';
        if (!isset($stacks[$id])) {
            return [$id => new Child_Definition($id)];
        }
        if (key($path) !== $search_key = array_search($id, $path)) {
            throw new Service_Circular_Reference_Exception($id, \array_slice($path, $search_key));
        }
        foreach ($stacks[$id] as $k => $definition) {
            if ($definition instanceof Child_Definition && isset($stacks[$definition->get_parent()])) {
                $path[] = $definition->get_parent();
                $definition = unserialize(serialize($definition));
                // deep clone
            } elseif ($definition instanceof Definition) {
                $definitions[$decorated_id = $prefix . $k] = $definition;
                continue;
            } elseif ($definition instanceof Reference || $definition instanceof Alias) {
                $path[] = (string) $definition;
            } else {
                throw new InvalidArgumentException(\sprintf('Invalid service "%s": unexpected value of type "%s" found in the stack of decorators.', $id, get_debug_type($definition)));
            }
            $p = $prefix . $k;
            foreach ($this->resolve_stack($stacks, $path) as $k => $v) {
                $definitions[$decorated_id = $p . $k] = $definition instanceof Child_Definition ? $definition->set_parent($k) : new Child_Definition($k);
                $definition = null;
            }
            array_pop($path);
        }
        if (1 === \count($path)) {
            foreach ($definitions as $definition) {
                $definition->set_public(false)->set_tags([])->set_decorated_service($decorated_id);
            }
            $definition->set_decorated_service(null);
        }
        return $definitions;
    }
}