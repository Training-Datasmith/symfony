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

/**
 * Represents an edge in your service graph.
 *
 * Value is typically a reference.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Service_Reference_Graph_Edge
{
    public function __construct(private readonly Service_Reference_Graph_Node $source_node, private readonly Service_Reference_Graph_Node $dest_node, private readonly mixed $value = null, private readonly bool $lazy = false, private readonly bool $weak = false, private readonly bool $by_constructor = false, private readonly bool $by_multi_use_argument = false)
    {
    }
    /**
     * Returns the value of the edge.
     */
    public function get_value(): mixed
    {
        return $this->value;
    }
    /**
     * Returns the source node.
     */
    public function get_source_node(): Service_Reference_Graph_Node
    {
        return $this->source_node;
    }
    /**
     * Returns the destination node.
     */
    public function get_dest_node(): Service_Reference_Graph_Node
    {
        return $this->dest_node;
    }
    /**
     * Returns true if the edge is lazy, meaning it's a dependency not requiring direct instantiation.
     */
    public function is_lazy(): bool
    {
        return $this->lazy;
    }
    /**
     * Returns true if the edge is weak, meaning it shouldn't prevent removing the target service.
     */
    public function is_weak(): bool
    {
        return $this->weak;
    }
    /**
     * Returns true if the edge links with a constructor argument.
     */
    public function is_referenced_by_constructor(): bool
    {
        return $this->by_constructor;
    }
    public function is_from_multi_use_argument(): bool
    {
        return $this->by_multi_use_argument;
    }
}