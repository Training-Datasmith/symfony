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
namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Exception\Duplicate_Key_Exception;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Config\Definition\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Config\Definition\Exception\Unset_Key_Exception;
/**
 * Represents a prototyped Array node in the config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Prototyped_Array_Node extends Array_Node
{
    protected Prototype_Node_Interface $prototype;
    protected ?string $key_attribute = null;
    protected bool $remove_key_attribute = false;
    protected int $min_number_of_elements = 0;
    protected array $default_value = [];
    protected ?array $default_children = null;
    /**
     * @var NodeInterface[] An array of the prototypes of the simplified value children
     */
    private array $value_prototypes = [];
    private bool $default_to_null = false;
    /**
     * Sets the minimum number of elements that a prototype based node must
     * contain. By default this is zero, meaning no elements.
     */
    public function set_min_number_of_elements(int $number): void
    {
        $this->min_number_of_elements = $number;
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
     *  becomes
     *
     *      [
     *          'my_name' => ['foo' => 'bar'],
     *      ];
     *
     * If you'd like "'id' => 'my_name'" to still be present in the resulting
     * array, then you can set the second argument of this method to false.
     *
     * @param string $attribute The name of the attribute which value is to be used as a key
     * @param bool   $remove    Whether or not to remove the key
     */
    public function set_key_attribute(string $attribute, bool $remove = true): void
    {
        $this->key_attribute = $attribute;
        $this->remove_key_attribute = $remove;
    }
    /**
     * Retrieves the name of the attribute which value should be used as key.
     */
    public function get_key_attribute(): ?string
    {
        return $this->key_attribute;
    }
    /**
     * Sets the default value of this node.
     */
    public function set_default_value(array $value): void
    {
        $this->default_value = $value;
        $this->default_to_null = false;
    }
    public function set_null_as_default(): void
    {
        $this->default_value = [];
        $this->default_to_null = true;
    }
    public function has_default_value(): bool
    {
        return true;
    }
    /**
     * Adds default children when none are set.
     *
     * @param int|string|array|null $children The number of children|The child name|The children names to be added
     */
    public function set_add_children_if_none_set(int|string|array|null $children = ['defaults']): void
    {
        if (null === $children) {
            $this->default_children = ['defaults'];
        } else {
            $this->default_children = \is_int($children) && $children > 0 ? range(1, $children) : (array) $children;
        }
        $this->default_to_null = false;
    }
    /**
     * The default value could be either explicit or derived from the prototype
     * default value.
     */
    public function get_default_value(): mixed
    {
        if ($this->default_to_null) {
            return null;
        }
        if (null !== $this->default_children) {
            $default = $this->prototype->has_default_value() ? $this->prototype->get_default_value() : [];
            $defaults = [];
            foreach (array_values($this->default_children) as $i => $name) {
                $defaults[null === $this->key_attribute ? $i : $name] = $default;
            }
            return $defaults;
        }
        return $this->default_value;
    }
    /**
     * Sets the node prototype.
     */
    public function set_prototype(Prototype_Node_Interface $node): void
    {
        $this->prototype = $node;
    }
    /**
     * Retrieves the prototype.
     */
    public function get_prototype(): Prototype_Node_Interface
    {
        return $this->prototype;
    }
    /**
     * Disable adding concrete children for prototyped nodes.
     *
     * @throws Exception
     */
    public function add_child(Node_Interface $node): never
    {
        throw new Exception('A prototyped array node cannot have concrete children.');
    }
    protected function finalize_value(mixed $value): mixed
    {
        if (false === $value) {
            throw new Unset_Key_Exception(\sprintf('Unsetting key for path "%s", value: false.', $this->get_path()));
        }
        foreach ($value as $k => $v) {
            $prototype = $this->get_prototype_for_child($k);
            try {
                $value[$k] = $prototype->finalize($v);
            } catch (Unset_Key_Exception) {
                unset($value[$k]);
            }
        }
        if (\count($value) < $this->min_number_of_elements) {
            $ex = new Invalid_Configuration_Exception(\sprintf('The path "%s" should have at least %d element(s) defined.', $this->get_path(), $this->min_number_of_elements));
            $ex->set_path($this->get_path());
            throw $ex;
        }
        return $value;
    }
    /**
     * @throws DuplicateKeyException
     */
    protected function normalize_value(mixed $value): mixed
    {
        if (false === $value) {
            return $value;
        }
        $value = $this->remap_xml($value);
        $is_list = array_is_list($value);
        $normalized = [];
        foreach ($value as $k => $v) {
            if (null !== $this->key_attribute && \is_array($v)) {
                if (!isset($v[$this->key_attribute]) && \is_int($k) && $is_list) {
                    $ex = new Invalid_Configuration_Exception(\sprintf('The attribute "%s" must be set for path "%s".', $this->key_attribute, $this->get_path()));
                    $ex->set_path($this->get_path());
                    throw $ex;
                }
                if (isset($v[$this->key_attribute])) {
                    $k = $v[$this->key_attribute];
                    if (\is_float($k)) {
                        $k = var_export($k, true);
                    }
                    // remove the key attribute when required
                    if ($this->remove_key_attribute) {
                        unset($v[$this->key_attribute]);
                    }
                    // if only "value" is left
                    if (array_keys($v) === ['value']) {
                        $v = $v['value'];
                        if ($this->prototype instanceof Array_Node && ($children = $this->prototype->get_children()) && \array_key_exists('value', $children)) {
                            $value_prototype = current($this->value_prototypes) ?: clone $children['value'];
                            $value_prototype->parent = $this;
                            $original_closures = $this->prototype->normalization_closures;
                            $value_prototype_closures = $value_prototype->normalization_closures;
                            $value_prototype->normalization_closures = array_merge($original_closures, $value_prototype_closures);
                            $this->value_prototypes[$k] = $value_prototype;
                        }
                    }
                }
                if (\array_key_exists($k, $normalized)) {
                    $ex = new Duplicate_Key_Exception(\sprintf('Duplicate key "%s" for path "%s".', $k, $this->get_path()));
                    $ex->set_path($this->get_path());
                    throw $ex;
                }
            }
            $prototype = $this->get_prototype_for_child($k);
            if (null !== $this->key_attribute || !$is_list) {
                $normalized[$k] = $prototype->normalize($v);
            } else {
                $normalized[] = $prototype->normalize($v);
            }
        }
        return $normalized;
    }
    protected function merge_values(mixed $left_side, mixed $right_side): mixed
    {
        if (false === $right_side) {
            // if this is still false after the last config has been merged the
            // finalization pass will take care of removing this key entirely
            return false;
        }
        if (false === $left_side || !$this->perform_deep_merging) {
            return $right_side;
        }
        // Track if this is initial population (leftSide is empty) - allowNewKeys
        // should only be enforced when merging additional configs, not when
        // initially populating from the first config
        $is_initial_population = [] === $left_side;
        $is_list = array_is_list($right_side);
        foreach ($right_side as $k => $v) {
            // prototype, and key is irrelevant there are no named keys, append the element
            if (null === $this->key_attribute && $is_list) {
                $left_side[] = $v;
                continue;
            }
            // no conflict
            if (!\array_key_exists($k, $left_side)) {
                if (!$this->allow_new_keys && !$is_initial_population) {
                    $ex = new Invalid_Configuration_Exception(\sprintf('You are not allowed to define new elements for path "%s". Please define all elements for this path in one config file.', $this->get_path()));
                    $ex->set_path($this->get_path());
                    throw $ex;
                }
                if (\is_array($v) && ($prototype = $this->get_prototype_for_child($k)) instanceof Array_Node) {
                    // Ensure prototype's merge is called to handle auto_enable recursively
                    $left_side[$k] = $prototype->merge([], $v);
                } else {
                    $left_side[$k] = $v;
                }
                continue;
            }
            $prototype = $this->get_prototype_for_child($k);
            $left_side[$k] = $prototype->merge($left_side[$k], $v);
        }
        return $left_side;
    }
    /**
     * Returns a prototype for the child node that is associated to $key in the value array.
     * For general child nodes, this will be $this->prototype.
     * But if $this->removeKeyAttribute is true and there are only two keys in the child node:
     * one is same as this->keyAttribute and the other is 'value', then the prototype will be different.
     *
     * For example, assume $this->keyAttribute is 'name' and the value array is as follows:
     *
     *     [
     *         [
     *             'name' => 'name001',
     *             'value' => 'value001'
     *         ]
     *     ]
     *
     * Now, the key is 0 and the child node is:
     *
     *     [
     *        'name' => 'name001',
     *        'value' => 'value001'
     *     ]
     *
     * When normalizing the value array, the 'name' element will removed from the child node
     * and its value becomes the new key of the child node:
     *
     *     [
     *         'name001' => ['value' => 'value001']
     *     ]
     *
     * Now only 'value' element is left in the child node which can be further simplified into a string:
     *
     *     ['name001' => 'value001']
     *
     * Now, the key becomes 'name001' and the child node becomes 'value001' and
     * the prototype of child node 'name001' should be a ScalarNode instead of an ArrayNode instance.
     */
    private function get_prototype_for_child(string $key): mixed
    {
        $prototype = $this->value_prototypes[$key] ?? $this->prototype;
        $prototype->set_name($key);
        return $prototype;
    }
}