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
/**
 * Checks your services for circular references.
 *
 * References from method calls are ignored since we might be able to resolve
 * these references depending on the order in which services are called.
 *
 * Circular reference from method calls will only be detected at run-time.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Check_Circular_References_Pass implements Compiler_Pass_Interface
{
    private array $current_path;
    private array $checked_nodes;
    private array $checked_lazy_nodes;
    /**
     * Checks the ContainerBuilder object for circular references.
     */
    public function process(Container_Builder $container): void
    {
        $graph = $container->get_compiler()->get_service_reference_graph();
        $this->checked_nodes = [];
        foreach ($graph->get_nodes() as $id => $node) {
            $this->current_path = [$id];
            $this->check_out_edges($node->get_out_edges());
        }
    }
    /**
     * Checks for circular references.
     *
     * @param ServiceReferenceGraphEdge[] $edges An array of Edges
     *
     * @throws ServiceCircularReferenceException when a circular reference is found
     */
    private function check_out_edges(array $edges): void
    {
        foreach ($edges as $edge) {
            $node = $edge->get_dest_node();
            $id = $node->get_id();
            if (!empty($this->checked_nodes[$id])) {
                continue;
            }
            $is_leaf = (bool) $node->get_value();
            $is_concrete = !$edge->is_lazy() && !$edge->is_weak();
            // Skip already checked lazy services if they are still lazy. Will not gain any new information.
            if (!empty($this->checked_lazy_nodes[$id]) && (!$is_leaf || !$is_concrete)) {
                continue;
            }
            // Process concrete references, otherwise defer check circular references for lazy edges.
            if (!$is_leaf || $is_concrete) {
                $search_key = array_search($id, $this->current_path);
                $this->current_path[] = $id;
                if (false !== $search_key) {
                    throw new Service_Circular_Reference_Exception($id, \array_slice($this->current_path, $search_key));
                }
                $this->check_out_edges($node->get_out_edges());
                $this->checked_nodes[$id] = true;
                unset($this->checked_lazy_nodes[$id]);
            } else {
                $this->checked_lazy_nodes[$id] = true;
            }
            array_pop($this->current_path);
        }
    }
}