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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
/**
 * Checks if arguments of methods are properly configured.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Check_Arguments_Validity_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    public function __construct(private readonly bool $throw_exceptions = true)
    {
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if (!$value instanceof Definition) {
            return parent::process_value($value, $is_root);
        }
        $i = 0;
        $has_named_args = false;
        foreach ($value->get_arguments() as $k => $v) {
            if (preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', (string) $k)) {
                $has_named_args = true;
                continue;
            }
            if ($k !== $i++) {
                if (!\is_int($k)) {
                    $msg = \sprintf('Invalid constructor argument for service "%s": integer expected but found string "%s". Check your service definition.', $this->current_id, $k);
                    $value->add_error($msg);
                    if ($this->throw_exceptions) {
                        throw new RuntimeException($msg);
                    }
                    break;
                }
                $msg = \sprintf('Invalid constructor argument %d for service "%s": argument %d must be defined before. Check your service definition.', 1 + $k, $this->current_id, $i);
                $value->add_error($msg);
                if ($this->throw_exceptions) {
                    throw new RuntimeException($msg);
                }
            }
            if ($has_named_args) {
                $msg = \sprintf('Invalid constructor argument for service "%s": cannot use positional argument after named argument. Check your service definition.', $this->current_id);
                $value->add_error($msg);
                if ($this->throw_exceptions) {
                    throw new RuntimeException($msg);
                }
                break;
            }
        }
        foreach ($value->get_method_calls() as $method_call) {
            $i = 0;
            $has_named_args = false;
            foreach ($method_call[1] as $k => $v) {
                if (preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', (string) $k)) {
                    $has_named_args = true;
                    continue;
                }
                if ($k !== $i++) {
                    if (!\is_int($k)) {
                        $msg = \sprintf('Invalid argument for method call "%s" of service "%s": integer expected but found string "%s". Check your service definition.', $method_call[0], $this->current_id, $k);
                        $value->add_error($msg);
                        if ($this->throw_exceptions) {
                            throw new RuntimeException($msg);
                        }
                        break;
                    }
                    $msg = \sprintf('Invalid argument %d for method call "%s" of service "%s": argument %d must be defined before. Check your service definition.', 1 + $k, $method_call[0], $this->current_id, $i);
                    $value->add_error($msg);
                    if ($this->throw_exceptions) {
                        throw new RuntimeException($msg);
                    }
                }
                if ($has_named_args) {
                    $msg = \sprintf('Invalid argument for method call "%s" of service "%s": cannot use positional argument after named argument. Check your service definition.', $method_call[0], $this->current_id);
                    $value->add_error($msg);
                    if ($this->throw_exceptions) {
                        throw new RuntimeException($msg);
                    }
                    break;
                }
            }
        }
        return null;
    }
}