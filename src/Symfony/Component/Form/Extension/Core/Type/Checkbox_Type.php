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
use Symfony\Component\Form\Extension\Core\Data_Transformer\Boolean_To_String_Transformer;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Checkbox_Type extends Abstract_Type
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        // Unlike in other types, where the data is NULL by default, it
        // needs to be a Boolean here. setData(null) is not acceptable
        // for checkboxes and radio buttons (unless a custom model
        // transformer handles this case).
        // We cannot solve this case via overriding the "data" option, because
        // doing so also calls setDataLocked(true).
        $builder->set_data($options['data'] ?? false);
        $builder->add_view_transformer(new Boolean_To_String_Transformer($options['value'], $options['false_values']));
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $view->vars = array_replace($view->vars, ['value' => $options['value'], 'checked' => null !== $form->get_view_data()]);
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $empty_data = static fn(Form_Interface $form, $view_data) => $view_data;
        $resolver->set_defaults(['value' => '1', 'empty_data' => $empty_data, 'compound' => false, 'false_values' => [null], 'invalid_message' => 'The checkbox has an invalid value.', 'is_empty_callback' => static fn($model_data): bool => false === $model_data]);
        $resolver->set_allowed_types('false_values', 'array');
    }
    public function get_block_prefix(): string
    {
        return 'checkbox';
    }
}