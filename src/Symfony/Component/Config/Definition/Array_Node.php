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

use Symfony\Component\Config\Definition\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Config\Definition\Exception\Invalid_Type_Exception;
use Symfony\Component\Config\Definition\Exception\Unset_Key_Exception;
/**
 * Represents an Array node in the config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Array_Node extends Base_Node implements Prototype_Node_Interface
{
    protected array $xml_remappings = [];
    protected array $children = [];
    protected bool $allow_false = false;
    protected bool $allow_new_keys = true;
    protected bool $add_if_not_set = false;
    protected bool $perform_deep_merging = true;
    protected bool $ignore_extra_keys = false;
    protected bool $remove_extra_keys = true;
    protected bool $normalize_keys = true;
    public function set_normalize_keys(bool $normalize_keys): void
    {
        $this->normalize_keys = $normalize_keys;
    }
    /**
     * Namely, you mostly have foo_bar in YAML while you have foo-bar in XML.
     * After running this method, all keys are normalized to foo_bar.
     *
     * If you have a mixed key like foo-bar_moo, it will not be altered.
     * The key will also not be altered if the target key already exists.
     */
    protected function pre_normalize(mixed $value): mixed
    {
        if (!$this->normalize_keys || !\is_array($value)) {
            return $value;
        }
        $normalized = [];
        foreach ($value as $k => $v) {
            if (str_contains((string) $k, '-') && !str_contains((string) $k, '_') && !\array_key_exists($normalized_key = str_replace('-', '_', $k), $value)) {
                $normalized[$normalized_key] = $v;
            } else {
                $normalized[$k] = $v;
            }
        }
        return $normalized;
    }
    /**
     * Retrieves the children of this node.
     *
     * @return array<string, NodeInterface>
     */
    public function get_children(): array
    {
        return $this->children;
    }
    /**
     * Sets the xml remappings that should be performed.
     *
     * @param array $remappings An array of the form [[string, string]]
     */
    public function set_xml_remappings(array $remappings): void
    {
        $this->xml_remappings = $remappings;
    }
    /**
     * Gets the xml remappings that should be performed.
     *
     * @return array an array of the form [[string, string]]
     */
    public function get_xml_remappings(): array
    {
        return $this->xml_remappings;
    }
    /**
     * Sets whether to add default values for this array if it has not been
     * defined in any of the configuration files.
     */
    public function set_add_if_not_set(bool $boolean): void
    {
        $this->add_if_not_set = $boolean;
    }
    /**
     * Sets whether false is allowed as value indicating that the array should be unset.
     */
    public function set_allow_false(bool $allow): void
    {
        $this->allow_false = $allow;
    }
    /**
     * Sets whether new keys can be defined in subsequent configurations.
     */
    public function set_allow_new_keys(bool $allow): void
    {
        $this->allow_new_keys = $allow;
    }
    /**
     * Sets if deep merging should occur.
     */
    public function set_perform_deep_merging(bool $boolean): void
    {
        $this->perform_deep_merging = $boolean;
    }
    /**
     * Whether deep merging should occur.
     */
    public function should_perform_deep_merging(): bool
    {
        return $this->perform_deep_merging;
    }
    /**
     * Whether extra keys should just be ignored without an exception.
     *
     * @param bool $boolean To allow extra keys
     * @param bool $remove  To remove extra keys
     */
    public function set_ignore_extra_keys(bool $boolean, bool $remove = true): void
    {
        $this->ignore_extra_keys = $boolean;
        $this->remove_extra_keys = $this->ignore_extra_keys && $remove;
    }
    /**
     * Returns true when extra keys should be ignored without an exception.
     */
    public function should_ignore_extra_keys(): bool
    {
        return $this->ignore_extra_keys;
    }
    public function set_name(string $name): void
    {
        $this->name = $name;
    }
    public function has_default_value(): bool
    {
        return $this->add_if_not_set;
    }
    public function get_default_value(): mixed
    {
        if (!$this->has_default_value()) {
            throw new \RuntimeException(\sprintf('The node at path "%s" has no default value.', $this->get_path()));
        }
        $defaults = [];
        foreach ($this->children as $name => $child) {
            if ($child->has_default_value()) {
                $defaults[$name] = $child->get_default_value();
            }
        }
        return $defaults;
    }
    /**
     * Adds a child node.
     *
     * @throws \InvalidArgumentException when the child node has no name
     * @throws \InvalidArgumentException when the child node's name is not unique
     */
    public function add_child(Node_Interface $node): void
    {
        $name = $node->get_name();
        if ('' === $name) {
            throw new \InvalidArgumentException('Child nodes must be named.');
        }
        if (isset($this->children[$name])) {
            throw new \InvalidArgumentException(\sprintf('A child node named "%s" already exists.', $name));
        }
        $this->children[$name] = $node;
    }
    /**
     * @throws UnsetKeyException
     * @throws InvalidConfigurationException if the node doesn't have enough children
     */
    protected function finalize_value(mixed $value): mixed
    {
        if (false === $value) {
            throw new Unset_Key_Exception(\sprintf('Unsetting key for path "%s", value: false.', $this->get_path()));
        }
        foreach ($this->children as $name => $child) {
            if (!\array_key_exists($name, $value)) {
                if ($child->is_required()) {
                    $message = \sprintf('The child config "%s" under "%s" must be configured', $name, $this->get_path());
                    if ($child->get_info()) {
                        $message .= \sprintf(': %s', $child->get_info());
                    } else {
                        $message .= '.';
                    }
                    $ex = new Invalid_Configuration_Exception($message);
                    $ex->set_path($this->get_path());
                    throw $ex;
                }
                if ($child->has_default_value()) {
                    $value[$name] = $child->get_default_value();
                }
                continue;
            }
            if ($child->is_deprecated()) {
                $deprecation = $child->get_deprecation($name, $this->get_path());
                trigger_deprecation($deprecation['package'], $deprecation['version'], $deprecation['message']);
            }
            try {
                $value[$name] = $child->finalize($value[$name]);
            } catch (Unset_Key_Exception) {
                unset($value[$name]);
            }
        }
        return $value;
    }
    protected function validate_type(mixed $value): void
    {
        if (!\is_array($value) && (!$this->allow_false || false !== $value)) {
            $ex = new Invalid_Type_Exception(\sprintf('Invalid type for path "%s". Expected "array", but got "%s"', $this->get_path(), get_debug_type($value)));
            if ($hint = $this->get_info()) {
                $ex->add_hint($hint);
            }
            $ex->set_path($this->get_path());
            throw $ex;
        }
    }
    /**
     * @throws InvalidConfigurationException
     */
    protected function normalize_value(mixed $value): mixed
    {
        if (false === $value) {
            return $value;
        }
        $value = $this->remap_xml($value);
        $normalized = [];
        foreach ($value as $name => $val) {
            if (isset($this->children[$name])) {
                try {
                    $normalized[$name] = $this->children[$name]->normalize($val);
                } catch (Unset_Key_Exception) {
                }
                unset($value[$name]);
            } elseif (!$this->remove_extra_keys) {
                $normalized[$name] = $val;
            }
        }
        // if extra fields are present, throw exception
        if (\count($value) && !$this->ignore_extra_keys) {
            $proposals = array_keys($this->children);
            sort($proposals);
            $guesses = [];
            foreach (array_keys($value) as $subject) {
                $min_score = \INF;
                foreach ($proposals as $proposal) {
                    $distance = levenshtein($subject, $proposal);
                    if ($distance <= $min_score && $distance < 3) {
                        $guesses[$proposal] = $distance;
                        $min_score = $distance;
                    }
                }
            }
            $msg = \sprintf('Unrecognized option%s "%s" under "%s"', 1 === \count($value) ? '' : 's', implode(', ', array_keys($value)), $this->get_path());
            if (\count($guesses)) {
                asort($guesses);
                $msg .= \sprintf('. Did you mean "%s"?', implode('", "', array_keys($guesses)));
            } else {
                $msg .= \sprintf('. Available option%s %s "%s".', 1 === \count($proposals) ? '' : 's', 1 === \count($proposals) ? 'is' : 'are', implode('", "', $proposals));
            }
            $ex = new Invalid_Configuration_Exception($msg);
            $ex->set_path($this->get_path());
            throw $ex;
        }
        return $normalized;
    }
    /**
     * Remaps multiple singular values to a single plural value.
     */
    protected function remap_xml(array $value): array
    {
        foreach ($this->xml_remappings as [$singular, $plural]) {
            if (!isset($value[$singular])) {
                continue;
            }
            $value[$plural] = Processor::normalize_config($value, $singular, $plural);
            unset($value[$singular]);
        }
        return $value;
    }
    /**
     * @throws InvalidConfigurationException
     * @throws \RuntimeException
     */
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
        if ($this->get_attribute('auto_enable') && \is_array($left_side) && \is_array($right_side) && !\array_key_exists('enabled', $left_side) && !\array_key_exists('enabled', $right_side)) {
            $right_side['enabled'] = true;
        }
        foreach ($right_side as $k => $v) {
            // no conflict
            if (!\array_key_exists($k, $left_side)) {
                if (!$this->allow_new_keys) {
                    $ex = new Invalid_Configuration_Exception(\sprintf('You are not allowed to define new elements for path "%s". Please define all elements for this path in one config file. If you are trying to overwrite an element, make sure you redefine it with the same name.', $this->get_path()));
                    $ex->set_path($this->get_path());
                    throw $ex;
                }
                if (\is_array($v) && ($this->children[$k] ?? null) instanceof self) {
                    // Ensure child's merge is called to handle auto_enable recursively
                    $left_side[$k] = $this->children[$k]->merge([], $v);
                } else {
                    $left_side[$k] = $v;
                }
                continue;
            }
            if (!isset($this->children[$k])) {
                if (!$this->ignore_extra_keys || $this->remove_extra_keys) {
                    throw new \RuntimeException('merge() expects a normalized config array.');
                }
                $left_side[$k] = $v;
                continue;
            }
            $left_side[$k] = $this->children[$k]->merge($left_side[$k], $v);
        }
        return $left_side;
    }
    protected function allow_placeholders(): bool
    {
        return false;
    }
}