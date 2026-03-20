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
 * Callback choice loader optimized for Intl choice types.
 *
 * @author Jules Pietri <jules@heahprod.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class Intl_Callback_Choice_Loader extends Callback_Choice_Loader
{
    public function load_choices_for_values(array $values, ?callable $value = null): array
    {
        return parent::load_choices_for_values(array_filter($values), $value);
    }
    public function load_values_for_choices(array $choices, ?callable $value = null): array
    {
        $choices = array_filter($choices);
        // If no callable is set, choices are the same as values
        if (null === $value) {
            return $choices;
        }
        return parent::load_values_for_choices($choices, $value);
    }
}