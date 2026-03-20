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
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\Data_Transformer\Week_To_Array_Transformer;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Form\Reversed_Transformer;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Week_Type extends Abstract_Type
{
    private const WIDGETS = ['text' => Integer_Type::class, 'choice' => Choice_Type::class];
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        if ('string' === $options['input']) {
            $builder->add_model_transformer(new Week_To_Array_Transformer());
        }
        if ('single_text' === $options['widget']) {
            $builder->add_view_transformer(new Reversed_Transformer(new Week_To_Array_Transformer()));
        } else {
            $year_options = $week_options = ['error_bubbling' => true];
            // when the form is compound the entries of the array are ignored in favor of children data
            // so we need to handle the cascade setting here
            $empty_data = $builder->get_empty_data() ?: [];
            $year_options['empty_data'] = $empty_data['year'] ?? '';
            $week_options['empty_data'] = $empty_data['week'] ?? '';
            if (isset($options['invalid_message'])) {
                $year_options['invalid_message'] = $options['invalid_message'];
                $week_options['invalid_message'] = $options['invalid_message'];
            }
            if (isset($options['invalid_message_parameters'])) {
                $year_options['invalid_message_parameters'] = $options['invalid_message_parameters'];
                $week_options['invalid_message_parameters'] = $options['invalid_message_parameters'];
            }
            if ('choice' === $options['widget']) {
                // Only pass a subset of the options to children
                $year_options['choices'] = array_combine($options['years'], $options['years']);
                $year_options['placeholder'] = $options['placeholder']['year'];
                $year_options['choice_translation_domain'] = $options['choice_translation_domain']['year'];
                $week_options['choices'] = array_combine($options['weeks'], $options['weeks']);
                $week_options['placeholder'] = $options['placeholder']['week'];
                $week_options['choice_translation_domain'] = $options['choice_translation_domain']['week'];
                // Append generic carry-along options
                foreach (['required', 'translation_domain'] as $pass_opt) {
                    $year_options[$pass_opt] = $options[$pass_opt];
                    $week_options[$pass_opt] = $options[$pass_opt];
                }
            }
            $builder->add('year', self::WIDGETS[$options['widget']], $year_options);
            $builder->add('week', self::WIDGETS[$options['widget']], $week_options);
        }
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $view->vars['widget'] = $options['widget'];
        if ($options['html5']) {
            $view->vars['type'] = 'week';
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $compound = static fn(Options $options): bool => 'single_text' !== $options['widget'];
        $placeholder_default = static fn(Options $options): ?string => $options['required'] ? null : '';
        $placeholder_normalizer = static function (Options $options, $placeholder) use ($placeholder_default): array {
            if (\is_array($placeholder)) {
                $default = $placeholder_default($options);
                return array_merge(['year' => $default, 'week' => $default], $placeholder);
            }
            return ['year' => $placeholder, 'week' => $placeholder];
        };
        $choice_translation_domain_normalizer = static function (Options $options, $choice_translation_domain): array {
            if (\is_array($choice_translation_domain)) {
                return array_replace(['year' => false, 'week' => false], $choice_translation_domain);
            }
            return ['year' => $choice_translation_domain, 'week' => $choice_translation_domain];
        };
        $resolver->set_defaults(['years' => range(date('Y') - 10, date('Y') + 10), 'weeks' => array_combine(range(1, 53), range(1, 53)), 'widget' => 'single_text', 'input' => 'array', 'placeholder' => $placeholder_default, 'html5' => static fn(Options $options): bool => 'single_text' === $options['widget'], 'error_bubbling' => false, 'empty_data' => static fn(Options $options): array|string => $options['compound'] ? [] : '', 'compound' => $compound, 'choice_translation_domain' => false, 'invalid_message' => 'Please enter a valid week.']);
        $resolver->set_normalizer('placeholder', $placeholder_normalizer);
        $resolver->set_normalizer('choice_translation_domain', $choice_translation_domain_normalizer);
        $resolver->set_normalizer('html5', static function (Options $options, $html5) {
            if ($html5 && 'single_text' !== $options['widget']) {
                throw new LogicException(\sprintf('The "widget" option of "%s" must be set to "single_text" when the "html5" option is enabled.', self::class));
            }
            return $html5;
        });
        $resolver->set_allowed_values('input', ['string', 'array']);
        $resolver->set_allowed_values('widget', ['single_text', 'text', 'choice']);
        $resolver->set_allowed_types('years', 'int[]');
        $resolver->set_allowed_types('weeks', 'int[]');
    }
    public function get_block_prefix(): string
    {
        return 'week';
    }
}