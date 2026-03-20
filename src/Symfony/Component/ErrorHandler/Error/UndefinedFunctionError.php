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
namespace Symfony\Component\Error_Handler\Error;

class Undefined_Function_Error extends \Error
{
    public function __construct(string $message, \Throwable $previous)
    {
        parent::__construct($message, $previous->get_code(), $previous->get_previous());
        foreach (['file' => $previous->get_file(), 'line' => $previous->get_line(), 'trace' => $previous->get_trace()] as $property => $value) {
            $refl = new \ReflectionProperty(\Error::class, $property);
            $refl->set_value($this, $value);
        }
    }
}