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
 * This node represents a Boolean value in the config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Boolean_Node extends Scalar_Node
{
    public function __construct(?string $name, ?Node_Interface $parent = null, string $path_separator = self::DEFAULT_PATH_SEPARATOR, private readonly bool $nullable = false)
    {
        parent::__construct($name, $parent, $path_separator);
    }
    protected function validate_type(mixed $value): void
    {
        if (!\is_bool($value)) {
            if (null === $value && $this->nullable) {
                return;
            }
            $ex = new Invalid_Type_Exception(\sprintf('Invalid type for path "%s". Expected "bool%s", but got "%s".', $this->get_path(), $this->nullable ? '" or "null' : '', get_debug_type($value)));
            if ($hint = $this->get_info()) {
                $ex->add_hint($hint);
            }
            $ex->set_path($this->get_path());
            throw $ex;
        }
    }
    protected function is_value_empty(mixed $value): bool
    {
        // a boolean value cannot be empty
        return false;
    }
    protected function get_valid_placeholder_types(): array
    {
        return ['bool'];
    }
}