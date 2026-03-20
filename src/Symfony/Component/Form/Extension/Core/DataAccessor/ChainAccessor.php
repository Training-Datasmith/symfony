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
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class Chain_Accessor implements Data_Accessor_Interface
{
    /**
     * @param DataAccessorInterface[]|iterable $accessors
     */
    public function __construct(private readonly iterable $accessors)
    {
    }
    public function get_value(object|array $data, Form_Interface $form): mixed
    {
        foreach ($this->accessors as $accessor) {
            if ($accessor->is_readable($data, $form)) {
                return $accessor->get_value($data, $form);
            }
        }
        throw new Access_Exception('Unable to read from the given form data as no accessor in the chain is able to read the data.');
    }
    public function set_value(object|array &$data, mixed $value, Form_Interface $form): void
    {
        foreach ($this->accessors as $accessor) {
            if ($accessor->is_writable($data, $form)) {
                $accessor->set_value($data, $value, $form);
                return;
            }
        }
        throw new Access_Exception('Unable to write the given value as no accessor in the chain is able to set the data.');
    }
    public function is_readable(object|array $data, Form_Interface $form): bool
    {
        foreach ($this->accessors as $accessor) {
            if ($accessor->is_readable($data, $form)) {
                return true;
            }
        }
        return false;
    }
    public function is_writable(object|array $data, Form_Interface $form): bool
    {
        foreach ($this->accessors as $accessor) {
            if ($accessor->is_writable($data, $form)) {
                return true;
            }
        }
        return false;
    }
}