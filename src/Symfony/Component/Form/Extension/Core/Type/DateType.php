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

use Symfony\Component\Clock\Date_Point;
use Symfony\Component\Form\Abstract_Type;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Point_To_Date_Time_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_Immutable_To_Date_Time_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_To_Array_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_To_Localized_String_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_To_String_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_To_Timestamp_Transformer;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Form\Reversed_Transformer;
use Symfony\Component\Options_Resolver\Exception\Invalid_Options_Exception;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Date_Type extends Abstract_Type
{
    public const DEFAULT_FORMAT = \Intl_Date_Formatter::MEDIUM;
    public const HTML5_FORMAT = 'yyyy-MM-dd';
    private const ACCEPTED_FORMATS = [\Intl_Date_Formatter::FULL, \Intl_Date_Formatter::LONG, \Intl_Date_Formatter::MEDIUM, \Intl_Date_Formatter::SHORT];
    private const WIDGETS = ['text' => Text_Type::class, 'choice' => Choice_Type::class];
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $date_format = \is_int($options['format']) ? $options['format'] : self::DEFAULT_FORMAT;
        $time_format = \Intl_Date_Formatter::NONE;
        $calendar = $options['calendar'] ?? \Intl_Date_Formatter::GREGORIAN;
        $pattern = \is_string($options['format']) ? $options['format'] : '';
        if (!\in_array($date_format, self::ACCEPTED_FORMATS, true)) {
            throw new Invalid_Options_Exception('The "format" option must be one of the IntlDateFormatter constants (FULL, LONG, MEDIUM, SHORT) or a string representing a custom format.');
        }
        if ('single_text' === $options['widget']) {
            if ('' !== $pattern && !str_contains($pattern, 'y') && !str_contains($pattern, 'M') && !str_contains($pattern, 'd')) {
                throw new Invalid_Options_Exception(\sprintf('The "format" option should contain the letters "y", "M" or "d". Its current value is "%s".', $pattern));
            }
            $builder->add_view_transformer(new Date_Time_To_Localized_String_Transformer($options['model_timezone'], $options['view_timezone'], $date_format, $time_format, $calendar, $pattern));
        } else {
            if ('' !== $pattern && (!str_contains($pattern, 'y') || !str_contains($pattern, 'M') || !str_contains($pattern, 'd'))) {
                throw new Invalid_Options_Exception(\sprintf('The "format" option should contain the letters "y", "M" and "d". Its current value is "%s".', $pattern));
            }
            $year_options = $month_options = $day_options = ['error_bubbling' => true, 'empty_data' => ''];
            // when the form is compound the entries of the array are ignored in favor of children data
            // so we need to handle the cascade setting here
            $empty_data = $builder->get_empty_data() ?: [];
            if ($empty_data instanceof \Closure) {
                $lazy_empty_data = static fn($option): \Closure => static function (Form_Interface $form) use ($empty_data, $option) {
                    $empty_data = $empty_data($form->get_parent());
                    return $empty_data[$option] ?? '';
                };
                $year_options['empty_data'] = $lazy_empty_data('year');
                $month_options['empty_data'] = $lazy_empty_data('month');
                $day_options['empty_data'] = $lazy_empty_data('day');
            } else {
                if (isset($empty_data['year'])) {
                    $year_options['empty_data'] = $empty_data['year'];
                }
                if (isset($empty_data['month'])) {
                    $month_options['empty_data'] = $empty_data['month'];
                }
                if (isset($empty_data['day'])) {
                    $day_options['empty_data'] = $empty_data['day'];
                }
            }
            if (isset($options['invalid_message'])) {
                $day_options['invalid_message'] = $options['invalid_message'];
                $month_options['invalid_message'] = $options['invalid_message'];
                $year_options['invalid_message'] = $options['invalid_message'];
            }
            if (isset($options['invalid_message_parameters'])) {
                $day_options['invalid_message_parameters'] = $options['invalid_message_parameters'];
                $month_options['invalid_message_parameters'] = $options['invalid_message_parameters'];
                $year_options['invalid_message_parameters'] = $options['invalid_message_parameters'];
            }
            $formatter = new \Intl_Date_Formatter(\Locale::get_default(), $date_format, $time_format, null, $calendar, $pattern);
            $formatter->set_lenient(false);
            if ('choice' === $options['widget']) {
                // Only pass a subset of the options to children
                $year_options['choices'] = $this->format_timestamps($formatter, '/y+/', $this->list_years($options['years']));
                $year_options['placeholder'] = $options['placeholder']['year'];
                $year_options['choice_translation_domain'] = $options['choice_translation_domain']['year'];
                $month_options['choices'] = $this->format_timestamps($formatter, '/[M|L]+/', $this->list_months($options['months']));
                $month_options['placeholder'] = $options['placeholder']['month'];
                $month_options['choice_translation_domain'] = $options['choice_translation_domain']['month'];
                $day_options['choices'] = $this->format_timestamps($formatter, '/d+/', $this->list_days($options['days']));
                $day_options['placeholder'] = $options['placeholder']['day'];
                $day_options['choice_translation_domain'] = $options['choice_translation_domain']['day'];
            }
            // Append generic carry-along options
            foreach (['required', 'translation_domain'] as $pass_opt) {
                $year_options[$pass_opt] = $month_options[$pass_opt] = $day_options[$pass_opt] = $options[$pass_opt];
            }
            $builder->add('year', self::WIDGETS[$options['widget']], $year_options)->add('month', self::WIDGETS[$options['widget']], $month_options)->add('day', self::WIDGETS[$options['widget']], $day_options)->add_view_transformer(new Date_Time_To_Array_Transformer($options['model_timezone'], $options['view_timezone'], ['year', 'month', 'day']))->set_attribute('formatter', $formatter);
        }
        if ('date_point' === $options['input']) {
            if (!class_exists(Date_Point::class)) {
                throw new LogicException(\sprintf('The "symfony/clock" component is required to use "%s" with option "input=date_point". Try running "composer require symfony/clock".', self::class));
            }
            $builder->add_model_transformer(new Date_Point_To_Date_Time_Transformer());
        } elseif ('datetime_immutable' === $options['input']) {
            $builder->add_model_transformer(new Date_Time_Immutable_To_Date_Time_Transformer());
        } elseif ('string' === $options['input']) {
            $builder->add_model_transformer(new Reversed_Transformer(new Date_Time_To_String_Transformer($options['model_timezone'], $options['model_timezone'], $options['input_format'])));
        } elseif ('timestamp' === $options['input']) {
            $builder->add_model_transformer(new Reversed_Transformer(new Date_Time_To_Timestamp_Transformer($options['model_timezone'], $options['model_timezone'])));
        } elseif ('array' === $options['input']) {
            $builder->add_model_transformer(new Reversed_Transformer(new Date_Time_To_Array_Transformer($options['model_timezone'], $options['model_timezone'], ['year', 'month', 'day'])));
        }
        if (\in_array($options['input'], ['datetime', 'datetime_immutable', 'date_point'], true) && null !== $options['model_timezone']) {
            $builder->add_event_listener(Form_Events::POST_SET_DATA, static function (Form_Event $event) use ($options): void {
                $date = $event->get_data();
                if (!$date instanceof \DateTimeInterface) {
                    return;
                }
                if ($date->get_timezone()->get_name() !== $options['model_timezone']) {
                    throw new LogicException(\sprintf('Using a "%s" instance with a timezone ("%s") not matching the configured model timezone "%s" is not supported.', get_debug_type($date), $date->get_timezone()->get_name(), $options['model_timezone']));
                }
            });
        }
    }
    public function finish_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $view->vars['widget'] = $options['widget'];
        // Change the input to an HTML5 date input if
        //  * the widget is set to "single_text"
        //  * the format matches the one expected by HTML5
        //  * the html5 is set to true
        if ($options['html5'] && 'single_text' === $options['widget'] && self::HTML5_FORMAT === $options['format']) {
            $view->vars['type'] = 'date';
        }
        if ($form->get_config()->has_attribute('formatter')) {
            $pattern = $form->get_config()->get_attribute('formatter')->get_pattern();
            // remove special characters unless the format was explicitly specified
            if (!\is_string($options['format'])) {
                // remove quoted strings first
                $pattern = preg_replace('/\'[^\']+\'/', '', (string) $pattern);
                // remove remaining special chars
                $pattern = preg_replace('/[^yMd]+/', '', $pattern);
            }
            // set right order with respect to locale (e.g.: de_DE=dd.MM.yy; en_US=M/d/yy)
            // lookup various formats at http://userguide.icu-project.org/formatparse/datetime
            if (preg_match('/^([yMd]+)[^yMd]*([yMd]+)[^yMd]*([yMd]+)$/', (string) $pattern)) {
                $pattern = preg_replace(['/y+/', '/M+/', '/d+/'], ['{{ year }}', '{{ month }}', '{{ day }}'], (string) $pattern);
            } else {
                // default fallback
                $pattern = '{{ year }}{{ month }}{{ day }}';
            }
            $view->vars['date_pattern'] = $pattern;
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $compound = static fn(Options $options): bool => 'single_text' !== $options['widget'];
        $placeholder_default = static fn(Options $options): ?string => $options['required'] ? null : '';
        $placeholder_normalizer = static function (Options $options, $placeholder) use ($placeholder_default): array {
            if (\is_array($placeholder)) {
                $default = $placeholder_default($options);
                return array_merge(['year' => $default, 'month' => $default, 'day' => $default], $placeholder);
            }
            return ['year' => $placeholder, 'month' => $placeholder, 'day' => $placeholder];
        };
        $choice_translation_domain_normalizer = static function (Options $options, $choice_translation_domain): array {
            if (\is_array($choice_translation_domain)) {
                return array_replace(['year' => false, 'month' => false, 'day' => false], $choice_translation_domain);
            }
            return ['year' => $choice_translation_domain, 'month' => $choice_translation_domain, 'day' => $choice_translation_domain];
        };
        $format = static fn(Options $options): string|int => 'single_text' === $options['widget'] ? self::HTML5_FORMAT : self::DEFAULT_FORMAT;
        $resolver->set_defaults([
            'years' => range((int) date('Y') - 5, (int) date('Y') + 5),
            'months' => range(1, 12),
            'days' => range(1, 31),
            'widget' => 'single_text',
            'input' => 'datetime',
            'format' => $format,
            'model_timezone' => null,
            'view_timezone' => null,
            'calendar' => null,
            'placeholder' => $placeholder_default,
            'html5' => true,
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            'by_reference' => false,
            'error_bubbling' => false,
            // If initialized with a \DateTime object, FormType initializes
            // this option to "\DateTime". Since the internal, normalized
            // representation is not \DateTime, but an array, we need to unset
            // this option.
            'data_class' => null,
            'compound' => $compound,
            'empty_data' => static fn(Options $options): array|string => $options['compound'] ? [] : '',
            'choice_translation_domain' => false,
            'input_format' => 'Y-m-d',
            'invalid_message' => 'Please enter a valid date.',
        ]);
        $resolver->set_normalizer('placeholder', $placeholder_normalizer);
        $resolver->set_normalizer('choice_translation_domain', $choice_translation_domain_normalizer);
        $resolver->set_allowed_values('input', ['datetime', 'datetime_immutable', 'date_point', 'string', 'timestamp', 'array']);
        $resolver->set_allowed_values('widget', ['single_text', 'text', 'choice']);
        $resolver->set_allowed_types('format', ['int', 'string']);
        $resolver->set_allowed_types('years', 'array');
        $resolver->set_allowed_types('months', 'array');
        $resolver->set_allowed_types('days', 'array');
        $resolver->set_allowed_types('input_format', 'string');
        $resolver->set_allowed_types('calendar', ['null', 'int', \Intl_Calendar::class]);
        $resolver->set_info('calendar', 'The calendar to use for formatting and parsing the date. The value should be an instance of \IntlCalendar. By default, the Gregorian calendar with the default locale is used.');
        $resolver->set_normalizer('html5', static function (Options $options, $html5) {
            if ($html5 && 'single_text' === $options['widget'] && self::HTML5_FORMAT !== $options['format']) {
                throw new LogicException(\sprintf('Cannot use the "format" option of "%s" when the "html5" option is enabled.', self::class));
            }
            return $html5;
        });
    }
    public function get_block_prefix(): string
    {
        return 'date';
    }
    private function format_timestamps(\Intl_Date_Formatter $formatter, string $regex, array $timestamps): array
    {
        $pattern = $formatter->get_pattern();
        $timezone = $formatter->get_time_zone_id();
        $formatted_timestamps = [];
        $formatter->set_time_zone('UTC');
        if (preg_match($regex, $pattern, $matches)) {
            $formatter->set_pattern($matches[0]);
            foreach ($timestamps as $timestamp => $choice) {
                $formatted_timestamps[$formatter->format($timestamp)] = $choice;
            }
            // I'd like to clone the formatter above, but then we get a
            // segmentation fault, so let's restore the old state instead
            $formatter->set_pattern($pattern);
        }
        $formatter->set_time_zone($timezone);
        return $formatted_timestamps;
    }
    private function list_years(array $years): array
    {
        $result = [];
        foreach ($years as $year) {
            $result[\PHP_INT_SIZE === 4 ? \DateTimeImmutable::create_from_format('Y e', $year . ' UTC')->format('U') : gmmktime(0, 0, 0, 6, 15, $year)] = $year;
        }
        return $result;
    }
    private function list_months(array $months): array
    {
        $result = [];
        foreach ($months as $month) {
            $result[gmmktime(0, 0, 0, $month, 15)] = $month;
        }
        return $result;
    }
    private function list_days(array $days): array
    {
        $result = [];
        foreach ($days as $day) {
            $result[gmmktime(0, 0, 0, 5, $day)] = $day;
        }
        return $result;
    }
}