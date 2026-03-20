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
use Symfony\Component\Intl\Exception\Missing_Resource_Exception;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Languages;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Language_Type extends Abstract_Type
{
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['choice_loader' => function (Options $options): \Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Loader {
            if (!class_exists(Intl::class)) {
                throw new LogicException(\sprintf('The "symfony/intl" component is required to use "%s". Try running "composer require symfony/intl".', static::class));
            }
            $choice_translation_locale = $options['choice_translation_locale'];
            $use_alpha3codes = $options['alpha3'];
            $choice_self_translation = $options['choice_self_translation'];
            return Choice_List::loader($this, new Intl_Callback_Choice_Loader(static function () use ($choice_translation_locale, $use_alpha3codes, $choice_self_translation): array {
                if (true === $choice_self_translation) {
                    foreach (Languages::get_language_codes() as $alpha2Code) {
                        try {
                            $language_code = $use_alpha3codes ? Languages::get_alpha3code($alpha2Code) : $alpha2Code;
                            $languages_list[$language_code] = Languages::get_name($alpha2Code, $alpha2Code);
                        } catch (Missing_Resource_Exception) {
                            // ignore errors like "Couldn't read the indices for the locale 'meta'"
                        }
                    }
                } else {
                    $languages_list = $use_alpha3codes ? Languages::get_alpha3names($choice_translation_locale) : Languages::get_names($choice_translation_locale);
                }
                return array_flip($languages_list);
            }), [$choice_translation_locale, $use_alpha3codes, $choice_self_translation]);
        }, 'choice_translation_domain' => false, 'choice_translation_locale' => null, 'alpha3' => false, 'choice_self_translation' => false, 'invalid_message' => 'Please select a valid language.']);
        $resolver->set_allowed_types('choice_self_translation', ['bool']);
        $resolver->set_allowed_types('choice_translation_locale', ['null', 'string']);
        $resolver->set_allowed_types('alpha3', 'bool');
        $resolver->set_normalizer('choice_self_translation', static function (Options $options, $value) {
            if (true === $value && $options['choice_translation_locale']) {
                throw new LogicException('Cannot use the "choice_self_translation" and "choice_translation_locale" options at the same time. Remove one of them.');
            }
            return $value;
        });
    }
    public function get_parent(): ?string
    {
        return Choice_Type::class;
    }
    public function get_block_prefix(): string
    {
        return 'language';
    }
}