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
 * This node represents an integer value in the config tree.
 *
 * @author Jeanmonod David <david.jeanmonod@gmail.com>
 */
class Integer_Node extends Numeric_Node
{
    protected function validate_type(mixed $value): void
    {
        if (!\is_int($value)) {
            $ex = new Invalid_Type_Exception(\sprintf('Invalid type for path "%s". Expected "int", but got "%s".', $this->get_path(), get_debug_type($value)));
            if ($hint = $this->get_info()) {
                $ex->add_hint($hint);
            }
            $ex->set_path($this->get_path());
            throw $ex;
        }
    }
    protected function get_valid_placeholder_types(): array
    {
        return ['int'];
    }
}