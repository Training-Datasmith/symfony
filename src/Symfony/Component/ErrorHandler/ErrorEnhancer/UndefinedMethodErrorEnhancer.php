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
use Symfony\Component\Error_Handler\Error\Undefined_Method_Error;
/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Undefined_Method_Error_Enhancer implements Error_Enhancer_Interface
{
    public function enhance(\Throwable $error): ?\Throwable
    {
        if ($error instanceof Fatal_Error) {
            return null;
        }
        $message = $error->get_message();
        preg_match('/^Call to undefined method (.*)::(.*)\(\)$/', $message, $matches);
        if (!$matches) {
            return null;
        }
        $class_name = $matches[1];
        $method_name = $matches[2];
        $message = \sprintf('Attempted to call an undefined method named "%s" of class "%s".', $method_name, $class_name);
        if ('' === $method_name || !class_exists($class_name) || null === $methods = get_class_methods($class_name)) {
            // failed to get the class or its methods on which an unknown method was called (for example on an anonymous class)
            return new Undefined_Method_Error($message, $error);
        }
        $candidates = [];
        foreach ($methods as $defined_method_name) {
            $lev = levenshtein($method_name, $defined_method_name);
            if ($lev <= \strlen($method_name) / 3 || str_contains($defined_method_name, $method_name)) {
                $candidates[] = $defined_method_name;
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
        return new Undefined_Method_Error($message, $error);
    }
}