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

/**
 * This class provides a fluent interface for building a node.
 *
 * @template TParent of (NodeDefinition&ParentNodeDefinitionInterface)|null = null
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Node_Builder implements Node_Parent_Interface
{
    /**
     * @var TParent
     */
    protected (Node_Definition&Parent_Node_Definition_Interface)|null $parent = null;
    /**
     * @var array<string, class-string<NodeDefinition>>
     */
    protected array $node_mapping;
    public function __construct()
    {
        // This list should be in sync with generics on method node() below and on TreeBuilder, ArrayNodeDefinition and DefinitionConfigurator
        $this->node_mapping = ['array' => Array_Node_Definition::class, 'variable' => Variable_Node_Definition::class, 'scalar' => Scalar_Node_Definition::class, 'string' => String_Node_Definition::class, 'boolean' => Boolean_Node_Definition::class, 'integer' => Integer_Node_Definition::class, 'float' => Float_Node_Definition::class, 'enum' => Enum_Node_Definition::class];
    }
    /**
     * Set the parent node.
     *
     * @template TNewParent of (NodeDefinition&ParentNodeDefinitionInterface)|null
     *
     * @psalm-this-out static<TNewParent>
     *
     * @return $this
     */
    public function set_parent((Node_Definition&Parent_Node_Definition_Interface)|null $parent): static
    {
        $this->parent = $parent;
        return $this;
    }
    /**
     * Creates a child array node.
     *
     * @param string|null $singular The singular name of the node when $name is plural
     *
     * @return ArrayNodeDefinition<$this>
     */
    public function array_node(string $name, ?string $singular = null): Array_Node_Definition
    {
        if (null !== $singular) {
            if (!$this->parent instanceof Array_Node_Definition) {
                throw new \LogicException('The parent node must be an ArrayNodeDefinition when setting the singular name.');
            }
            $this->parent->fix_xml_config($singular, $name);
        }
        return $this->node($name, 'array');
    }
    /**
     * Creates a child scalar node.
     *
     * @return ScalarNodeDefinition<$this>
     */
    public function scalar_node(string $name): Scalar_Node_Definition
    {
        return $this->node($name, 'scalar');
    }
    /**
     * Creates a child Boolean node.
     *
     * @return BooleanNodeDefinition<$this>
     */
    public function boolean_node(string $name): Boolean_Node_Definition
    {
        return $this->node($name, 'boolean');
    }
    /**
     * Creates a child integer node.
     *
     * @return IntegerNodeDefinition<$this>
     */
    public function integer_node(string $name): Integer_Node_Definition
    {
        return $this->node($name, 'integer');
    }
    /**
     * Creates a child float node.
     *
     * @return FloatNodeDefinition<$this>
     */
    public function float_node(string $name): Float_Node_Definition
    {
        return $this->node($name, 'float');
    }
    /**
     * Creates a child EnumNode.
     *
     * @return EnumNodeDefinition<$this>
     */
    public function enum_node(string $name): Enum_Node_Definition
    {
        return $this->node($name, 'enum');
    }
    /**
     * Creates a child variable node.
     *
     * @return VariableNodeDefinition<$this>
     */
    public function variable_node(string $name): Variable_Node_Definition
    {
        return $this->node($name, 'variable');
    }
    /**
     * Creates a child string node.
     *
     * @return StringNodeDefinition<$this>
     */
    public function string_node(string $name): String_Node_Definition
    {
        return $this->node($name, 'string');
    }
    /**
     * Returns the parent node.
     *
     * @return TParent
     */
    public function end(): (Node_Definition&Parent_Node_Definition_Interface)|null
    {
        return $this->parent;
    }
    /**
     * Creates a child node.
     *
     * @template T of 'array'|'variable'|'scalar'|'string'|'boolean'|'integer'|'float'|'enum'
     *
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
     *
     * @throws \RuntimeException When the node type is not registered
     * @throws \RuntimeException When the node class is not found
     */
    public function node(?string $name, string $type): Node_Definition
    {
        $class = $this->get_node_class($type);
        $node = new $class($name);
        $this->append($node);
        return $node;
    }
    /**
     * Appends a node definition.
     *
     * Usage:
     *
     *     $node = new ArrayNodeDefinition('name')
     *         ->children()
     *             ->scalarNode('foo')->end()
     *             ->scalarNode('baz')->end()
     *             ->append($this->getBarNodeDefinition())
     *         ->end()
     *     ;
     *
     * @return $this
     */
    public function append(Node_Definition $node): static
    {
        if ($node instanceof Builder_Aware_Interface) {
            $builder = clone $this;
            $builder->set_parent(null);
            $node->set_builder($builder);
        }
        if (null !== $this->parent) {
            $this->parent->append($node);
            // Make this builder the node parent to allow for a fluid interface
            $node->set_parent($this);
        }
        return $this;
    }
    /**
     * Adds or overrides a node Type.
     *
     * @param string                       $type  The name of the type
     * @param class-string<NodeDefinition> $class The fully qualified name the node definition class
     *
     * @return $this
     */
    public function set_node_class(string $type, string $class): static
    {
        $this->node_mapping[strtolower($type)] = $class;
        return $this;
    }
    /**
     * Returns the class name of the node definition.
     *
     * @throws \RuntimeException When the node type is not registered
     * @throws \RuntimeException When the node class is not found
     */
    protected function get_node_class(string $type): string
    {
        $type = strtolower($type);
        if (!isset($this->node_mapping[$type])) {
            throw new \RuntimeException(\sprintf('The node type "%s" is not registered.', $type));
        }
        $class = $this->node_mapping[$type];
        if (!class_exists($class)) {
            throw new \RuntimeException(\sprintf('The node class "%s" does not exist.', $class));
        }
        return $class;
    }
}