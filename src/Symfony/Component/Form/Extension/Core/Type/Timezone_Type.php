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
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_Zone_To_String_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Intl_Time_Zone_To_String_Transformer;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Timezones;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Timezone_Type extends Abstract_Type
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        if ('datetimezone' === $options['input']) {
            $builder->add_model_transformer(new Date_Time_Zone_To_String_Transformer($options['multiple']));
        } elseif ('intltimezone' === $options['input']) {
            $builder->add_model_transformer(new Intl_Time_Zone_To_String_Transformer($options['multiple']));
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['intl' => false, 'choice_loader' => function (Options $options): \Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Loader {
            $input = $options['input'];
            if ($options['intl']) {
                if (!class_exists(Intl::class)) {
                    throw new LogicException(\sprintf('The "symfony/intl" component is required to use "%s" with option "intl=true". Try running "composer require symfony/intl".', static::class));
                }
                $choice_translation_locale = $options['choice_translation_locale'];
                return Choice_List::loader($this, new Intl_Callback_Choice_Loader(static fn(): array => self::get_intl_timezones($input, $choice_translation_locale)), [$input, $choice_translation_locale]);
            }
            return Choice_List::lazy($this, static fn(): array => self::get_php_timezones($input), $input);
        }, 'choice_translation_domain' => false, 'choice_translation_locale' => null, 'input' => 'string', 'invalid_message' => 'Please select a valid timezone.', 'regions' => \DateTimeZone::ALL]);
        $resolver->set_allowed_types('intl', ['bool']);
        $resolver->set_allowed_types('choice_translation_locale', ['null', 'string']);
        $resolver->set_normalizer('choice_translation_locale', static function (Options $options, $value) {
            if (null !== $value && !$options['intl']) {
                throw new LogicException('The "choice_translation_locale" option can only be used if the "intl" option is set to true.');
            }
            return $value;
        });
        $resolver->set_allowed_values('input', ['string', 'datetimezone', 'intltimezone']);
        $resolver->set_normalizer('input', static function (Options $options, $value) {
            if ('intltimezone' === $value && !class_exists(\Intl_Time_Zone::class)) {
                throw new LogicException('Cannot use "intltimezone" input because the PHP intl extension is not available.');
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
        return 'timezone';
    }
    private static function get_php_timezones(string $input): array
    {
        $timezones = [];
        foreach (\DateTimeZone::list_identifiers(\DateTimeZone::ALL) as $timezone) {
            if ('intltimezone' === $input && 'Etc/Unknown' === \Intl_Time_Zone::create_time_zone($timezone)->get_id()) {
                continue;
            }
            $timezones[str_replace(['/', '_'], [' / ', ' '], $timezone)] = $timezone;
        }
        return $timezones;
    }
    private static function get_intl_timezones(string $input, ?string $locale = null): array
    {
        $timezones = array_flip(Timezones::get_names($locale));
        if ('intltimezone' === $input) {
            foreach ($timezones as $name => $timezone) {
                if ('Etc/Unknown' === \Intl_Time_Zone::create_time_zone($timezone)->get_id()) {
                    unset($timezones[$name]);
                }
            }
        }
        return $timezones;
    }
}