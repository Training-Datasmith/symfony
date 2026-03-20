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

use Symfony\Component\Form\Choice_List\Array_Choice_List;
use Symfony\Component\Form\Choice_List\Choice_List_Interface;
use Symfony\Component\Form\Choice_List\Lazy_Choice_List;
use Symfony\Component\Form\Choice_List\Loader\Callback_Choice_Loader;
use Symfony\Component\Form\Choice_List\Loader\Choice_Loader_Interface;
use Symfony\Component\Form\Choice_List\Loader\Filter_Choice_Loader_Decorator;
use Symfony\Component\Form\Choice_List\View\Choice_Group_View;
use Symfony\Component\Form\Choice_List\View\Choice_List_View;
use Symfony\Component\Form\Choice_List\View\Choice_View;
use Symfony\Contracts\Translation\Translatable_Interface;
/**
 * Default implementation of {@link ChoiceListFactoryInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Jules Pietri <jules@heahprod.com>
 */
class Default_Choice_List_Factory implements Choice_List_Factory_Interface
{
    public function create_list_from_choices(iterable $choices, ?callable $value = null, ?callable $filter = null): Choice_List_Interface
    {
        if ($filter) {
            // filter the choice list lazily
            return $this->create_list_from_loader(new Filter_Choice_Loader_Decorator(new Callback_Choice_Loader(static fn(): iterable => $choices), $filter), $value);
        }
        return new Array_Choice_List($choices, $value);
    }
    public function create_list_from_loader(Choice_Loader_Interface $loader, ?callable $value = null, ?callable $filter = null): Choice_List_Interface
    {
        if ($filter) {
            $loader = new Filter_Choice_Loader_Decorator($loader, $filter);
        }
        return new Lazy_Choice_List($loader, $value);
    }
    public function create_view(Choice_List_Interface $list, array|callable|null $preferred_choices = null, callable|false|null $label = null, ?callable $index = null, ?callable $group_by = null, array|callable|null $attr = null, array|callable $label_translation_parameters = [], bool $duplicate_preferred_choices = true): Choice_List_View
    {
        $preferred_views = [];
        $preferred_views_order = [];
        $other_views = [];
        $choices = $list->get_choices();
        $keys = $list->get_original_keys();
        if (!\is_callable($preferred_choices)) {
            if (!$preferred_choices) {
                $preferred_choices = null;
            } else {
                // make sure we have keys that reflect order
                $preferred_choices = array_values($preferred_choices);
                $preferred_choices = static fn($choice): int|false => array_search($choice, $preferred_choices, true);
            }
        }
        // The names are generated from an incrementing integer by default
        $index ??= 0;
        // If $groupBy is a callable returning a string
        // choices are added to the group with the name returned by the callable.
        // If $groupBy is a callable returning an array
        // choices are added to the groups with names returned by the callable
        // If the callable returns null, the choice is not added to any group
        if (\is_callable($group_by)) {
            foreach ($choices as $value => $choice) {
                self::add_choice_views_grouped_by_callable($group_by, $choice, $value, $label, $keys, $index, $attr, $label_translation_parameters, $preferred_choices, $preferred_views, $preferred_views_order, $other_views, $duplicate_preferred_choices);
            }
            // Remove empty group views that may have been created by
            // addChoiceViewsGroupedByCallable()
            foreach ($preferred_views as $key => $view) {
                if ($view instanceof Choice_Group_View && 0 === \count($view->choices)) {
                    unset($preferred_views[$key]);
                }
            }
            foreach ($other_views as $key => $view) {
                if ($view instanceof Choice_Group_View && 0 === \count($view->choices)) {
                    unset($other_views[$key]);
                }
            }
            foreach ($preferred_views_order as $key => $group_views_order) {
                if ($group_views_order) {
                    $preferred_views_order[$key] = min($group_views_order);
                } else {
                    unset($preferred_views_order[$key]);
                }
            }
        } else {
            // Otherwise use the original structure of the choices
            self::add_choice_views_from_structured_values($list->get_structured_values(), $label, $choices, $keys, $index, $attr, $label_translation_parameters, $preferred_choices, $preferred_views, $preferred_views_order, $other_views, $duplicate_preferred_choices);
        }
        uksort($preferred_views, static fn($a, $b): int => isset($preferred_views_order[$a], $preferred_views_order[$b]) ? $preferred_views_order[$a] <=> $preferred_views_order[$b] : 0);
        return new Choice_List_View($other_views, $preferred_views);
    }
    private static function add_choice_view($choice, string $value, $label, array $keys, &$index, array $attr, array $label_translation_parameters, ?callable $is_preferred, array &$preferred_views, array &$preferred_views_order, array &$other_views, bool $duplicate_preferred_choices): void
    {
        // $value may be an integer or a string, since it's stored in the array
        // keys. We want to guarantee it's a string though.
        $key = $keys[$value];
        $next_index = \is_int($index) ? $index++ : $index($choice, $key, $value);
        // BC normalize label to accept a false value
        if (null === $label) {
            // If the labels are null, use the original choice key by default
            $label = (string) $key;
        } elseif (false !== $label) {
            // If "choice_label" is set to false and "expanded" is true, the value false
            // should be passed on to the "label" option of the checkboxes/radio buttons
            $dynamic_label = $label($choice, $key, $value);
            if (false === $dynamic_label) {
                $label = false;
            } elseif ($dynamic_label instanceof Translatable_Interface) {
                $label = $dynamic_label;
            } else {
                $label = (string) $dynamic_label;
            }
        }
        $view = new Choice_View(
            $choice,
            $value,
            $label,
            // The attributes may be a callable or a mapping from choice indices
            // to nested arrays
            \is_callable($attr) ? $attr($choice, $key, $value) : $attr[$key] ?? [],
            // The label translation parameters may be a callable or a mapping from choice indices
            // to nested arrays
            \is_callable($label_translation_parameters) ? $label_translation_parameters($choice, $key, $value) : $label_translation_parameters[$key] ?? []
        );
        // $isPreferred may be null if no choices are preferred
        if (null !== $is_preferred && false !== $preferred_key = $is_preferred($choice, $key, $value)) {
            $preferred_views[$next_index] = $view;
            $preferred_views_order[$next_index] = $preferred_key;
            if ($duplicate_preferred_choices) {
                $other_views[$next_index] = $view;
            }
        } else {
            $other_views[$next_index] = $view;
        }
    }
    private static function add_choice_views_from_structured_values(array $values, $label, array $choices, array $keys, &$index, $attr, $label_translation_parameters, ?callable $is_preferred, array &$preferred_views, array &$preferred_views_order, array &$other_views, bool $duplicate_preferred_choices): void
    {
        foreach ($values as $key => $value) {
            if (null === $value) {
                continue;
            }
            // Add the contents of groups to new ChoiceGroupView instances
            if (\is_array($value)) {
                $preferred_views_for_group = [];
                $other_views_for_group = [];
                self::add_choice_views_from_structured_values($value, $label, $choices, $keys, $index, $attr, $label_translation_parameters, $is_preferred, $preferred_views_for_group, $preferred_views_order, $other_views_for_group, $duplicate_preferred_choices);
                if (\count($preferred_views_for_group) > 0) {
                    $preferred_views[$key] = new Choice_Group_View($key, $preferred_views_for_group);
                }
                if (\count($other_views_for_group) > 0) {
                    $other_views[$key] = new Choice_Group_View($key, $other_views_for_group);
                }
                continue;
            }
            // Add ungrouped items directly
            self::add_choice_view($choices[$value], $value, $label, $keys, $index, $attr, $label_translation_parameters, $is_preferred, $preferred_views, $preferred_views_order, $other_views, $duplicate_preferred_choices);
        }
    }
    private static function add_choice_views_grouped_by_callable(callable $group_by, $choice, string $value, callable|bool|null $label, array $keys, &$index, array|callable|null $attr, array|callable $label_translation_parameters, ?callable $is_preferred, array &$preferred_views, array &$preferred_views_order, array &$other_views, bool $duplicate_preferred_choices): void
    {
        $group_labels = $group_by($choice, $keys[$value], $value);
        if (null === $group_labels) {
            // If the callable returns null, don't group the choice
            self::add_choice_view($choice, $value, $label, $keys, $index, $attr, $label_translation_parameters, $is_preferred, $preferred_views, $preferred_views_order, $other_views, $duplicate_preferred_choices);
            return;
        }
        $group_labels = \is_array($group_labels) ? array_map(strval(...), $group_labels) : [(string) $group_labels];
        foreach ($group_labels as $group_label) {
            // Initialize the group views if necessary. Unnecessarily built group
            // views will be cleaned up at the end of createView()
            if (!isset($preferred_views[$group_label])) {
                $preferred_views[$group_label] = new Choice_Group_View($group_label);
                $other_views[$group_label] = new Choice_Group_View($group_label);
            }
            if (!isset($preferred_views_order[$group_label])) {
                $preferred_views_order[$group_label] = [];
            }
            self::add_choice_view($choice, $value, $label, $keys, $index, $attr, $label_translation_parameters, $is_preferred, $preferred_views[$group_label]->choices, $preferred_views_order[$group_label], $other_views[$group_label]->choices, $duplicate_preferred_choices);
        }
    }
}