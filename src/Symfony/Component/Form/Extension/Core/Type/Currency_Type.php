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
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Options_Resolver\Exception\Invalid_Options_Exception;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Currency_Type extends Abstract_Type
{
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['choice_loader' => function (Options $options): \Symfony\Component\Form\Choice_List\Factory\Cache\Choice_Loader {
            if (!class_exists(Intl::class)) {
                throw new LogicException(\sprintf('The "symfony/intl" component is required to use "%s". Try running "composer require symfony/intl".', static::class));
            }
            $choice_translation_locale = $options['choice_translation_locale'];
            $active_at = $options['active_at'];
            $not_active_at = $options['not_active_at'];
            $legal_tender = $options['legal_tender'];
            $include_undated = $options['include_undated'];
            if (null !== $active_at && null !== $not_active_at) {
                throw new Invalid_Options_Exception('The "active_at" and "not_active_at" options cannot be used together.');
            }
            $legal_tender_cache_key = match ($legal_tender) {
                null => 'X',
                true => '1',
                false => '0',
            };
            return Choice_List::loader($this, new Intl_Callback_Choice_Loader(static function () use ($choice_translation_locale, $active_at, $not_active_at, $legal_tender, $include_undated): array {
                if (null === $active_at && null === $not_active_at && null === $legal_tender) {
                    return array_flip(Currencies::get_names($choice_translation_locale));
                }
                $filtered_currency_names = [];
                $active = match (true) {
                    null !== $active_at => true,
                    null !== $not_active_at => false,
                    default => null,
                };
                foreach (Currencies::get_currency_codes() as $code) {
                    if (!Currencies::is_valid_in_any_country($code, $legal_tender, $active, $active_at ?? $not_active_at, $include_undated)) {
                        continue;
                    }
                    $filtered_currency_names[$code] = Currencies::get_name($code, $choice_translation_locale);
                }
                return array_flip($filtered_currency_names);
            }), $choice_translation_locale . ($active_at ?? $not_active_at)?->format('Y-m-d\TH:i:s') . $legal_tender_cache_key . (int) $include_undated);
        }, 'choice_translation_domain' => false, 'choice_translation_locale' => null, 'active_at' => new \DateTimeImmutable('today', new \DateTimeZone('Etc/UTC')), 'not_active_at' => null, 'include_undated' => true, 'legal_tender' => true, 'invalid_message' => 'Please select a valid currency.']);
        $resolver->set_allowed_types('choice_translation_locale', ['null', 'string']);
        $resolver->set_allowed_types('active_at', [\DateTimeInterface::class, 'null']);
        $resolver->set_allowed_types('not_active_at', [\DateTimeInterface::class, 'null']);
        $resolver->set_allowed_types('legal_tender', ['bool', 'null']);
        $resolver->set_allowed_types('include_undated', 'bool');
    }
    public function get_parent(): ?string
    {
        return Choice_Type::class;
    }
    public function get_block_prefix(): string
    {
        return 'currency';
    }
}