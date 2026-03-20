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
namespace Symfony\Component\Config\Definition\Builder;

/**
 * An interface that must be implemented by nodes which can have children.
 *
 * @author Victor Berchet <victor@suumit.com>
 */
interface Parent_Node_Definition_Interface extends Builder_Aware_Interface
{
    /**
     * Returns a builder to add children nodes.
     *
     * @return NodeBuilder<static>
     */
    public function children(): Node_Builder;
    /**
     * Appends a node definition.
     *
     * Usage:
     *
     *     $node = $parentNode
     *         ->children()
     *             ->scalarNode('foo')->end()
     *             ->scalarNode('baz')->end()
     *             ->append($this->getBarNodeDefinition())
     *         ->end()
     *     ;
     *
     * @return $this
     */
    public function append(Node_Definition $node): static;
    /**
     * Gets the child node definitions.
     *
     * @return NodeDefinition<static>[]
     */
    public function get_child_node_definitions(): array;
}