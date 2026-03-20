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

/**
 * @implements \IteratorAggregate<TreeNode>
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class Tree_Node implements \Countable, \IteratorAggregate, \Stringable
{
    /**
     * @var array<TreeNode|callable(): \Generator>
     */
    private array $children = [];
    public function __construct(private readonly string $value = '', iterable $children = [])
    {
        foreach ($children as $child) {
            $this->add_child($child);
        }
    }
    public static function from_values(iterable $nodes, ?self $node = null): self
    {
        $node ??= new self();
        foreach ($nodes as $key => $value) {
            if (is_iterable($value)) {
                $child = new self($key);
                self::from_values($value, $child);
                $node->add_child($child);
            } elseif ($value instanceof self) {
                $node->add_child($value);
            } else {
                $node->add_child(new self($value));
            }
        }
        return $node;
    }
    public function get_value(): string
    {
        return $this->value;
    }
    public function add_child(self|string|callable $node): self
    {
        if (\is_string($node)) {
            $node = new self($node);
        }
        $this->children[] = $node;
        return $this;
    }
    /**
     * @return \Traversable<int, TreeNode>
     */
    public function get_children(): \Traversable
    {
        foreach ($this->children as $child) {
            if (\is_callable($child)) {
                yield from $child();
            } elseif ($child instanceof self) {
                yield $child;
            }
        }
    }
    /**
     * @return \Traversable<int, TreeNode>
     */
    public function getIterator(): \Traversable
    {
        return $this->get_children();
    }
    public function count(): int
    {
        $count = 0;
        foreach ($this->get_children() as $child) {
            ++$count;
        }
        return $count;
    }
    public function __toString(): string
    {
        return $this->value;
    }
}