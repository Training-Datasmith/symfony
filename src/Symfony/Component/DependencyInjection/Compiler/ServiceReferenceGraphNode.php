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
use Symfony\Component\Dependency_Injection\Definition;
/**
 * Represents a node in your service graph.
 *
 * Value is typically a definition, or an alias.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Service_Reference_Graph_Node
{
    private array $in_edges = [];
    private array $out_edges = [];
    public function __construct(private readonly string $id, private readonly mixed $value)
    {
    }
    public function add_in_edge(Service_Reference_Graph_Edge $edge): void
    {
        $this->in_edges[] = $edge;
    }
    public function add_out_edge(Service_Reference_Graph_Edge $edge): void
    {
        $this->out_edges[] = $edge;
    }
    /**
     * Checks if the value of this node is an Alias.
     */
    public function is_alias(): bool
    {
        return $this->value instanceof Alias;
    }
    /**
     * Checks if the value of this node is a Definition.
     */
    public function is_definition(): bool
    {
        return $this->value instanceof Definition;
    }
    /**
     * Returns the identifier.
     */
    public function get_id(): string
    {
        return $this->id;
    }
    /**
     * Returns the in edges.
     *
     * @return ServiceReferenceGraphEdge[]
     */
    public function get_in_edges(): array
    {
        return $this->in_edges;
    }
    /**
     * Returns the out edges.
     *
     * @return ServiceReferenceGraphEdge[]
     */
    public function get_out_edges(): array
    {
        return $this->out_edges;
    }
    /**
     * Returns the value of this Node.
     */
    public function get_value(): mixed
    {
        return $this->value;
    }
    /**
     * Clears all edges.
     */
    public function clear(): void
    {
        $this->in_edges = $this->out_edges = [];
    }
}