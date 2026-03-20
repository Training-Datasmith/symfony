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

use Symfony\Component\Form\Choice_List\Factory\Caching_Factory_Decorator;
use Symfony\Component\Form\Choice_List\Loader\Choice_Loader_Interface;
use Symfony\Component\Form\Extension\Core\Type\Choice_Type;
use Symfony\Component\Form\Form_Type_Extension_Interface;
use Symfony\Component\Form\Form_Type_Interface;
/**
 * A template decorator for static {@see ChoiceType} options.
 *
 * Used as fly weight for {@see CachingFactoryDecorator}.
 *
 * @internal
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
abstract class Abstract_Static_Option
{
    private static array $options = [];
    private readonly bool|string|array|\Closure|Choice_Loader_Interface $option;
    /**
     * @param mixed $option Any pseudo callable, array, string or bool to define a choice list option
     * @param mixed $vary   Dynamic data used to compute a unique hash when caching the option
     */
    final public function __construct(Form_Type_Interface|Form_Type_Extension_Interface $form_type, mixed $option, mixed $vary = null)
    {
        $hash = Caching_Factory_Decorator::generate_hash([static::class, $form_type, $vary]);
        $this->option = self::$options[$hash] ??= $option instanceof \Closure || \is_string($option) || \is_bool($option) || $option instanceof Choice_Loader_Interface || !\is_callable($option) ? $option : $option(...);
    }
    final public function get_option(): mixed
    {
        return $this->option;
    }
    final public static function reset(): void
    {
        self::$options = [];
    }
}