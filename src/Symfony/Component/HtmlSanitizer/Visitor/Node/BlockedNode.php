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
namespace Symfony\Component\Html_Sanitizer\Visitor\Node;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class Blocked_Node implements Node_Interface
{
    private array $children = [];
    public function __construct(private readonly Node_Interface $parent_node)
    {
    }
    public function add_child(Node_Interface $node): void
    {
        $this->children[] = $node;
    }
    public function get_parent(): \Symfony\Component\Html_Sanitizer\Visitor\Node\Node_Interface
    {
        return $this->parent_node;
    }
    public function render(): string
    {
        $rendered = '';
        foreach ($this->children as $child) {
            $rendered .= $child->render();
        }
        return $rendered;
    }
}