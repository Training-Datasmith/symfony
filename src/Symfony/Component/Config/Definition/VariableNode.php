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
/**
 * This node represents a value of variable type in the config tree.
 *
 * This node is intended for values of arbitrary type.
 * Any PHP type is accepted as a value.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class Variable_Node extends Base_Node implements Prototype_Node_Interface
{
    protected bool $default_value_set = false;
    protected mixed $default_value = null;
    protected bool $allow_empty_value = true;
    public function set_default_value(mixed $value): void
    {
        $this->default_value_set = true;
        $this->default_value = $value;
    }
    public function has_default_value(): bool
    {
        return $this->default_value_set;
    }
    public function get_default_value(): mixed
    {
        $v = $this->default_value;
        return $v instanceof \Closure ? $v() : $v;
    }
    /**
     * Sets if this node is allowed to have an empty value.
     *
     * @param bool $boolean True if this entity will accept empty values
     */
    public function set_allow_empty_value(bool $boolean): void
    {
        $this->allow_empty_value = $boolean;
    }
    public function set_name(string $name): void
    {
        $this->name = $name;
    }
    protected function validate_type(mixed $value): void
    {
    }
    protected function finalize_value(mixed $value): mixed
    {
        // deny environment variables only when using custom validators
        // this avoids ever passing an empty value to final validation closures
        if (!$this->allow_empty_value && $this->is_handling_placeholder() && $this->final_validation_closures) {
            $e = new Invalid_Configuration_Exception(\sprintf('The path "%s" cannot contain an environment variable when empty values are not allowed by definition and are validated.', $this->get_path()));
            if ($hint = $this->get_info()) {
                $e->add_hint($hint);
            }
            $e->set_path($this->get_path());
            throw $e;
        }
        if (!$this->allow_empty_value && $this->is_value_empty($value)) {
            $ex = new Invalid_Configuration_Exception(\sprintf('The path "%s" cannot contain an empty value, but got %s.', $this->get_path(), json_encode($value)));
            if ($hint = $this->get_info()) {
                $ex->add_hint($hint);
            }
            $ex->set_path($this->get_path());
            throw $ex;
        }
        return $value;
    }
    protected function normalize_value(mixed $value): mixed
    {
        return $value;
    }
    protected function merge_values(mixed $left_side, mixed $right_side): mixed
    {
        return $right_side;
    }
    /**
     * Evaluates if the given value is to be treated as empty.
     *
     * By default, PHP's empty() function is used to test for emptiness. This
     * method may be overridden by subtypes to better match their understanding
     * of empty data.
     *
     * @see finalizeValue()
     */
    protected function is_value_empty(mixed $value): bool
    {
        return !$value;
    }
}