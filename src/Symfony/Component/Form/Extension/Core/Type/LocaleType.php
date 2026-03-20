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
namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\Abstract_Type;
use Symfony\Component\Form\Choice_List\Choice_List;
use Symfony\Component\Form\Choice_List\Loader\Intl_Callback_Choice_Loader;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Locales;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Locale_Type extends Abstract_Type
{
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['choice_loader' => function (Options $options): \Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Loader {
            if (!class_exists(Intl::class)) {
                throw new LogicException(\sprintf('The "symfony/intl" component is required to use "%s". Try running "composer require symfony/intl".', static::class));
            }
            $choice_translation_locale = $options['choice_translation_locale'];
            return Choice_List::loader($this, new Intl_Callback_Choice_Loader(static fn(): array => array_flip(Locales::get_names($choice_translation_locale))), $choice_translation_locale);
        }, 'choice_translation_domain' => false, 'choice_translation_locale' => null, 'invalid_message' => 'Please select a valid locale.']);
        $resolver->set_allowed_types('choice_translation_locale', ['null', 'string']);
    }
    public function get_parent(): ?string
    {
        return Choice_Type::class;
    }
    public function get_block_prefix(): string
    {
        return 'locale';
    }
}