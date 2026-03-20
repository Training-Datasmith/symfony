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

use Symfony\Component\Config\Definition\Array_Node;
use Symfony\Component\Config\Definition\Exception\Invalid_Definition_Exception;
use Symfony\Component\Config\Definition\Node_Interface;
use Symfony\Component\Config\Definition\Prototyped_Array_Node;
/**
 * This class provides a fluent interface for defining an array node.
 *
 * @template TParent of NodeParentInterface|null = null
 *
 * @extends NodeDefinition<TParent>
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Array_Node_Definition extends Node_Definition implements Parent_Node_Definition_Interface
{
    protected bool $perform_deep_merging = true;
    protected bool $ignore_extra_keys = false;
    protected bool $remove_extra_keys = true;
    /**
     * @var NodeDefinition<$this>[]
     */
    protected array $children = [];
    /**
     * @var NodeDefinition<$this>
     */
    protected Node_Definition $prototype;
    protected bool $at_least_one = false;
    protected bool $allow_new_keys = true;
    protected ?string $key = null;
    protected bool $remove_key_item = false;
    protected bool $add_defaults = false;
    protected int|string|array|false|null $add_default_children = false;
    /**
     * @var NodeBuilder<static>
     */
    protected Node_Builder $node_builder;
    protected bool $normalize_keys = true;
    /**
     * @var list<ExprBuilder::TYPE_*>|null
     */
    protected ?array $allowed_types = null;
    /**
     * @param TParent $parent
     */
    public function __construct(?string $name, ?Node_Parent_Interface $parent = null)
    {
        parent::__construct($name, $parent);
        $this->null_equivalent = [];
        $this->true_equivalent = [];
    }
    /**
     * @return $this
     */
    public function default_value(mixed $value): static
    {
        $this->null_equivalent = null === $value ? null : [];
        return parent::default_value($value);
    }
    /**
     * @param NodeBuilder<static> $builder
     */
    public function set_builder(Node_Builder $builder): void
    {
        $this->node_builder = $builder;
    }
    /**
     * Allows alternative types and wraps them into arrays.
     *
     * @param list<ExprBuilder::TYPE_INT|ExprBuilder::TYPE_STRING|ExprBuilder::TYPE_BOOL|ExprBuilder::TYPE_NULL|ExprBuilder::TYPE_BACKED_ENUM> $allowedTypes
     * @param string|null                                                                                                                      $key          The key to wrap the value in
     *
     * @return $this
     */
    public function accept_and_wrap(array $allowed_types, ?string $key = null): static
    {
        $this->allowed_types = $allowed_types;
        foreach ($allowed_types as $type) {
            $this->before_normalization()->if_true(match ($type) {
                Expr_Builder::TYPE_INT => is_int(...),
                Expr_Builder::TYPE_STRING => is_string(...),
                Expr_Builder::TYPE_BOOL => is_bool(...),
                Expr_Builder::TYPE_NULL => is_null(...),
                Expr_Builder::TYPE_BACKED_ENUM => static fn($v): bool => $v instanceof \Backed_Enum,
            })->then(static fn($v): array => [$key ?? 0 => $v]);
        }
        return $this;
    }
    /**
     * @return NodeBuilder<static>
     */
    public function children(): Node_Builder
    {
        return $this->get_node_builder();
    }
    /**
     * Sets a prototype for child nodes.
     *
     * @template T of 'array'|'variable'|'scalar'|'string'|'boolean'|'integer'|'float'|'enum'
     *
     * @param T $type
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
     */
    public function prototype(string $type): Node_Definition
    {
        return $this->prototype = $this->get_node_builder()->node(null, $type)->set_parent($this);
    }
    /**
     * @return VariableNodeDefinition<$this>
     */
    public function variable_prototype(): Variable_Node_Definition
    {
        return $this->prototype('variable');
    }
    /**
     * @return ScalarNodeDefinition<$this>
     */
    public function scalar_prototype(): Scalar_Node_Definition
    {
        return $this->prototype('scalar');
    }
    /**
     * @return StringNodeDefinition<$this>
     */
    public function string_prototype(): String_Node_Definition
    {
        return $this->prototype('string');
    }
    /**
     * @return BooleanNodeDefinition<$this>
     */
    public function boolean_prototype(): Boolean_Node_Definition
    {
        return $this->prototype('boolean');
    }
    /**
     * @return IntegerNodeDefinition<$this>
     */
    public function integer_prototype(): Integer_Node_Definition
    {
        return $this->prototype('integer');
    }
    /**
     * @return FloatNodeDefinition<$this>
     */
    public function float_prototype(): Float_Node_Definition
    {
        return $this->prototype('float');
    }
    /**
     * @return self<$this>
     */
    public function array_prototype(): self
    {
        return $this->prototype('array');
    }
    /**
     * @return EnumNodeDefinition<$this>
     */
    public function enum_prototype(): Enum_Node_Definition
    {
        return $this->prototype('enum');
    }
    /**
     * Adds the default value if the node is not set in the configuration.
     *
     * This method is applicable to concrete nodes only (not to prototype nodes).
     * If this function has been called and the node is not set during the finalization
     * phase, it's default value will be derived from its children default values.
     *
     * @return $this
     */
    public function add_defaults_if_not_set(): static
    {
        $this->add_defaults = true;
        return $this;
    }
    /**
     * Adds children with a default value when none are defined.
     *
     * This method is applicable to prototype nodes only.
     *
     * @param int|string|array|null $children The number of children|The child name|The children names to be added
     *
     * @return $this
     */
    public function add_default_children_if_none_set(int|string|array|null $children = null): static
    {
        $this->add_default_children = $children;
        return $this;
    }
    /**
     * Requires the node to have at least one element.
     *
     * This method is applicable to prototype nodes only.
     *
     * @return $this
     */
    public function requires_at_least_one_element(): static
    {
        $this->at_least_one = true;
        return $this;
    }
    /**
     * Disallows adding news keys in a subsequent configuration.
     *
     * If used all keys have to be defined in the same configuration file.
     *
     * @return $this
     */
    public function disallow_new_keys_in_subsequent_configs(): static
    {
        $this->allow_new_keys = false;
        return $this;
    }
    /**
     * Sets a normalization rule for XML configurations.
     *
     * @param string      $singular The key to remap
     * @param string|null $plural   The plural of the key for irregular plurals
     *
     * @return $this
     */
    public function fix_xml_config(string $singular, ?string $plural = null): static
    {
        $this->normalization()->remap($singular, $plural);
        return $this;
    }
    /**
     * Sets the attribute which value is to be used as key.
     *
     * This is useful when you have an indexed array that should be an
     * associative array. You can select an item from within the array
     * to be the key of the particular item. For example, if "id" is the
     * "key", then:
     *
     *     [
     *         ['id' => 'my_name', 'foo' => 'bar'],
     *     ];
     *
     *   becomes
     *
     *     [
     *         'my_name' => ['foo' => 'bar'],
     *     ];
     *
     * If you'd like "'id' => 'my_name'" to still be present in the resulting
     * array, then you can set the second argument of this method to false.
     *
     * This method is applicable to prototype nodes only.
     *
     * @param string $name          The name of the key
     * @param bool   $removeKeyItem Whether or not the key item should be removed
     *
     * @return $this
     */
    public function use_attribute_as_key(string $name, bool $remove_key_item = true): static
    {
        $this->key = $name;
        $this->remove_key_item = $remove_key_item;
        return $this;
    }
    /**
     * Sets whether the node can be unset.
     *
     * @return $this
     */
    public function can_be_unset(bool $allow = true): static
    {
        $this->merge()->allow_unset($allow);
        return $this;
    }
    /**
     * Adds an "enabled" boolean to enable the current section.
     *
     * By default, the section is disabled. If any configuration is specified then
     * the node will be automatically enabled:
     *
     * enableableArrayNode: {enabled: true, ...}   # The config is enabled & default values get overridden
     * enableableArrayNode: ~                      # The config is enabled & use the default values
     * enableableArrayNode: true                   # The config is enabled & use the default values
     * enableableArrayNode: {other: value, ...}    # The config is enabled & default values get overridden
     * enableableArrayNode: {enabled: false, ...}  # The config is disabled
     * enableableArrayNode: false                  # The config is disabled
     *
     * @param string|null $info A description of what happens when the node is enabled or disabled
     *
     * @return $this
     */
    public function can_be_enabled(?string $info = null): static
    {
        $disabled_node = $this->attribute('auto_enable', true)->add_defaults_if_not_set()->treat_false_like(['enabled' => false])->treat_true_like(['enabled' => true])->treat_null_like(['enabled' => true])->before_normalization()->if_array()->then(static function (array $v): array {
            $v['enabled'] ??= true;
            return $v;
        })->end()->children()->boolean_node('enabled')->default_false();
        if ($info) {
            $disabled_node->info($info);
        }
        return $this;
    }
    /**
     * Adds an "enabled" boolean to enable the current section.
     *
     * By default, the section is enabled.
     *
     * @param string|null $info A description of what happens when the node is enabled or disabled
     *
     * @return $this
     */
    public function can_be_disabled(?string $info = null): static
    {
        $enabled_node = $this->attribute('auto_enable', true)->add_defaults_if_not_set()->treat_false_like(['enabled' => false])->treat_true_like(['enabled' => true])->treat_null_like(['enabled' => true])->children()->boolean_node('enabled')->default_true();
        if ($info) {
            $enabled_node->info($info);
        }
        return $this;
    }
    /**
     * Disables the deep merging of the node.
     *
     * @return $this
     */
    public function perform_no_deep_merging(): static
    {
        $this->perform_deep_merging = false;
        return $this;
    }
    /**
     * Allows extra config keys to be specified under an array without
     * throwing an exception.
     *
     * Those config values are ignored and removed from the resulting
     * array. This should be used only in special cases where you want
     * to send an entire configuration array through a special tree that
     * processes only part of the array.
     *
     * @param bool $remove Whether to remove the extra keys
     *
     * @return $this
     */
    public function ignore_extra_keys(bool $remove = true): static
    {
        $this->ignore_extra_keys = true;
        $this->remove_extra_keys = $remove;
        return $this;
    }
    /**
     * Sets whether to enable key normalization.
     *
     * @return $this
     */
    public function normalize_keys(bool $bool): static
    {
        $this->normalize_keys = $bool;
        return $this;
    }
    public function append(Node_Definition $node): static
    {
        $this->children[$node->name ?? ''] = $node->set_parent($this);
        return $this;
    }
    /**
     * Returns a node builder to be used to add children and prototype.
     *
     * @return NodeBuilder<static>
     */
    protected function get_node_builder(): Node_Builder
    {
        $this->node_builder ??= new Node_Builder();
        return $this->node_builder->set_parent($this);
    }
    protected function create_node(): Node_Interface
    {
        if (!isset($this->prototype)) {
            $node = new Array_Node($this->name, $this->parent, $this->path_separator);
            $this->validate_concrete_node($node);
            $node->set_add_if_not_set($this->add_defaults);
            foreach ($this->children as $child) {
                $child->parent = $node;
                $node->add_child($child->get_node());
            }
        } else {
            $node = new Prototyped_Array_Node($this->name, $this->parent, $this->path_separator);
            $this->validate_prototype_node($node);
            if (null !== $this->key) {
                $node->set_key_attribute($this->key, $this->remove_key_item);
            }
            if (true === $this->at_least_one || false === $this->allow_empty_value) {
                $node->set_min_number_of_elements(1);
            }
            if ($this->default) {
                if (null === $this->default_value) {
                    $node->set_null_as_default();
                } elseif (!\is_array($this->default_value)) {
                    throw new \InvalidArgumentException(\sprintf('%s: the default value of an array node has to be an array or null.', $node->get_path()));
                } else {
                    $node->set_default_value($this->default_value);
                }
            }
            if (false !== $this->add_default_children) {
                $node->set_add_children_if_none_set($this->add_default_children);
                if ($this->prototype instanceof self && !isset($this->prototype->prototype)) {
                    $this->prototype->add_defaults_if_not_set();
                }
            }
            $this->prototype->parent = $node;
            $node->set_prototype($this->prototype->get_node());
        }
        $node->set_allow_new_keys($this->allow_new_keys);
        $node->add_equivalent_value(null, $this->null_equivalent);
        $node->add_equivalent_value(true, $this->true_equivalent);
        $node->add_equivalent_value(false, $this->false_equivalent);
        $node->set_perform_deep_merging($this->perform_deep_merging);
        $node->set_required($this->required);
        $node->set_ignore_extra_keys($this->ignore_extra_keys, $this->remove_extra_keys);
        $node->set_normalize_keys($this->normalize_keys);
        if ($this->deprecation) {
            $node->set_deprecated($this->deprecation['package'], $this->deprecation['version'], $this->deprecation['message']);
        }
        $normalized_types = $this->allowed_types ?? [];
        if (isset($this->normalization)) {
            $normalized_types = $normalized_types ?: $this->normalization->declared_types;
            $node->set_normalization_closures($this->normalization->before);
            $node->set_xml_remappings($this->normalization->remappings);
        }
        $normalized_types[] = Expr_Builder::TYPE_ARRAY;
        foreach ([$this->true_equivalent, $this->false_equivalent] as $equivalent) {
            if (\is_array($equivalent) && $equivalent) {
                $normalized_types[] = Expr_Builder::TYPE_BOOL;
            }
        }
        $node->set_normalized_types(array_values(array_unique($normalized_types)));
        if (isset($this->merge)) {
            $node->set_allow_overwrite($this->merge->allow_overwrite);
            $node->set_allow_false($this->merge->allow_false);
        }
        if (isset($this->validation)) {
            $node->set_final_validation_closures($this->validation->rules);
        }
        return $node;
    }
    /**
     * Validate the configuration of a concrete node.
     *
     * @throws InvalidDefinitionException
     */
    protected function validate_concrete_node(Array_Node $node): void
    {
        $path = $node->get_path();
        if (null !== $this->key) {
            throw new Invalid_Definition_Exception(\sprintf('->useAttributeAsKey() is not applicable to concrete nodes at path "%s".', $path));
        }
        if (false === $this->allow_empty_value) {
            throw new Invalid_Definition_Exception(\sprintf('->cannotBeEmpty() is not applicable to concrete nodes at path "%s".', $path));
        }
        if (true === $this->at_least_one) {
            throw new Invalid_Definition_Exception(\sprintf('->requiresAtLeastOneElement() is not applicable to concrete nodes at path "%s".', $path));
        }
        if ($this->default) {
            throw new Invalid_Definition_Exception(\sprintf('->defaultValue() is not applicable to concrete nodes at path "%s".', $path));
        }
        if (false !== $this->add_default_children) {
            throw new Invalid_Definition_Exception(\sprintf('->addDefaultChildrenIfNoneSet() is not applicable to concrete nodes at path "%s".', $path));
        }
    }
    /**
     * Validate the configuration of a prototype node.
     *
     * @throws InvalidDefinitionException
     */
    protected function validate_prototype_node(Prototyped_Array_Node $node): void
    {
        $path = $node->get_path();
        if ($this->add_defaults) {
            throw new Invalid_Definition_Exception(\sprintf('->addDefaultsIfNotSet() is not applicable to prototype nodes at path "%s".', $path));
        }
        if (false !== $this->add_default_children) {
            if ($this->default) {
                throw new Invalid_Definition_Exception(\sprintf('A default value and default children might not be used together at path "%s".', $path));
            }
            if (null !== $this->key && (null === $this->add_default_children || \is_int($this->add_default_children) && $this->add_default_children > 0)) {
                throw new Invalid_Definition_Exception(\sprintf('->addDefaultChildrenIfNoneSet() should set default children names as ->useAttributeAsKey() is used at path "%s".', $path));
            }
            if (null === $this->key && (\is_string($this->add_default_children) || \is_array($this->add_default_children))) {
                throw new Invalid_Definition_Exception(\sprintf('->addDefaultChildrenIfNoneSet() might not set default children names as ->useAttributeAsKey() is not used at path "%s".', $path));
            }
        }
    }
    /**
     * @return NodeDefinition<$this>[]
     */
    public function get_child_node_definitions(): array
    {
        return $this->children;
    }
    /**
     * Finds a node defined by the given $nodePath.
     *
     * @param string $nodePath The path of the node to find. e.g "doctrine.orm.mappings"
     */
    public function find(string $node_path): Node_Definition
    {
        $first_path_segment = false === ($path_separator_pos = strpos($node_path, $this->path_separator)) ? $node_path : substr($node_path, 0, $path_separator_pos);
        if (null === $node = $this->children[$first_path_segment] ?? null) {
            throw new \RuntimeException(\sprintf('Node with name "%s" does not exist in the current node "%s".', $first_path_segment, $this->name));
        }
        if (false === $path_separator_pos) {
            return $node;
        }
        return $node->find(substr($node_path, $path_separator_pos + \strlen($this->path_separator)));
    }
}