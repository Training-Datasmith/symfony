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
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Removes unused service definitions from the container.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Remove_Unused_Definitions_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private array $connected_ids = [];
    /**
     * Processes the ContainerBuilder to remove unused definitions.
     */
    public function process(Container_Builder $container): void
    {
        try {
            $this->enable_expression_processing();
            $this->container = $container;
            $connected_ids = [];
            $aliases = $container->get_aliases();
            foreach ($aliases as $id => $alias) {
                if ($alias->is_public()) {
                    $this->connected_ids[] = (string) $aliases[$id];
                }
            }
            foreach ($container->get_definitions() as $id => $definition) {
                if ($definition->is_public()) {
                    $connected_ids[$id] = true;
                    $this->process_value($definition);
                }
            }
            while ($this->connected_ids) {
                $ids = $this->connected_ids;
                $this->connected_ids = [];
                foreach ($ids as $id) {
                    if (!isset($connected_ids[$id]) && $container->has_definition($id)) {
                        $connected_ids[$id] = true;
                        $this->process_value($container->get_definition($id));
                    }
                }
            }
            foreach ($container->get_definitions() as $id => $definition) {
                if (!isset($connected_ids[$id])) {
                    $container->remove_definition($id);
                    $container->resolve_env_placeholders(!$definition->has_errors() ? serialize($definition) : $definition);
                    $container->log($this, \sprintf('Removed service "%s"; reason: unused.', $id));
                }
            }
        } finally {
            $this->container = null;
            $this->connected_ids = [];
        }
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if (!$value instanceof Reference) {
            return parent::process_value($value, $is_root);
        }
        if (Container_Builder::IGNORE_ON_UNINITIALIZED_REFERENCE !== $value->get_invalid_behavior()) {
            $this->connected_ids[] = (string) $value;
        }
        return $value;
    }
}