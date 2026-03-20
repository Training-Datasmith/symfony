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

use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\Service_Circular_Reference_Exception;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Replaces all references to aliases with references to the actual service.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Resolve_References_To_Aliases_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    public function process(Container_Builder $container): void
    {
        parent::process($container);
        foreach ($container->get_aliases() as $id => $alias) {
            $alias_id = (string) $alias;
            $this->current_id = $id;
            if ($alias_id !== $def_id = $this->get_definition_id($alias_id, $container)) {
                $new_alias = $container->set_alias($id, $def_id)->set_public($alias->is_public());
                if ($alias->is_deprecated()) {
                    $new_alias->set_deprecated(...array_values($alias->get_deprecation('%alias_id%')));
                }
            }
        }
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if (!$value instanceof Reference) {
            return parent::process_value($value, $is_root);
        }
        $def_id = $this->get_definition_id($id = (string) $value, $this->container);
        return $def_id !== $id ? new Reference($def_id, $value->get_invalid_behavior()) : $value;
    }
    private function get_definition_id(string $id, Container_Builder $container): string
    {
        if (!$container->has_alias($id)) {
            return $id;
        }
        $alias = $container->get_alias($id);
        if ($alias->is_deprecated()) {
            $referencing_definition = $container->has_definition($this->current_id) ? $container->get_definition($this->current_id) : $container->get_alias($this->current_id);
            if (!$referencing_definition->is_deprecated()) {
                $deprecation = $alias->get_deprecation($id);
                trigger_deprecation($deprecation['package'], $deprecation['version'], rtrim((string) $deprecation['message'], '. ') . '. It is being referenced by the "%s" ' . ($container->has_definition($this->current_id) ? 'service.' : 'alias.'), $this->current_id);
            }
        }
        $seen = [];
        do {
            if (isset($seen[$id])) {
                throw new Service_Circular_Reference_Exception($id, array_merge(array_keys($seen), [$id]));
            }
            $seen[$id] = true;
            $id = (string) $container->get_alias($id);
        } while ($container->has_alias($id));
        return $id;
    }
}