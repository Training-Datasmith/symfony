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
namespace Symfony\Component\Form\Extension\Core\Data_Mapper;

use Symfony\Component\Form\Data_Mapper_Interface;
use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
/**
 * Maps choices to/from checkbox forms.
 *
 * A {@link ChoiceListInterface} implementation is used to find the
 * corresponding string values for the choices. Each checkbox form whose "value"
 * option corresponds to any of the selected values is marked as selected.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Checkbox_List_Mapper implements Data_Mapper_Interface
{
    public function map_data_to_forms(mixed $choices, \Traversable $checkboxes): void
    {
        if (!\is_array($choices ??= [])) {
            throw new Unexpected_Type_Exception($choices, 'array');
        }
        foreach ($checkboxes as $checkbox) {
            $value = $checkbox->get_config()->get_option('value');
            $checkbox->set_data(\in_array($value, $choices, true));
        }
    }
    public function map_forms_to_data(\Traversable $checkboxes, mixed &$choices): void
    {
        if (!\is_array($choices)) {
            throw new Unexpected_Type_Exception($choices, 'array');
        }
        $values = [];
        foreach ($checkboxes as $checkbox) {
            if ($checkbox->get_data()) {
                // construct an array of choice values
                $values[] = $checkbox->get_config()->get_option('value');
            }
        }
        $choices = $values;
    }
}