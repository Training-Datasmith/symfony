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
use Symfony\Component\Form\Data_Mapper_Interface;
use Symfony\Component\Form\Exception\Access_Exception;
use Symfony\Component\Form\Extension\Core\Data_Mapper\Data_Mapper;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Property_Access\Exception\Access_Exception as PropertyAccessException;
use Symfony\Component\Property_Access\Exception\No_Such_Index_Exception;
use Symfony\Component\Property_Access\Exception\No_Such_Property_Exception;
use Symfony\Component\Property_Access\Exception\Uninitialized_Property_Exception;
use Symfony\Component\Property_Access\Property_Access;
use Symfony\Component\Property_Access\Property_Accessor_Interface;
use Symfony\Component\Property_Access\Property_Path_Interface;
/**
 * Writes and reads values to/from an object or array using property path.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Property_Path_Accessor implements Data_Accessor_Interface
{
    private readonly Property_Accessor_Interface $property_accessor;
    public function __construct(?Property_Accessor_Interface $property_accessor = null)
    {
        $this->property_accessor = $property_accessor ?? Property_Access::create_property_accessor();
    }
    public function get_value(object|array $data, Form_Interface $form): mixed
    {
        if (null === $property_path = $form->get_property_path()) {
            throw new Access_Exception('Unable to read from the given form data as no property path is defined.');
        }
        return $this->get_property_value($data, $property_path);
    }
    public function set_value(object|array &$data, mixed $value, Form_Interface $form): void
    {
        if (null === $property_path = $form->get_property_path()) {
            throw new Access_Exception('Unable to write the given value as no property path is defined.');
        }
        $get_value = function () use ($data, $form, $property_path) {
            $data_mapper = $this->get_data_mapper($form);
            if ($data_mapper instanceof Data_Mapper && null !== $data_accessor = $data_mapper->get_data_accessor()) {
                return $data_accessor->get_value($data, $form);
            }
            return $this->get_property_value($data, $property_path);
        };
        // If the field is of type DateTimeInterface and the data is the same skip the update to
        // keep the original object hash
        if ($value instanceof \DateTimeInterface && $value == $get_value()) {
            return;
        }
        // If the data is identical to the value in $data, we are
        // dealing with a reference
        if (!\is_object($data) || !$form->get_config()->get_by_reference() || $value !== $get_value()) {
            try {
                $this->property_accessor->set_value($data, $property_path, $value);
            } catch (No_Such_Property_Exception $e) {
                throw new No_Such_Property_Exception($e->get_message() . ' Make the property public, add a setter, or set the "mapped" field option in the form type to be false.', 0, $e);
            }
        }
    }
    public function is_readable(object|array $data, Form_Interface $form): bool
    {
        return null !== $form->get_property_path();
    }
    public function is_writable(object|array $data, Form_Interface $form): bool
    {
        return null !== $form->get_property_path();
    }
    private function get_property_value(object|array $data, Property_Path_Interface $property_path): mixed
    {
        try {
            return $this->property_accessor->get_value($data, $property_path);
        } catch (Property_Access_Exception $e) {
            if (\is_array($data) && $e instanceof No_Such_Index_Exception) {
                return null;
            }
            if (!$e instanceof Uninitialized_Property_Exception) {
                throw $e;
            }
            return null;
        }
    }
    private function get_data_mapper(Form_Interface $form): ?Data_Mapper_Interface
    {
        do {
            $data_mapper = $form->get_config()->get_data_mapper();
        } while (null === $data_mapper && null !== $form = $form->get_parent());
        return $data_mapper;
    }
}