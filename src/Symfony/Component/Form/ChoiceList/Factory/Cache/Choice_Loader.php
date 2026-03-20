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
namespace Symfony\Component\Form\Choice_List\Factory\Cache;

use Symfony\Component\Form\Choice_List\Choice_List_Interface;
use Symfony\Component\Form\Choice_List\Loader\Choice_Loader_Interface;
use Symfony\Component\Form\Form_Type_Extension_Interface;
use Symfony\Component\Form\Form_Type_Interface;
/**
 * A cacheable wrapper for {@see FormTypeInterface} or {@see FormTypeExtensionInterface}
 * which configures a "choice_loader" option.
 *
 * @internal
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
final class Choice_Loader extends Abstract_Static_Option implements Choice_Loader_Interface
{
    public function load_choice_list(?callable $value = null): Choice_List_Interface
    {
        return $this->get_option()->load_choice_list($value);
    }
    public function load_choices_for_values(array $values, ?callable $value = null): array
    {
        return $this->get_option()->load_choices_for_values($values, $value);
    }
    public function load_values_for_choices(array $choices, ?callable $value = null): array
    {
        return $this->get_option()->load_values_for_choices($choices, $value);
    }
}