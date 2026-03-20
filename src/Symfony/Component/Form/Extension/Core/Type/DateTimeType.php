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
use Symfony\Component\Form\Extension\Core\Data_Transformer\Array_To_Parts_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Data_Transformer_Chain;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Point_To_Date_Time_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_Immutable_To_Date_Time_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_To_Array_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_To_Html5local_Date_Time_Transformer;
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
class Date_Time_Type extends Abstract_Type
{
    public const DEFAULT_DATE_FORMAT = \Intl_Date_Formatter::MEDIUM;
    public const DEFAULT_TIME_FORMAT = \Intl_Date_Formatter::MEDIUM;
    /**
     * The HTML5 datetime-local format as defined in
     * http://w3c.github.io/html-reference/datatypes.html#form.data.datetime-local.
     */
    public const HTML5_FORMAT = "yyyy-MM-dd'T'HH:mm:ss";
    private const ACCEPTED_FORMATS = [\Intl_Date_Formatter::FULL, \Intl_Date_Formatter::LONG, \Intl_Date_Formatter::MEDIUM, \Intl_Date_Formatter::SHORT];
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $parts = ['year', 'month', 'day', 'hour'];
        $date_parts = ['year', 'month', 'day'];
        $time_parts = ['hour'];
        if ($options['with_minutes']) {
            $parts[] = 'minute';
            $time_parts[] = 'minute';
        }
        if ($options['with_seconds']) {
            $parts[] = 'second';
            $time_parts[] = 'second';
        }
        $date_format = \is_int($options['date_format']) ? $options['date_format'] : self::DEFAULT_DATE_FORMAT;
        $time_format = self::DEFAULT_TIME_FORMAT;
        $calendar = \Intl_Date_Formatter::GREGORIAN;
        $pattern = \is_string($options['format']) ? $options['format'] : null;
        if (!\in_array($date_format, self::ACCEPTED_FORMATS, true)) {
            throw new Invalid_Options_Exception('The "date_format" option must be one of the IntlDateFormatter constants (FULL, LONG, MEDIUM, SHORT) or a string representing a custom format.');
        }
        if ('single_text' === $options['widget']) {
            if (self::HTML5_FORMAT === $pattern) {
                $builder->add_view_transformer(new Date_Time_To_Html5local_Date_Time_Transformer($options['model_timezone'], $options['view_timezone'], $options['with_seconds']));
            } else {
                $builder->add_view_transformer(new Date_Time_To_Localized_String_Transformer($options['model_timezone'], $options['view_timezone'], $date_format, $time_format, $calendar, $pattern));
            }
        } else {
            // when the form is compound the entries of the array are ignored in favor of children data
            // so we need to handle the cascade setting here
            $empty_data = $builder->get_empty_data() ?: [];
            // Only pass a subset of the options to children
            $date_options = array_intersect_key($options, array_flip(['years', 'months', 'days', 'placeholder', 'choice_translation_domain', 'required', 'translation_domain', 'html5', 'invalid_message', 'invalid_message_parameters']));
            if ($empty_data instanceof \Closure) {
                $lazy_empty_data = static fn($option): \Closure => static function (Form_Interface $form) use ($empty_data, $option) {
                    $empty_data = $empty_data($form->get_parent());
                    return $empty_data[$option] ?? '';
                };
                $date_options['empty_data'] = $lazy_empty_data('date');
            } elseif (isset($empty_data['date'])) {
                $date_options['empty_data'] = $empty_data['date'];
            }
            $time_options = array_intersect_key($options, array_flip(['hours', 'minutes', 'seconds', 'with_minutes', 'with_seconds', 'placeholder', 'choice_translation_domain', 'required', 'translation_domain', 'html5', 'invalid_message', 'invalid_message_parameters']));
            if ($empty_data instanceof \Closure) {
                $time_options['empty_data'] = $lazy_empty_data('time');
            } elseif (isset($empty_data['time'])) {
                $time_options['empty_data'] = $empty_data['time'];
            }
            if (false === $options['label']) {
                $date_options['label'] = false;
                $time_options['label'] = false;
            }
            $date_options['widget'] = $options['date_widget'] ?? $options['widget'] ?? 'choice';
            $time_options['widget'] = $options['time_widget'] ?? $options['widget'] ?? 'choice';
            if (null !== $options['date_label']) {
                $date_options['label'] = $options['date_label'];
            }
            if (null !== $options['time_label']) {
                $time_options['label'] = $options['time_label'];
            }
            if (null !== $options['date_format']) {
                $date_options['format'] = $options['date_format'];
            }
            $date_options['input'] = $time_options['input'] = 'array';
            $date_options['error_bubbling'] = $time_options['error_bubbling'] = true;
            $builder->add_view_transformer(new Data_Transformer_Chain([new Date_Time_To_Array_Transformer($options['model_timezone'], $options['view_timezone'], $parts), new Array_To_Parts_Transformer(['date' => $date_parts, 'time' => $time_parts])]))->add('date', Date_Type::class, $date_options)->add('time', Time_Type::class, $time_options);
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
            $builder->add_model_transformer(new Reversed_Transformer(new Date_Time_To_Array_Transformer($options['model_timezone'], $options['model_timezone'], $parts)));
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
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $view->vars['widget'] = $options['widget'];
        // Change the input to an HTML5 datetime input if
        //  * the widget is set to "single_text"
        //  * the format matches the one expected by HTML5
        //  * the html5 is set to true
        if ($options['html5'] && 'single_text' === $options['widget'] && self::HTML5_FORMAT === $options['format']) {
            $view->vars['type'] = 'datetime-local';
            // we need to force the browser to display the seconds by
            // adding the HTML attribute step if not already defined.
            // Otherwise the browser will not display and so not send the seconds
            // therefore the value will always be considered as invalid.
            if (!isset($view->vars['attr']['step'])) {
                if ($options['with_seconds']) {
                    $view->vars['attr']['step'] = 1;
                } elseif (!$options['with_minutes']) {
                    $view->vars['attr']['step'] = 3600;
                }
            }
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $compound = static fn(Options $options): bool => 'single_text' !== $options['widget'];
        $resolver->set_defaults([
            'input' => 'datetime',
            'model_timezone' => null,
            'view_timezone' => null,
            'format' => self::HTML5_FORMAT,
            'date_format' => null,
            'widget' => null,
            'date_widget' => null,
            'time_widget' => null,
            'with_minutes' => true,
            'with_seconds' => false,
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
            'date_label' => null,
            'time_label' => null,
            'empty_data' => static fn(Options $options): array|string => $options['compound'] ? [] : '',
            'input_format' => 'Y-m-d H:i:s',
            'invalid_message' => 'Please enter a valid date and time.',
        ]);
        // Don't add some defaults in order to preserve the defaults
        // set in DateType and TimeType
        $resolver->set_defined(['placeholder', 'choice_translation_domain', 'years', 'months', 'days', 'hours', 'minutes', 'seconds']);
        $resolver->set_allowed_values('input', ['datetime', 'datetime_immutable', 'date_point', 'string', 'timestamp', 'array']);
        $resolver->set_allowed_values('date_widget', [
            null,
            // inherit default from DateType
            'single_text',
            'text',
            'choice',
        ]);
        $resolver->set_allowed_values('time_widget', [
            null,
            // inherit default from TimeType
            'single_text',
            'text',
            'choice',
        ]);
        // This option will overwrite "date_widget" and "time_widget" options
        $resolver->set_allowed_values('widget', [
            null,
            // default, don't overwrite options
            'single_text',
            'text',
            'choice',
        ]);
        $resolver->set_allowed_types('input_format', 'string');
        $resolver->set_normalizer('date_format', static function (Options $options, $date_format) {
            if (null !== $date_format && 'single_text' === $options['widget'] && self::HTML5_FORMAT === $options['format']) {
                throw new LogicException(\sprintf('Cannot use the "date_format" option of the "%s" with an HTML5 date.', self::class));
            }
            return $date_format;
        });
        $resolver->set_normalizer('widget', static function (Options $options, $widget) {
            if ('single_text' === $widget) {
                if (null !== $options['date_widget']) {
                    throw new LogicException(\sprintf('Cannot use the "date_widget" option of the "%s" when the "widget" option is set to "single_text".', self::class));
                }
                if (null !== $options['time_widget']) {
                    throw new LogicException(\sprintf('Cannot use the "time_widget" option of the "%s" when the "widget" option is set to "single_text".', self::class));
                }
            } elseif (null === $widget && null === $options['date_widget'] && null === $options['time_widget']) {
                return 'single_text';
            }
            return $widget;
        });
        $resolver->set_normalizer('html5', static function (Options $options, $html5) {
            if ($html5 && self::HTML5_FORMAT !== $options['format']) {
                throw new LogicException(\sprintf('Cannot use the "format" option of "%s" when the "html5" option is enabled.', self::class));
            }
            return $html5;
        });
    }
    public function get_block_prefix(): string
    {
        return 'datetime';
    }
}