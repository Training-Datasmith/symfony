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
final class Document_Node implements Node_Interface
{
    private array $children = [];
    public function add_child(Node_Interface $node): void
    {
        $this->children[] = $node;
    }
    public function get_parent(): ?Node_Interface
    {
        return null;
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