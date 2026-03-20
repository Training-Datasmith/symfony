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

use Symfony\Component\Form\Choice_List\Loader\Choice_Loader_Interface;
/**
 * A choice list that loads its choices lazily.
 *
 * The choices are fetched using a {@link ChoiceLoaderInterface} instance.
 * If only {@link getChoicesForValues()} or {@link getValuesForChoices()} is
 * called, the choice list is only loaded partially for improved performance.
 *
 * Once {@link getChoices()} or {@link getValues()} is called, the list is
 * loaded fully.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Lazy_Choice_List implements Choice_List_Interface
{
    /**
     * The callable creating string values for each choice.
     *
     * If null, choices are cast to strings.
     */
    private readonly ?\Closure $value;
    /**
     * Creates a lazily-loaded list using the given loader.
     *
     * Optionally, a callable can be passed for generating the choice values.
     * The callable receives the choice as first and the array key as the second
     * argument.
     *
     * @param callable|null $value The callable creating string values for each choice.
     *                             If null, choices are cast to strings.
     */
    public function __construct(private readonly Choice_Loader_Interface $loader, ?callable $value = null)
    {
        $this->value = null === $value ? null : $value(...);
    }
    public function get_choices(): array
    {
        return $this->loader->load_choice_list($this->value)->get_choices();
    }
    public function get_values(): array
    {
        return $this->loader->load_choice_list($this->value)->get_values();
    }
    public function get_structured_values(): array
    {
        return $this->loader->load_choice_list($this->value)->get_structured_values();
    }
    public function get_original_keys(): array
    {
        return $this->loader->load_choice_list($this->value)->get_original_keys();
    }
    public function get_choices_for_values(array $values): array
    {
        return $this->loader->load_choices_for_values($values, $this->value);
    }
    public function get_values_for_choices(array $choices): array
    {
        return $this->loader->load_values_for_choices($choices, $this->value);
    }
}