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
namespace Symfony\Component\Form\Choice_List\View;

/**
 * Represents a choice list in templates.
 *
 * A choice list contains choices and optionally preferred choices which are
 * displayed in the very beginning of the list. Both choices and preferred
 * choices may be grouped in {@link ChoiceGroupView} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Choice_List_View
{
    /**
     * Creates a new choice list view.
     *
     * @param array<ChoiceGroupView|ChoiceView> $choices          The choice views
     * @param array<ChoiceGroupView|ChoiceView> $preferredChoices the preferred choice views
     */
    public function __construct(public array $choices = [], public array $preferred_choices = [])
    {
    }
    /**
     * Returns whether a placeholder is in the choices.
     *
     * A placeholder must be the first child element, not be in a group and have an empty value.
     */
    public function has_placeholder(): bool
    {
        if ($this->preferred_choices) {
            $first_choice = reset($this->preferred_choices);
            return $first_choice instanceof Choice_View && '' === $first_choice->value;
        }
        $first_choice = reset($this->choices);
        return $first_choice instanceof Choice_View && '' === $first_choice->value;
    }
}