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
/**
 * This is the entry class for building a config tree.
 *
 * @template T of 'array'|'variable'|'scalar'|'string'|'boolean'|'integer'|'float'|'enum' = 'array'
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Tree_Builder implements Node_Parent_Interface
{
    protected ?Node_Interface $tree = null;
    /**
     * @var NodeDefinition<$this>|null
     */
    protected ?Node_Definition $root = null;
    /**
     * @param T $type
     */
    public function __construct(string $name, string $type = 'array', ?Node_Builder $builder = null)
    {
        $builder ??= new Node_Builder();
        $this->root = $builder->node($name, $type)->set_parent($this);
    }
    /**
     * @return (
     *    T is 'array' ? ArrayNodeDefinition<$this>
     *    : (T is 'variable' ? VariableNodeDefinition<$this>
     *    : (T is 'scalar' ? ScalarNodeDefinition<$this>
     *    : (T is 'string' ? StringNodeDefinition<$this>
     *    : (T is 'boolean' ? BooleanNodeDefinition<$this>
     *    : (T is 'integer' ? IntegerNodeDefinition<$this>
     *    : (T is 'float' ? FloatNodeDefinition<$this>
     *    : (T is 'enum' ? EnumNodeDefinition<$this>
     *    : NodeDefinition<$this>)))))))
     * )
     */
    public function get_root_node(): Node_Definition
    {
        return $this->root;
    }
    public function build_tree(): Node_Interface
    {
        return $this->tree ??= $this->root->get_node(true);
    }
    public function set_path_separator(string $separator): void
    {
        // unset last built as changing path separator changes all nodes
        $this->tree = null;
        $this->root->set_path_separator($separator);
    }
}