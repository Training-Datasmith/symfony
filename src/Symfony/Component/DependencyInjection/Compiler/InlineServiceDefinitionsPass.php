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

use Symfony\Component\Dependency_Injection\Argument\Argument_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\Service_Circular_Reference_Exception;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Inline service definitions where this is possible.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Inline_Service_Definitions_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private array $cloning_ids = [];
    private array $connected_ids = [];
    private array $not_inlined_ids = [];
    private array $inlined_ids = [];
    private array $not_inlinable_ids = [];
    private array $autowire_inline = [];
    private ?Service_Reference_Graph $graph = null;
    public function __construct(private readonly ?Analyze_Service_References_Pass $analyzing_pass = null)
    {
    }
    public function process(Container_Builder $container): void
    {
        $this->container = $container;
        if ($this->analyzing_pass) {
            $analyzed_container = new Container_Builder();
            $analyzed_container->set_aliases($container->get_aliases());
            $analyzed_container->set_definitions($container->get_definitions());
            foreach ($container->get_expression_language_providers() as $provider) {
                $analyzed_container->add_expression_language_provider($provider);
            }
        } else {
            $analyzed_container = $container;
        }
        try {
            $not_inlinable_ids = [];
            $remaining_inlined_ids = [];
            $this->connected_ids = $this->not_inlined_ids = $container->get_definitions();
            do {
                if ($this->analyzing_pass) {
                    $analyzed_container->set_definitions(array_intersect_key($analyzed_container->get_definitions(), $this->connected_ids));
                    $this->analyzing_pass->process($analyzed_container);
                }
                $this->graph = $analyzed_container->get_compiler()->get_service_reference_graph();
                $not_inlined_ids = $this->not_inlined_ids;
                $not_inlinable_ids += $this->not_inlinable_ids;
                $this->connected_ids = $this->not_inlined_ids = $this->inlined_ids = $this->not_inlinable_ids = [];
                foreach ($analyzed_container->get_definitions() as $id => $definition) {
                    if (!$this->graph->has_node($id)) {
                        continue;
                    }
                    if ($definition->is_public()) {
                        $this->connected_ids[$id] = true;
                    }
                    foreach ($this->graph->get_node($id)->get_out_edges() as $edge) {
                        if (isset($not_inlined_ids[$edge->get_source_node()->get_id()])) {
                            $this->current_id = $id;
                            $this->process_value($definition, true);
                            break;
                        }
                    }
                }
                foreach ($this->inlined_ids as $id => $is_public_or_not_shared) {
                    if ($is_public_or_not_shared) {
                        $remaining_inlined_ids[$id] = $id;
                    } else {
                        $container->remove_definition($id);
                        if (!isset($this->autowire_inline[$id])) {
                            $analyzed_container->remove_definition($id);
                        }
                    }
                }
            } while ($this->inlined_ids && $this->analyzing_pass);
            foreach ($remaining_inlined_ids as $id) {
                if (isset($not_inlinable_ids[$id])) {
                    continue;
                }
                $definition = $container->get_definition($id);
                if (!$definition->is_shared() && !$definition->is_public()) {
                    $container->remove_definition($id);
                }
            }
        } finally {
            $this->container = null;
            $this->connected_ids = $this->not_inlined_ids = $this->inlined_ids = [];
            $this->not_inlinable_ids = [];
            $this->graph = null;
        }
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Argument_Interface) {
            // References found in ArgumentInterface::getValues() are not inlineable
            return $value;
        }
        if ($value instanceof Definition && $this->cloning_ids) {
            if ($value->is_shared()) {
                return $value;
            }
            $value = clone $value;
        }
        if (!$value instanceof Reference) {
            return parent::process_value($value, $is_root);
        }
        if (!$this->container->has_definition($id = (string) $value)) {
            return $value;
        }
        $definition = $this->container->get_definition($id);
        if (isset($this->not_inlinable_ids[$id]) || !$this->is_inlineable_definition($id, $definition)) {
            if ($this->current_id !== $id) {
                $this->not_inlinable_ids[$id] = true;
            }
            return $value;
        }
        $this->container->log($this, \sprintf('Inlined service "%s" to "%s".', $id, $this->current_id));
        $this->inlined_ids[$id] = $definition->is_public() || !$definition->is_shared();
        $this->not_inlined_ids[$this->current_id ?? ''] = true;
        if ($definition->is_shared()) {
            return $definition;
        }
        if (isset($this->cloning_ids[$id])) {
            $ids = array_keys($this->cloning_ids);
            $ids[] = $id;
            throw new Service_Circular_Reference_Exception($id, \array_slice($ids, array_search($id, $ids)));
        }
        $this->cloning_ids[$id] = true;
        try {
            return $this->process_value($definition);
        } finally {
            unset($this->cloning_ids[$id]);
        }
    }
    /**
     * Checks if the definition is inlineable.
     */
    private function is_inlineable_definition(string $id, Definition $definition): bool
    {
        if (str_starts_with($id, '.autowire_inline.')) {
            $this->autowire_inline[$id] = true;
            return true;
        }
        if ($definition->has_errors() || $definition->is_deprecated() || $definition->is_lazy() || $definition->is_synthetic() || $definition->has_tag('container.do_not_inline')) {
            return false;
        }
        if (!$definition->is_shared()) {
            if (!$this->graph->has_node($id)) {
                return true;
            }
            foreach ($this->graph->get_node($id)->get_in_edges() as $edge) {
                $src_id = $edge->get_source_node()->get_id();
                $this->connected_ids[$src_id] = true;
                if ($edge->is_weak() || $edge->is_lazy()) {
                    return !$this->connected_ids[$id] = true;
                }
            }
            return true;
        }
        if ($definition->is_public() || $this->current_id === $id || !$this->graph->has_node($id)) {
            return false;
        }
        $this->connected_ids[$id] = true;
        $src_ids = [];
        $src_count = 0;
        foreach ($this->graph->get_node($id)->get_in_edges() as $edge) {
            $src_id = $edge->get_source_node()->get_id();
            $this->connected_ids[$src_id] = true;
            if ($edge->is_weak() || $edge->is_lazy()) {
                return false;
            }
            $src_ids[$src_id] = true;
            ++$src_count;
        }
        if (1 !== \count($src_ids)) {
            $this->not_inlined_ids[$id] = true;
            return false;
        }
        if ($src_count > 1 && \is_array($factory = $definition->get_factory()) && ($factory[0] instanceof Reference || $factory[0] instanceof Definition)) {
            return false;
        }
        $src_definition = $this->container->get_definition($src_id);
        return $src_definition->is_shared() && !$src_definition->is_lazy();
    }
}