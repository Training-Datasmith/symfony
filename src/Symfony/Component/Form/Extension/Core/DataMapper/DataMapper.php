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

use Symfony\Component\Form\Data_Accessor_Interface;
use Symfony\Component\Form\Data_Mapper_Interface;
use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
use Symfony\Component\Form\Extension\Core\Data_Accessor\Callback_Accessor;
use Symfony\Component\Form\Extension\Core\Data_Accessor\Chain_Accessor;
use Symfony\Component\Form\Extension\Core\Data_Accessor\Property_Path_Accessor;
/**
 * Maps arrays/objects to/from forms using data accessors.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Data_Mapper implements Data_Mapper_Interface
{
    public function __construct(private readonly ?Data_Accessor_Interface $data_accessor = new Chain_Accessor([new Callback_Accessor(), new Property_Path_Accessor()]))
    {
    }
    public function map_data_to_forms(mixed $data, \Traversable $forms): void
    {
        $empty = null === $data || [] === $data;
        if (!$empty && !\is_array($data) && !\is_object($data)) {
            throw new Unexpected_Type_Exception($data, 'object, array or empty');
        }
        foreach ($forms as $form) {
            $config = $form->get_config();
            if (!$empty && $config->get_mapped() && $this->data_accessor->is_readable($data, $form)) {
                $form->set_data($this->data_accessor->get_value($data, $form));
            } else {
                $form->set_data($config->get_data());
            }
        }
    }
    public function map_forms_to_data(\Traversable $forms, mixed &$data): void
    {
        if (null === $data) {
            return;
        }
        if (!\is_array($data) && !\is_object($data)) {
            throw new Unexpected_Type_Exception($data, 'object, array or empty');
        }
        foreach ($forms as $form) {
            $config = $form->get_config();
            // Write-back is disabled if the form is not synchronized (transformation failed),
            // if the form was not submitted and if the form is disabled (modification not allowed)
            if ($config->get_mapped() && $form->is_submitted() && $form->is_synchronized() && !$form->is_disabled() && $this->data_accessor->is_writable($data, $form)) {
                $this->data_accessor->set_value($data, $form->get_data(), $form);
            }
        }
    }
    /**
     * @internal
     */
    public function get_data_accessor(): Data_Accessor_Interface
    {
        return $this->data_accessor;
    }
}