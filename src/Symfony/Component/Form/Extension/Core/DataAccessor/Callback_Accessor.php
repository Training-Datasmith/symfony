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
namespace Symfony\Component\Form\Extension\Core\Data_Accessor;

use Symfony\Component\Form\Data_Accessor_Interface;
use Symfony\Component\Form\Exception\Access_Exception;
use Symfony\Component\Form\Form_Interface;
/**
 * Writes and reads values to/from an object or array using callback functions.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class Callback_Accessor implements Data_Accessor_Interface
{
    public function get_value(object|array $data, Form_Interface $form): mixed
    {
        if (null === $getter = $form->get_config()->get_option('getter')) {
            throw new Access_Exception('Unable to read from the given form data as no getter is defined.');
        }
        return $getter($data, $form);
    }
    public function set_value(object|array &$data, mixed $value, Form_Interface $form): void
    {
        if (null === $setter = $form->get_config()->get_option('setter')) {
            throw new Access_Exception('Unable to write the given value as no setter is defined.');
        }
        $setter($data, $form->get_data(), $form);
    }
    public function is_readable(object|array $data, Form_Interface $form): bool
    {
        return null !== $form->get_config()->get_option('getter');
    }
    public function is_writable(object|array $data, Form_Interface $form): bool
    {
        return null !== $form->get_config()->get_option('setter');
    }
}