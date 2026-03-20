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
namespace Symfony\Component\Form\Choice_List\Loader;

use Symfony\Component\Form\Choice_List\Array_Choice_List;
use Symfony\Component\Form\Choice_List\Choice_List_Interface;
/**
 * @author Jules Pietri <jules@heahprod.com>
 */
abstract class Abstract_Choice_Loader implements Choice_Loader_Interface
{
    private ?iterable $choices = null;
    /**
     * @final
     */
    public function load_choice_list(?callable $value = null): Choice_List_Interface
    {
        return new Array_Choice_List($this->choices ??= $this->load_choices(), $value);
    }
    public function load_choices_for_values(array $values, ?callable $value = null): array
    {
        if (!$values) {
            return [];
        }
        return $this->do_load_choices_for_values($values, $value);
    }
    public function load_values_for_choices(array $choices, ?callable $value = null): array
    {
        if (!$choices) {
            return [];
        }
        if ($value) {
            // if a value callback exists, use it
            return array_map(static fn($item): string => (string) $value($item), $choices);
        }
        return $this->do_load_values_for_choices($choices);
    }
    abstract protected function load_choices(): iterable;
    protected function do_load_choices_for_values(array $values, ?callable $value): array
    {
        return $this->load_choice_list($value)->get_choices_for_values($values);
    }
    protected function do_load_values_for_choices(array $choices): array
    {
        return $this->load_choice_list()->get_values_for_choices($choices);
    }
}