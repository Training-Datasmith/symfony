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
namespace Symfony\Component\Form;

/**
 * Writes and reads values to/from an object or array bound to a form.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
interface Data_Accessor_Interface
{
    /**
     * Returns the value at the end of the property of the object graph.
     *
     * @throws Exception\AccessException If unable to read from the given form data
     */
    public function get_value(object|array $view_data, Form_Interface $form): mixed;
    /**
     * Sets the value at the end of the property of the object graph.
     *
     * @throws Exception\AccessException If unable to write the given value
     */
    public function set_value(object|array &$view_data, mixed $value, Form_Interface $form): void;
    /**
     * Returns whether a value can be read from an object graph.
     *
     * Whenever this method returns true, {@link getValue()} is guaranteed not
     * to throw an exception when called with the same arguments.
     */
    public function is_readable(object|array $view_data, Form_Interface $form): bool;
    /**
     * Returns whether a value can be written at a given object graph.
     *
     * Whenever this method returns true, {@link setValue()} is guaranteed not
     * to throw an exception when called with the same arguments.
     */
    public function is_writable(object|array $view_data, Form_Interface $form): bool;
}