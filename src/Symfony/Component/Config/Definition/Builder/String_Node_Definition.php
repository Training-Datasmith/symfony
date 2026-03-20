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

use Symfony\Component\Config\Definition\String_Node;
/**
 * This class provides a fluent interface for defining a node.
 *
 * @template TParent of NodeParentInterface|null = null
 *
 * @extends ScalarNodeDefinition<TParent>
 *
 * @author Raffaele Carelle <raffaele.carelle@gmail.com>
 */
class String_Node_Definition extends Scalar_Node_Definition
{
    /**
     * @param TParent $parent
     */
    public function __construct(?string $name, ?Node_Parent_Interface $parent = null)
    {
        parent::__construct($name, $parent);
        $this->null_equivalent = '';
    }
    protected function instantiate_node(): String_Node
    {
        return new String_Node($this->name, $this->parent, $this->path_separator);
    }
}