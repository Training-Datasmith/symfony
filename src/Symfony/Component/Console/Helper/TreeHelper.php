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
namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Output\Output_Interface;
/**
 * The TreeHelper class provides methods to display tree-like structures.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @implements \RecursiveIterator<int, TreeNode>
 */
final readonly class Tree_Helper implements \Recursive_Iterator
{
    /**
     * @var \Iterator<int, TreeNode>
     */
    private \Iterator $children;
    private function __construct(private Output_Interface $output, private Tree_Node $node, private Tree_Style $style)
    {
        $this->children = new \Iterator_Iterator($this->node->get_children());
        $this->children->rewind();
    }
    public static function create_tree(Output_Interface $output, string|Tree_Node|null $root = null, iterable $values = [], ?Tree_Style $style = null): self
    {
        $node = $root instanceof Tree_Node ? $root : new Tree_Node($root ?? '');
        return new self($output, Tree_Node::from_values($values, $node), $style ?? Tree_Style::default());
    }
    public function current(): Tree_Node
    {
        return $this->children->current();
    }
    public function key(): int
    {
        return $this->children->key();
    }
    public function next(): void
    {
        $this->children->next();
    }
    public function rewind(): void
    {
        $this->children->rewind();
    }
    public function valid(): bool
    {
        return $this->children->valid();
    }
    public function has_children(): bool
    {
        if (null === $current = $this->current()) {
            return false;
        }
        foreach ($current->get_children() as $child) {
            return true;
        }
        return false;
    }
    public function get_children(): \Recursive_Iterator
    {
        return new self($this->output, $this->current(), $this->style);
    }
    /**
     * Recursively renders the tree to the output, applying the tree style.
     */
    public function render(): void
    {
        $tree_iterator = new \Recursive_Tree_Iterator($this);
        $this->style->apply_prefixes($tree_iterator);
        $this->output->writeln($this->node->get_value());
        $visited = new \Spl_Object_Storage();
        foreach ($tree_iterator as $node) {
            $current_node = $node instanceof Tree_Node ? $node : $tree_iterator->get_inner_iterator()->current();
            if (isset($visited[$current_node])) {
                throw new \LogicException(\sprintf('Cycle detected at node: "%s".', $current_node->get_value()));
            }
            $visited[$current_node] = true;
            $this->output->writeln($node);
        }
    }
}