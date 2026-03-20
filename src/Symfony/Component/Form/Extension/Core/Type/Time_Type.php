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
use Symfony\Component\Form\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Point_To_Date_Time_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_Immutable_To_Date_Time_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_To_Array_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_To_String_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Time_To_Timestamp_Transformer;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Form\Reversed_Transformer;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Time_Type extends Abstract_Type
{
    private const WIDGETS = ['text' => Text_Type::class, 'choice' => Choice_Type::class];
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $parts = ['hour'];
        $format = 'H';
        if ($options['with_seconds'] && !$options['with_minutes']) {
            throw new Invalid_Configuration_Exception('You cannot disable minutes if you have enabled seconds.');
        }
        if (null !== $options['reference_date'] && $options['reference_date']->get_timezone()->get_name() !== $options['model_timezone']) {
            throw new Invalid_Configuration_Exception(\sprintf('The configured "model_timezone" (%s) must match the timezone of the "reference_date" (%s).', $options['model_timezone'], $options['reference_date']->get_timezone()->get_name()));
        }
        if ($options['with_minutes']) {
            $format .= ':i';
            $parts[] = 'minute';
        }
        if ($options['with_seconds']) {
            $format .= ':s';
            $parts[] = 'second';
        }
        if ('single_text' === $options['widget']) {
            $builder->add_event_listener(Form_Events::PRE_SUBMIT, static function (Form_Event $e) use ($options): void {
                $data = $e->get_data();
                if ($data && preg_match('/^(?P<hours>\d{2}):(?P<minutes>\d{2})(?::(?P<seconds>\d{2})(?:\.\d+)?)?$/', $data, $matches)) {
                    if ($options['with_seconds']) {
                        // handle seconds ignored by user's browser when with_seconds enabled
                        // https://codereview.chromium.org/450533009/
                        $e->set_data(\sprintf('%s:%s:%s', $matches['hours'], $matches['minutes'], $matches['seconds'] ?? '00'));
                    } else {
                        $e->set_data(\sprintf('%s:%s', $matches['hours'], $matches['minutes']));
                    }
                }
            });
            $parse_format = null;
            if (null !== $options['reference_date']) {
                $parse_format = 'Y-m-d ' . $format;
                $builder->add_event_listener(Form_Events::PRE_SUBMIT, static function (Form_Event $event) use ($options): void {
                    $data = $event->get_data();
                    if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data)) {
                        $event->set_data($options['reference_date']->format('Y-m-d ') . $data);
                    }
                });
            }
            $builder->add_view_transformer(new Date_Time_To_String_Transformer($options['model_timezone'], $options['view_timezone'], $format, $parse_format));
        } else {
            $hour_options = $minute_options = $second_options = ['error_bubbling' => true, 'empty_data' => ''];
            // when the form is compound the entries of the array are ignored in favor of children data
            // so we need to handle the cascade setting here
            $empty_data = $builder->get_empty_data() ?: [];
            if ($empty_data instanceof \Closure) {
                $lazy_empty_data = static fn($option): \Closure => static function (Form_Interface $form) use ($empty_data, $option) {
                    $empty_data = $empty_data($form->get_parent());
                    return $empty_data[$option] ?? '';
                };
                $hour_options['empty_data'] = $lazy_empty_data('hour');
            } elseif (isset($empty_data['hour'])) {
                $hour_options['empty_data'] = $empty_data['hour'];
            }
            if (isset($options['invalid_message'])) {
                $hour_options['invalid_message'] = $options['invalid_message'];
                $minute_options['invalid_message'] = $options['invalid_message'];
                $second_options['invalid_message'] = $options['invalid_message'];
            }
            if (isset($options['invalid_message_parameters'])) {
                $hour_options['invalid_message_parameters'] = $options['invalid_message_parameters'];
                $minute_options['invalid_message_parameters'] = $options['invalid_message_parameters'];
                $second_options['invalid_message_parameters'] = $options['invalid_message_parameters'];
            }
            if ('choice' === $options['widget']) {
                $hours = $minutes = [];
                foreach ($options['hours'] as $hour) {
                    $hours[str_pad((string) $hour, 2, '0', \STR_PAD_LEFT)] = $hour;
                }
                // Only pass a subset of the options to children
                $hour_options['choices'] = $hours;
                $hour_options['placeholder'] = $options['placeholder']['hour'];
                $hour_options['choice_translation_domain'] = $options['choice_translation_domain']['hour'];
                if ($options['with_minutes']) {
                    foreach ($options['minutes'] as $minute) {
                        $minutes[str_pad((string) $minute, 2, '0', \STR_PAD_LEFT)] = $minute;
                    }
                    $minute_options['choices'] = $minutes;
                    $minute_options['placeholder'] = $options['placeholder']['minute'];
                    $minute_options['choice_translation_domain'] = $options['choice_translation_domain']['minute'];
                }
                if ($options['with_seconds']) {
                    $seconds = [];
                    foreach ($options['seconds'] as $second) {
                        $seconds[str_pad((string) $second, 2, '0', \STR_PAD_LEFT)] = $second;
                    }
                    $second_options['choices'] = $seconds;
                    $second_options['placeholder'] = $options['placeholder']['second'];
                    $second_options['choice_translation_domain'] = $options['choice_translation_domain']['second'];
                }
                // Append generic carry-along options
                foreach (['required', 'translation_domain'] as $pass_opt) {
                    $hour_options[$pass_opt] = $options[$pass_opt];
                    if ($options['with_minutes']) {
                        $minute_options[$pass_opt] = $options[$pass_opt];
                    }
                    if ($options['with_seconds']) {
                        $second_options[$pass_opt] = $options[$pass_opt];
                    }
                }
            }
            $builder->add('hour', self::WIDGETS[$options['widget']], $hour_options);
            if ($options['with_minutes']) {
                if ($empty_data instanceof \Closure) {
                    $minute_options['empty_data'] = $lazy_empty_data('minute');
                } elseif (isset($empty_data['minute'])) {
                    $minute_options['empty_data'] = $empty_data['minute'];
                }
                $builder->add('minute', self::WIDGETS[$options['widget']], $minute_options);
            }
            if ($options['with_seconds']) {
                if ($empty_data instanceof \Closure) {
                    $second_options['empty_data'] = $lazy_empty_data('second');
                } elseif (isset($empty_data['second'])) {
                    $second_options['empty_data'] = $empty_data['second'];
                }
                $builder->add('second', self::WIDGETS[$options['widget']], $second_options);
            }
            $builder->add_view_transformer(new Date_Time_To_Array_Transformer($options['model_timezone'], $options['view_timezone'], $parts, 'text' === $options['widget'], $options['reference_date']));
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
            $builder->add_model_transformer(new Reversed_Transformer(new Date_Time_To_Array_Transformer($options['model_timezone'], $options['model_timezone'], $parts, 'text' === $options['widget'], $options['reference_date'])));
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
        $view->vars = array_replace($view->vars, ['widget' => $options['widget'], 'with_minutes' => $options['with_minutes'], 'with_seconds' => $options['with_seconds']]);
        // Change the input to an HTML5 time input if
        //  * the widget is set to "single_text"
        //  * the html5 is set to true
        if ($options['html5'] && 'single_text' === $options['widget']) {
            $view->vars['type'] = 'time';
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
        $placeholder_default = static fn(Options $options): ?string => $options['required'] ? null : '';
        $placeholder_normalizer = static function (Options $options, $placeholder) use ($placeholder_default): array {
            if (\is_array($placeholder)) {
                $default = $placeholder_default($options);
                return array_merge(['hour' => $default, 'minute' => $default, 'second' => $default], $placeholder);
            }
            return ['hour' => $placeholder, 'minute' => $placeholder, 'second' => $placeholder];
        };
        $choice_translation_domain_normalizer = static function (Options $options, $choice_translation_domain): array {
            if (\is_array($choice_translation_domain)) {
                return array_replace(['hour' => false, 'minute' => false, 'second' => false], $choice_translation_domain);
            }
            return ['hour' => $choice_translation_domain, 'minute' => $choice_translation_domain, 'second' => $choice_translation_domain];
        };
        $model_timezone = static function (Options $options, $value): ?string {
            if (null !== $value) {
                return $value;
            }
            if (null !== $options['reference_date']) {
                return $options['reference_date']->get_timezone()->get_name();
            }
            return null;
        };
        $view_timezone = static function (Options $options, $value): ?string {
            if (null !== $value) {
                return $value;
            }
            if (null !== $options['model_timezone'] && null === $options['reference_date']) {
                return $options['model_timezone'];
            }
            return null;
        };
        $resolver->set_defaults([
            'hours' => range(0, 23),
            'minutes' => range(0, 59),
            'seconds' => range(0, 59),
            'widget' => 'single_text',
            'input' => 'datetime',
            'input_format' => 'H:i:s',
            'with_minutes' => true,
            'with_seconds' => false,
            'model_timezone' => $model_timezone,
            'view_timezone' => $view_timezone,
            'reference_date' => null,
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
            'empty_data' => static fn(Options $options): array|string => $options['compound'] ? [] : '',
            'compound' => $compound,
            'choice_translation_domain' => false,
            'invalid_message' => 'Please enter a valid time.',
        ]);
        $resolver->set_normalizer('view_timezone', static function (Options $options, $view_timezone): ?string {
            if (null !== $options['model_timezone'] && $view_timezone !== $options['model_timezone'] && null === $options['reference_date']) {
                throw new LogicException('Using different values for the "model_timezone" and "view_timezone" options without configuring a reference date is not supported.');
            }
            return $view_timezone;
        });
        $resolver->set_normalizer('placeholder', $placeholder_normalizer);
        $resolver->set_normalizer('choice_translation_domain', $choice_translation_domain_normalizer);
        $resolver->set_allowed_values('input', ['datetime', 'datetime_immutable', 'date_point', 'string', 'timestamp', 'array']);
        $resolver->set_allowed_values('widget', ['single_text', 'text', 'choice']);
        $resolver->set_allowed_types('hours', 'array');
        $resolver->set_allowed_types('minutes', 'array');
        $resolver->set_allowed_types('seconds', 'array');
        $resolver->set_allowed_types('input_format', 'string');
        $resolver->set_allowed_types('model_timezone', ['null', 'string']);
        $resolver->set_allowed_types('view_timezone', ['null', 'string']);
        $resolver->set_allowed_types('reference_date', ['null', \DateTimeInterface::class]);
    }
    public function get_block_prefix(): string
    {
        return 'time';
    }
}