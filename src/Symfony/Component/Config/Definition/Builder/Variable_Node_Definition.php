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

use Symfony\Component\Config\Definition\Node_Interface;
use Symfony\Component\Config\Definition\Variable_Node;
/**
 * This class provides a fluent interface for defining a node.
 *
 * @template TParent of NodeParentInterface|null = null
 *
 * @extends NodeDefinition<TParent>
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Variable_Node_Definition extends Node_Definition
{
    protected function instantiate_node(): Variable_Node
    {
        return new Variable_Node($this->name, $this->parent, $this->path_separator);
    }
    protected function create_node(): Node_Interface
    {
        $node = $this->instantiate_node();
        if (isset($this->normalization)) {
            $node->set_normalization_closures($this->normalization->before);
        }
        if (isset($this->merge)) {
            $node->set_allow_overwrite($this->merge->allow_overwrite);
        }
        if (true === $this->default) {
            $node->set_default_value($this->default_value);
        }
        $node->set_allow_empty_value($this->allow_empty_value);
        $node->add_equivalent_value(null, $this->null_equivalent);
        $node->add_equivalent_value(true, $this->true_equivalent);
        $node->add_equivalent_value(false, $this->false_equivalent);
        $node->set_required($this->required);
        if ($this->deprecation) {
            $node->set_deprecated($this->deprecation['package'], $this->deprecation['version'], $this->deprecation['message']);
        }
        if (isset($this->validation)) {
            $node->set_final_validation_closures($this->validation->rules);
        }
        return $node;
    }
}