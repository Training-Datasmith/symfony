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

use Symfony\Component\Config\Definition\Exception\Invalid_Type_Exception;
/**
 * This node represents a scalar value in the config tree.
 *
 * The following values are considered scalars:
 *   * booleans
 *   * strings
 *   * null
 *   * integers
 *   * floats
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Scalar_Node extends Variable_Node
{
    protected function validate_type(mixed $value): void
    {
        if (!\is_scalar($value) && null !== $value) {
            $ex = new Invalid_Type_Exception(\sprintf('Invalid type for path "%s". Expected "scalar", but got "%s".', $this->get_path(), get_debug_type($value)));
            if ($hint = $this->get_info()) {
                $ex->add_hint($hint);
            }
            $ex->set_path($this->get_path());
            throw $ex;
        }
    }
    protected function is_value_empty(mixed $value): bool
    {
        // assume environment variables are never empty (which in practice is likely to be true during runtime)
        // not doing so breaks many configs that are valid today
        if ($this->is_handling_placeholder()) {
            return false;
        }
        return null === $value || '' === $value;
    }
    protected function get_valid_placeholder_types(): array
    {
        return ['bool', 'int', 'float', 'string'];
    }
}