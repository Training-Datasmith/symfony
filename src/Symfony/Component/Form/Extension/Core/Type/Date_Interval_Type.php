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
use Symfony\Component\Form\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Interval_To_Array_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Date_Interval_To_String_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Integer_To_Localized_String_Transformer;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Form\Reversed_Transformer;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * @author Steffen Roßkamp <steffen.rosskamp@gimmickmedia.de>
 */
class Date_Interval_Type extends Abstract_Type
{
    private const TIME_PARTS = ['years', 'months', 'weeks', 'days', 'hours', 'minutes', 'seconds'];
    private const WIDGETS = ['text' => Text_Type::class, 'integer' => Integer_Type::class, 'choice' => Choice_Type::class];
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        if (!$options['with_years'] && !$options['with_months'] && !$options['with_weeks'] && !$options['with_days'] && !$options['with_hours'] && !$options['with_minutes'] && !$options['with_seconds']) {
            throw new Invalid_Configuration_Exception('You must enable at least one interval field.');
        }
        if ($options['with_invert'] && 'single_text' === $options['widget']) {
            throw new Invalid_Configuration_Exception('The single_text widget does not support invertible intervals.');
        }
        if ($options['with_weeks'] && $options['with_days']) {
            throw new Invalid_Configuration_Exception('You cannot enable weeks and days fields together.');
        }
        $format = 'P';
        $parts = [];
        if ($options['with_years']) {
            $format .= '%yY';
            $parts[] = 'years';
        }
        if ($options['with_months']) {
            $format .= '%mM';
            $parts[] = 'months';
        }
        if ($options['with_weeks']) {
            $format .= '%wW';
            $parts[] = 'weeks';
        }
        if ($options['with_days']) {
            $format .= '%dD';
            $parts[] = 'days';
        }
        if ($options['with_hours'] || $options['with_minutes'] || $options['with_seconds']) {
            $format .= 'T';
        }
        if ($options['with_hours']) {
            $format .= '%hH';
            $parts[] = 'hours';
        }
        if ($options['with_minutes']) {
            $format .= '%iM';
            $parts[] = 'minutes';
        }
        if ($options['with_seconds']) {
            $format .= '%sS';
            $parts[] = 'seconds';
        }
        if ($options['with_invert']) {
            $parts[] = 'invert';
        }
        if ('single_text' === $options['widget']) {
            $builder->add_view_transformer(new Date_Interval_To_String_Transformer($format));
        } else {
            foreach (self::TIME_PARTS as $part) {
                if ($options['with_' . $part]) {
                    $child_options = [
                        'error_bubbling' => true,
                        'label' => $options['labels'][$part],
                        // Append generic carry-along options
                        'required' => $options['required'],
                        'translation_domain' => $options['translation_domain'],
                        // when compound the array entries are ignored, we need to cascade the configuration here
                        'empty_data' => $options['empty_data'][$part] ?? null,
                    ];
                    if ('choice' === $options['widget']) {
                        $child_options['choice_translation_domain'] = false;
                        $child_options['choices'] = $options[$part];
                        $child_options['placeholder'] = $options['placeholder'][$part];
                    }
                    $child_form = $builder->create($part, self::WIDGETS[$options['widget']], $child_options);
                    if ('integer' === $options['widget']) {
                        $child_form->add_model_transformer(new Reversed_Transformer(new Integer_To_Localized_String_Transformer()));
                    }
                    $builder->add($child_form);
                }
            }
            if ($options['with_invert']) {
                $builder->add('invert', Checkbox_Type::class, ['label' => $options['labels']['invert'], 'error_bubbling' => true, 'required' => false, 'translation_domain' => $options['translation_domain']]);
            }
            $builder->add_view_transformer(new Date_Interval_To_Array_Transformer($parts, 'text' === $options['widget']));
        }
        if ('string' === $options['input']) {
            $builder->add_model_transformer(new Reversed_Transformer(new Date_Interval_To_String_Transformer($format)));
        } elseif ('array' === $options['input']) {
            $builder->add_model_transformer(new Reversed_Transformer(new Date_Interval_To_Array_Transformer($parts)));
        }
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $vars = ['widget' => $options['widget'], 'with_invert' => $options['with_invert']];
        foreach (self::TIME_PARTS as $part) {
            $vars['with_' . $part] = $options['with_' . $part];
        }
        $view->vars = array_replace($view->vars, $vars);
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $compound = static fn(Options $options): bool => 'single_text' !== $options['widget'];
        $empty_data = static fn(Options $options): array|string => 'single_text' === $options['widget'] ? '' : [];
        $placeholder_default = static fn(Options $options): ?string => $options['required'] ? null : '';
        $placeholder_normalizer = static function (Options $options, $placeholder) use ($placeholder_default): array {
            if (\is_array($placeholder)) {
                $default = $placeholder_default($options);
                return array_merge(array_fill_keys(self::TIME_PARTS, $default), $placeholder);
            }
            return array_fill_keys(self::TIME_PARTS, $placeholder);
        };
        $labels_normalizer = static fn(Options $options, array $labels): array => array_replace(['years' => null, 'months' => null, 'days' => null, 'weeks' => null, 'hours' => null, 'minutes' => null, 'seconds' => null, 'invert' => 'Negative interval'], array_filter($labels, static fn($label): bool => null !== $label));
        $resolver->set_defaults([
            'with_years' => true,
            'with_months' => true,
            'with_days' => true,
            'with_weeks' => false,
            'with_hours' => false,
            'with_minutes' => false,
            'with_seconds' => false,
            'with_invert' => false,
            'years' => range(0, 100),
            'months' => range(0, 12),
            'weeks' => range(0, 52),
            'days' => range(0, 31),
            'hours' => range(0, 24),
            'minutes' => range(0, 60),
            'seconds' => range(0, 60),
            'widget' => 'choice',
            'input' => 'dateinterval',
            'placeholder' => $placeholder_default,
            'by_reference' => true,
            'error_bubbling' => false,
            // If initialized with a \DateInterval object, FormType initializes
            // this option to "\DateInterval". Since the internal, normalized
            // representation is not \DateInterval, but an array, we need to unset
            // this option.
            'data_class' => null,
            'compound' => $compound,
            'empty_data' => $empty_data,
            'labels' => [],
            'invalid_message' => 'Please choose a valid date interval.',
        ]);
        $resolver->set_normalizer('placeholder', $placeholder_normalizer);
        $resolver->set_normalizer('labels', $labels_normalizer);
        $resolver->set_allowed_values('input', ['dateinterval', 'string', 'array']);
        $resolver->set_allowed_values('widget', ['single_text', 'text', 'integer', 'choice']);
        // Don't clone \DateInterval classes, as i.e. format()
        // does not work after that
        $resolver->set_allowed_values('by_reference', true);
        $resolver->set_allowed_types('years', 'array');
        $resolver->set_allowed_types('months', 'array');
        $resolver->set_allowed_types('weeks', 'array');
        $resolver->set_allowed_types('days', 'array');
        $resolver->set_allowed_types('hours', 'array');
        $resolver->set_allowed_types('minutes', 'array');
        $resolver->set_allowed_types('seconds', 'array');
        $resolver->set_allowed_types('with_years', 'bool');
        $resolver->set_allowed_types('with_months', 'bool');
        $resolver->set_allowed_types('with_weeks', 'bool');
        $resolver->set_allowed_types('with_days', 'bool');
        $resolver->set_allowed_types('with_hours', 'bool');
        $resolver->set_allowed_types('with_minutes', 'bool');
        $resolver->set_allowed_types('with_seconds', 'bool');
        $resolver->set_allowed_types('with_invert', 'bool');
        $resolver->set_allowed_types('labels', 'array');
    }
    public function get_block_prefix(): string
    {
        return 'dateinterval';
    }
}