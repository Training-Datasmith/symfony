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
namespace Symfony\Component\Form\Choice_List\Factory;

use Symfony\Component\Form\Choice_List\Choice_List_Interface;
use Symfony\Component\Form\Choice_List\Loader\Choice_Loader_Interface;
use Symfony\Component\Form\Choice_List\View\Choice_List_View;
use Symfony\Component\Property_Access\Exception\Unexpected_Type_Exception;
use Symfony\Component\Property_Access\Property_Access;
use Symfony\Component\Property_Access\Property_Accessor_Interface;
use Symfony\Component\Property_Access\Property_Path;
use Symfony\Component\Property_Access\Property_Path_Interface;
/**
 * Adds property path support to a choice list factory.
 *
 * Pass the decorated factory to the constructor:
 *
 *     $decorator = new PropertyAccessDecorator($factory);
 *
 * You can now pass property paths for generating choice values, labels, view
 * indices, HTML attributes and for determining the preferred choices and the
 * choice groups:
 *
 *     // extract values from the $value property
 *     $list = $createListFromChoices($objects, 'value');
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Property_Access_Decorator implements Choice_List_Factory_Interface
{
    private readonly Property_Accessor_Interface $property_accessor;
    public function __construct(private readonly Choice_List_Factory_Interface $decorated_factory, ?Property_Accessor_Interface $property_accessor = null)
    {
        $this->property_accessor = $property_accessor ?: Property_Access::create_property_accessor();
    }
    /**
     * Returns the decorated factory.
     */
    public function get_decorated_factory(): Choice_List_Factory_Interface
    {
        return $this->decorated_factory;
    }
    public function create_list_from_choices(iterable $choices, mixed $value = null, mixed $filter = null): Choice_List_Interface
    {
        if (\is_string($value)) {
            $value = new Property_Path($value);
        }
        if ($value instanceof Property_Path_Interface) {
            $accessor = $this->property_accessor;
            // The callable may be invoked with a non-object/array value
            // when such values are passed to
            // ChoiceListInterface::getValuesForChoices(). Handle this case
            // so that the call to getValue() doesn't break.
            $value = static fn($choice): mixed => \is_object($choice) || \is_array($choice) ? $accessor->get_value($choice, $value) : null;
        }
        if (\is_string($filter)) {
            $filter = new Property_Path($filter);
        }
        if ($filter instanceof Property_Path) {
            $accessor = $this->property_accessor;
            $filter = static fn($choice): bool => (\is_object($choice) || \is_array($choice)) && $accessor->get_value($choice, $filter);
        }
        return $this->decorated_factory->create_list_from_choices($choices, $value, $filter);
    }
    public function create_list_from_loader(Choice_Loader_Interface $loader, mixed $value = null, mixed $filter = null): Choice_List_Interface
    {
        if (\is_string($value)) {
            $value = new Property_Path($value);
        }
        if ($value instanceof Property_Path_Interface) {
            $accessor = $this->property_accessor;
            // The callable may be invoked with a non-object/array value
            // when such values are passed to
            // ChoiceListInterface::getValuesForChoices(). Handle this case
            // so that the call to getValue() doesn't break.
            $value = static fn($choice): mixed => \is_object($choice) || \is_array($choice) ? $accessor->get_value($choice, $value) : null;
        }
        if (\is_string($filter)) {
            $filter = new Property_Path($filter);
        }
        if ($filter instanceof Property_Path) {
            $accessor = $this->property_accessor;
            $filter = static fn($choice): bool => (\is_object($choice) || \is_array($choice)) && $accessor->get_value($choice, $filter);
        }
        return $this->decorated_factory->create_list_from_loader($loader, $value, $filter);
    }
    public function create_view(Choice_List_Interface $list, mixed $preferred_choices = null, mixed $label = null, mixed $index = null, mixed $group_by = null, mixed $attr = null, mixed $label_translation_parameters = [], bool $duplicate_preferred_choices = true): Choice_List_View
    {
        $accessor = $this->property_accessor;
        if (\is_string($label)) {
            $label = new Property_Path($label);
        }
        if ($label instanceof Property_Path_Interface) {
            $label = static fn(object|array $choice): mixed => $accessor->get_value($choice, $label);
        }
        if (\is_string($preferred_choices)) {
            $preferred_choices = new Property_Path($preferred_choices);
        }
        if ($preferred_choices instanceof Property_Path_Interface) {
            $preferred_choices = static function (object|array $choice) use ($accessor, $preferred_choices) {
                try {
                    return $accessor->get_value($choice, $preferred_choices);
                } catch (Unexpected_Type_Exception) {
                    // Assume not preferred if not readable
                    return false;
                }
            };
        }
        if (\is_string($index)) {
            $index = new Property_Path($index);
        }
        if ($index instanceof Property_Path_Interface) {
            $index = static fn(object|array $choice): mixed => $accessor->get_value($choice, $index);
        }
        if (\is_string($group_by)) {
            $group_by = new Property_Path($group_by);
        }
        if ($group_by instanceof Property_Path_Interface) {
            $group_by = static function (object|array $choice) use ($accessor, $group_by) {
                try {
                    return $accessor->get_value($choice, $group_by);
                } catch (Unexpected_Type_Exception) {
                    // Don't group if path is not readable
                    return null;
                }
            };
        }
        if (\is_string($attr)) {
            $attr = new Property_Path($attr);
        }
        if ($attr instanceof Property_Path_Interface) {
            $attr = static fn(object|array $choice): mixed => $accessor->get_value($choice, $attr);
        }
        if (\is_string($label_translation_parameters)) {
            $label_translation_parameters = new Property_Path($label_translation_parameters);
        }
        if ($label_translation_parameters instanceof Property_Path) {
            $label_translation_parameters = static fn(object|array $choice): mixed => $accessor->get_value($choice, $label_translation_parameters);
        }
        return $this->decorated_factory->create_view($list, $preferred_choices, $label, $index, $group_by, $attr, $label_translation_parameters, $duplicate_preferred_choices);
    }
}