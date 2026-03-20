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
namespace Symfony\Component\Error_Handler\Error_Enhancer;

use Symfony\Component\Error_Handler\Error\Fatal_Error;
use Symfony\Component\Error_Handler\Error\Undefined_Function_Error;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Undefined_Function_Error_Enhancer implements Error_Enhancer_Interface
{
    public function enhance(\Throwable $error): ?\Throwable
    {
        if ($error instanceof Fatal_Error) {
            return null;
        }
        $message = $error->get_message();
        $message_len = \strlen($message);
        $not_found_suffix = '()';
        $not_found_suffix_len = \strlen($not_found_suffix);
        if ($not_found_suffix_len > $message_len) {
            return null;
        }
        if (0 !== substr_compare($message, $not_found_suffix, -$not_found_suffix_len)) {
            return null;
        }
        $prefix = 'Call to undefined function ';
        $prefix_len = \strlen($prefix);
        if (!str_starts_with($message, $prefix)) {
            return null;
        }
        $fully_qualified_function_name = substr($message, $prefix_len, -$not_found_suffix_len);
        if (false !== $namespace_separator_index = strrpos($fully_qualified_function_name, '\\')) {
            $function_name = substr($fully_qualified_function_name, $namespace_separator_index + 1);
            $namespace_prefix = substr($fully_qualified_function_name, 0, $namespace_separator_index);
            $message = \sprintf('Attempted to call undefined function "%s" from namespace "%s".', $function_name, $namespace_prefix);
        } else {
            $function_name = $fully_qualified_function_name;
            $message = \sprintf('Attempted to call undefined function "%s" from the global namespace.', $function_name);
        }
        $candidates = [];
        foreach (get_defined_functions() as $defined_function_names) {
            foreach ($defined_function_names as $defined_function_name) {
                if (false !== $namespace_separator_index = strrpos($defined_function_name, '\\')) {
                    $defined_function_name_basename = substr($defined_function_name, $namespace_separator_index + 1);
                } else {
                    $defined_function_name_basename = $defined_function_name;
                }
                if ($defined_function_name_basename === $function_name) {
                    $candidates[] = '\\' . $defined_function_name;
                }
            }
        }
        if ($candidates) {
            sort($candidates);
            $last = array_pop($candidates) . '"?';
            if ($candidates) {
                $candidates = 'e.g. "' . implode('", "', $candidates) . '" or "' . $last;
            } else {
                $candidates = '"' . $last;
            }
            $message .= "\nDid you mean to call " . $candidates;
        }
        return new Undefined_Function_Error($message, $error);
    }
}