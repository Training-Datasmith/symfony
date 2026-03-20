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
namespace Symfony\Component\Form\Choice_List;

use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Attr;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Field_Name;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Filter;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Label;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Loader;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Translation_Parameters;
use Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Value;
use Symfony\Component\Form\Choice_List\Factory\Cache\Group_By;
use Symfony\Component\Form\Choice_List\Factory\Cache\Preferred_Choice;
use Symfony\Component\Form\Choice_List\Loader\Callback_Choice_Loader;
use Symfony\Component\Form\Choice_List\Loader\Choice_Loader_Interface;
use Symfony\Component\Form\Form_Type_Extension_Interface;
use Symfony\Component\Form\Form_Type_Interface;
/**
 * A set of convenient static methods to create cacheable choice list options.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
final class Choice_List
{
    /**
     * Creates a cacheable loader from any callable providing iterable choices.
     *
     * @param callable $choices A callable that must return iterable choices or grouped choices
     * @param mixed    $vary    Dynamic data used to compute a unique hash when caching the loader
     */
    public static function lazy(Form_Type_Interface|Form_Type_Extension_Interface $form_type, callable $choices, mixed $vary = null): Choice_Loader
    {
        return self::loader($form_type, new Callback_Choice_Loader($choices), $vary);
    }
    /**
     * Decorates a loader to make it cacheable.
     *
     * @param ChoiceLoaderInterface $loader A loader responsible for creating loading choices or grouped choices
     * @param mixed                 $vary   Dynamic data used to compute a unique hash when caching the loader
     */
    public static function loader(Form_Type_Interface|Form_Type_Extension_Interface $form_type, Choice_Loader_Interface $loader, mixed $vary = null): Choice_Loader
    {
        return new Choice_Loader($form_type, $loader, $vary);
    }
    /**
     * Decorates a "choice_value" callback to make it cacheable.
     *
     * @param callable|array $value Any pseudo callable to create a unique string value from a choice
     * @param mixed          $vary  Dynamic data used to compute a unique hash when caching the callback
     */
    public static function value(Form_Type_Interface|Form_Type_Extension_Interface $form_type, callable|array $value, mixed $vary = null): Choice_Value
    {
        return new Choice_Value($form_type, $value, $vary);
    }
    /**
     * @param callable|array $filter Any pseudo callable to filter a choice list
     * @param mixed          $vary   Dynamic data used to compute a unique hash when caching the callback
     */
    public static function filter(Form_Type_Interface|Form_Type_Extension_Interface $form_type, callable|array $filter, mixed $vary = null): Choice_Filter
    {
        return new Choice_Filter($form_type, $filter, $vary);
    }
    /**
     * Decorates a "choice_label" option to make it cacheable.
     *
     * @param callable|false $label Any pseudo callable to create a label from a choice or false to discard it
     * @param mixed          $vary  Dynamic data used to compute a unique hash when caching the option
     */
    public static function label(Form_Type_Interface|Form_Type_Extension_Interface $form_type, callable|false $label, mixed $vary = null): Choice_Label
    {
        return new Choice_Label($form_type, $label, $vary);
    }
    /**
     * Decorates a "choice_name" callback to make it cacheable.
     *
     * @param callable|array $fieldName Any pseudo callable to create a field name from a choice
     * @param mixed          $vary      Dynamic data used to compute a unique hash when caching the callback
     */
    public static function field_name(Form_Type_Interface|Form_Type_Extension_Interface $form_type, callable|array $field_name, mixed $vary = null): Choice_Field_Name
    {
        return new Choice_Field_Name($form_type, $field_name, $vary);
    }
    /**
     * Decorates a "choice_attr" option to make it cacheable.
     *
     * @param callable|array $attr Any pseudo callable or array to create html attributes from a choice
     * @param mixed          $vary Dynamic data used to compute a unique hash when caching the option
     */
    public static function attr(Form_Type_Interface|Form_Type_Extension_Interface $form_type, callable|array $attr, mixed $vary = null): Choice_Attr
    {
        return new Choice_Attr($form_type, $attr, $vary);
    }
    /**
     * Decorates a "choice_translation_parameters" option to make it cacheable.
     *
     * @param callable|array $translationParameters Any pseudo callable or array to create translation parameters from a choice
     * @param mixed          $vary                  Dynamic data used to compute a unique hash when caching the option
     */
    public static function translation_parameters(Form_Type_Interface|Form_Type_Extension_Interface $form_type, callable|array $translation_parameters, mixed $vary = null): Choice_Translation_Parameters
    {
        return new Choice_Translation_Parameters($form_type, $translation_parameters, $vary);
    }
    /**
     * Decorates a "group_by" callback to make it cacheable.
     *
     * @param callable|array $groupBy Any pseudo callable to return a group name from a choice
     * @param mixed          $vary    Dynamic data used to compute a unique hash when caching the callback
     */
    public static function group_by(Form_Type_Interface|Form_Type_Extension_Interface $form_type, callable|array $group_by, mixed $vary = null): Group_By
    {
        return new Group_By($form_type, $group_by, $vary);
    }
    /**
     * Decorates a "preferred_choices" option to make it cacheable.
     *
     * @param callable|array $preferred Any pseudo callable or array to return a group name from a choice
     * @param mixed          $vary      Dynamic data used to compute a unique hash when caching the option
     */
    public static function preferred(Form_Type_Interface|Form_Type_Extension_Interface $form_type, callable|array $preferred, mixed $vary = null): Preferred_Choice
    {
        return new Preferred_Choice($form_type, $preferred, $vary);
    }
    /**
     * Should not be instantiated.
     */
    private function __construct()
    {
    }
}