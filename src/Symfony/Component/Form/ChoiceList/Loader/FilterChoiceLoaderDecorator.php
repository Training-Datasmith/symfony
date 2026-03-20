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

/**
 * A decorator to filter choices only when they are loaded or partially loaded.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class Filter_Choice_Loader_Decorator extends Abstract_Choice_Loader
{
    private readonly \Closure $filter;
    public function __construct(private readonly Choice_Loader_Interface $decorated_loader, callable $filter)
    {
        $this->filter = $filter(...);
    }
    protected function load_choices(): iterable
    {
        $list = $this->decorated_loader->load_choice_list();
        if (array_values($list->get_values()) === array_values($structured_values = $list->get_structured_values())) {
            return array_filter(array_combine($list->get_original_keys(), $list->get_choices()), $this->filter);
        }
        foreach ($structured_values as $group => $values) {
            if (\is_array($values)) {
                if ($values && $filtered = array_filter($list->get_choices_for_values($values), $this->filter)) {
                    $choices[$group] = $filtered;
                }
                continue;
                // filter empty groups
            }
            if ($filtered = array_filter($list->get_choices_for_values([$values]), $this->filter)) {
                $choices[$group] = $filtered[0];
            }
        }
        return $choices ?? [];
    }
    public function load_choices_for_values(array $values, ?callable $value = null): array
    {
        return array_filter($this->decorated_loader->load_choices_for_values($values, $value), $this->filter);
    }
    public function load_values_for_choices(array $choices, ?callable $value = null): array
    {
        return $this->decorated_loader->load_values_for_choices(array_filter($choices, $this->filter), $value);
    }
}