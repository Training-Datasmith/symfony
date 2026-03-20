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

use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * This is a directed graph of your services.
 *
 * This information can be used by your compiler passes instead of collecting
 * it themselves which improves performance quite a lot.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @final
 */
class Service_Reference_Graph
{
    /**
     * @var ServiceReferenceGraphNode[]
     */
    private array $nodes = [];
    public function has_node(string $id): bool
    {
        return isset($this->nodes[$id]);
    }
    /**
     * Gets a node by identifier.
     *
     * @throws InvalidArgumentException if no node matches the supplied identifier
     */
    public function get_node(string $id): Service_Reference_Graph_Node
    {
        if (!isset($this->nodes[$id])) {
            throw new InvalidArgumentException(\sprintf('There is no node with id "%s".', $id));
        }
        return $this->nodes[$id];
    }
    /**
     * Returns all nodes.
     *
     * @return ServiceReferenceGraphNode[]
     */
    public function get_nodes(): array
    {
        return $this->nodes;
    }
    /**
     * Clears all nodes.
     */
    public function clear(): void
    {
        foreach ($this->nodes as $node) {
            $node->clear();
        }
        $this->nodes = [];
    }
    /**
     * Connects 2 nodes together in the Graph.
     */
    public function connect(?string $source_id, mixed $source_value, ?string $dest_id, mixed $dest_value = null, ?Reference $reference = null, bool $lazy = false, bool $weak = false, bool $by_constructor = false, bool $by_multi_use_argument = false): void
    {
        if (null === $source_id || null === $dest_id) {
            return;
        }
        $source_node = $this->create_node($source_id, $source_value);
        $dest_node = $this->create_node($dest_id, $dest_value);
        $edge = new Service_Reference_Graph_Edge($source_node, $dest_node, $reference, $lazy, $weak, $by_constructor, $by_multi_use_argument);
        $source_node->add_out_edge($edge);
        $dest_node->add_in_edge($edge);
    }
    private function create_node(string $id, mixed $value): Service_Reference_Graph_Node
    {
        if (isset($this->nodes[$id]) && $this->nodes[$id]->get_value() === $value) {
            return $this->nodes[$id];
        }
        return $this->nodes[$id] = new Service_Reference_Graph_Node($id, $value);
    }
}