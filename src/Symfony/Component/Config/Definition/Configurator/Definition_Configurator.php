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
namespace Symfony\Component\Config\Definition\Configurator;

use Symfony\Component\Config\Definition\Builder\Array_Node_Definition;
use Symfony\Component\Config\Definition\Builder\Boolean_Node_Definition;
use Symfony\Component\Config\Definition\Builder\Enum_Node_Definition;
use Symfony\Component\Config\Definition\Builder\Float_Node_Definition;
use Symfony\Component\Config\Definition\Builder\Integer_Node_Definition;
use Symfony\Component\Config\Definition\Builder\Node_Definition;
use Symfony\Component\Config\Definition\Builder\Scalar_Node_Definition;
use Symfony\Component\Config\Definition\Builder\String_Node_Definition;
use Symfony\Component\Config\Definition\Builder\Tree_Builder;
use Symfony\Component\Config\Definition\Builder\Variable_Node_Definition;
use Symfony\Component\Config\Definition\Loader\Definition_File_Loader;
/**
 * @template T of 'array'|'variable'|'scalar'|'string'|'boolean'|'integer'|'float'|'enum' = 'array'
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class Definition_Configurator
{
    /**
     * @param TreeBuilder<T> $treeBuilder
     */
    public function __construct(private readonly Tree_Builder $tree_builder, private readonly Definition_File_Loader $loader, private readonly string $path, private readonly string $file)
    {
    }
    public function import(string $resource, ?string $type = null, bool $ignore_errors = false): void
    {
        $this->loader->set_current_dir(\dirname($this->path));
        $this->loader->import($resource, $type, $ignore_errors, $this->file);
    }
    /**
     * @return (
     *    T is 'array' ? ArrayNodeDefinition<TreeBuilder<T>>
     *    : (T is 'variable' ? VariableNodeDefinition<TreeBuilder<T>>
     *    : (T is 'scalar' ? ScalarNodeDefinition<TreeBuilder<T>>
     *    : (T is 'string' ? StringNodeDefinition<TreeBuilder<T>>
     *    : (T is 'boolean' ? BooleanNodeDefinition<TreeBuilder<T>>
     *    : (T is 'integer' ? IntegerNodeDefinition<TreeBuilder<T>>
     *    : (T is 'float' ? FloatNodeDefinition<TreeBuilder<T>>
     *    : (T is 'enum' ? EnumNodeDefinition<TreeBuilder<T>>
     *    : NodeDefinition<TreeBuilder<T>>)))))))
     * )
     */
    public function root_node(): Node_Definition
    {
        return $this->tree_builder->get_root_node();
    }
    public function set_path_separator(string $separator): void
    {
        $this->tree_builder->set_path_separator($separator);
    }
}