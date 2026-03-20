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
 * This node represents a numeric value in the config tree.
 *
 * @author David Jeanmonod <david.jeanmonod@gmail.com>
 */
class Numeric_Node extends Scalar_Node
{
    public function __construct(?string $name, ?Node_Interface $parent = null, protected int|float|null $min = null, protected int|float|null $max = null, string $path_separator = Base_Node::DEFAULT_PATH_SEPARATOR)
    {
        parent::__construct($name, $parent, $path_separator);
    }
    protected function finalize_value(mixed $value): mixed
    {
        $value = parent::finalize_value($value);
        $error_msg = null;
        if (isset($this->min) && $value < $this->min) {
            $error_msg = \sprintf('The value %s is too small for path "%s". Should be greater than or equal to %s', $value, $this->get_path(), $this->min);
        }
        if (isset($this->max) && $value > $this->max) {
            $error_msg = \sprintf('The value %s is too big for path "%s". Should be less than or equal to %s', $value, $this->get_path(), $this->max);
        }
        if (isset($error_msg)) {
            $ex = new Invalid_Configuration_Exception($error_msg);
            $ex->set_path($this->get_path());
            throw $ex;
        }
        return $value;
    }
    public function get_min(): float|int|null
    {
        return $this->min;
    }
    public function get_max(): float|int|null
    {
        return $this->max;
    }
    protected function is_value_empty(mixed $value): bool
    {
        // a numeric value cannot be empty
        return false;
    }
}