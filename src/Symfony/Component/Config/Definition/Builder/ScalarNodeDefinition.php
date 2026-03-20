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

use Symfony\Component\Config\Definition\Scalar_Node;
/**
 * This class provides a fluent interface for defining a node.
 *
 * @template TParent of NodeParentInterface|null = null
 *
 * @extends VariableNodeDefinition<TParent>
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Scalar_Node_Definition extends Variable_Node_Definition
{
    protected function instantiate_node(): Scalar_Node
    {
        return new Scalar_Node($this->name, $this->parent, $this->path_separator);
    }
}