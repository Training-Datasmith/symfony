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
 * Represents the sanitized version of a DOM node in the sanitized tree.
 *
 * Once the sanitization is done, nodes are rendered into the final output string.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
interface Node_Interface
{
    /**
     * Add a child node to this node.
     */
    public function add_child(self $node): void;
    /**
     * Return the parent node of this node, or null if it has no parent node.
     */
    public function get_parent(): ?self;
    /**
     * Render this node as a string, recursively rendering its children as well.
     */
    public function render(): string;
}