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

use Symfony\Component\Html_Sanitizer\Text_Sanitizer\String_Sanitizer;
/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final readonly class Text_Node implements Node_Interface
{
    public function __construct(private Node_Interface $parent_node, private string $text)
    {
    }
    public function add_child(Node_Interface $node): void
    {
        throw new \LogicException('Text nodes cannot have children.');
    }
    public function get_parent(): \Symfony\Component\Html_Sanitizer\Visitor\Node\Node_Interface
    {
        return $this->parent_node;
    }
    public function render(): string
    {
        return String_Sanitizer::encode_html_entities($this->text);
    }
}