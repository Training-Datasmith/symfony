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
 * Maps choices to/from radio forms.
 *
 * A {@link ChoiceListInterface} implementation is used to find the
 * corresponding string values for the choices. The radio form whose "value"
 * option corresponds to the selected value is marked as selected.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Radio_List_Mapper implements Data_Mapper_Interface
{
    public function map_data_to_forms(mixed $choice, \Traversable $radios): void
    {
        if (!\is_string($choice)) {
            throw new Unexpected_Type_Exception($choice, 'string');
        }
        foreach ($radios as $radio) {
            $value = $radio->get_config()->get_option('value');
            $radio->set_data($choice === $value);
        }
    }
    public function map_forms_to_data(\Traversable $radios, mixed &$choice): void
    {
        if (null !== $choice && !\is_string($choice)) {
            throw new Unexpected_Type_Exception($choice, 'null or string');
        }
        $choice = null;
        foreach ($radios as $radio) {
            if ($radio->get_data()) {
                if ('placeholder' === $radio->get_name()) {
                    return;
                }
                $choice = $radio->get_config()->get_option('value');
                return;
            }
        }
    }
}