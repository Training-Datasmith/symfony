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
 * A choice loader that loads its choices and values lazily, only when necessary.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class Lazy_Choice_Loader implements Choice_Loader_Interface
{
    private ?Choice_List_Interface $choice_list = null;
    public function __construct(private readonly Choice_Loader_Interface $loader)
    {
    }
    public function load_choice_list(?callable $value = null): Choice_List_Interface
    {
        return $this->choice_list ??= new Array_Choice_List([], $value);
    }
    public function load_choices_for_values(array $values, ?callable $value = null): array
    {
        $choices = $this->loader->load_choices_for_values($values, $value);
        $this->choice_list = new Array_Choice_List($choices, $value);
        return $choices;
    }
    public function load_values_for_choices(array $choices, ?callable $value = null): array
    {
        $values = $this->loader->load_values_for_choices($choices, $value);
        if ($this->choice_list?->get_values_for_choices($choices) !== $values) {
            $this->load_choices_for_values($values, $value);
        }
        return $values;
    }
}