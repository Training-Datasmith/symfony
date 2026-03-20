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
use Symfony\Component\Form\Extension\Core\Data_Transformer\Value_To_Duplicates_Transformer;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Repeated_Type extends Abstract_Type
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        // Overwrite required option for child fields
        $options['first_options']['required'] = $options['required'];
        $options['second_options']['required'] = $options['required'];
        if (!isset($options['options']['error_bubbling'])) {
            $options['options']['error_bubbling'] = $options['error_bubbling'];
        }
        // children fields must always be mapped
        $default_options = ['mapped' => true];
        $builder->add_view_transformer(new Value_To_Duplicates_Transformer([$options['first_name'], $options['second_name']]))->add($options['first_name'], $options['type'], array_merge($options['options'], $options['first_options'], $default_options))->add($options['second_name'], $options['type'], array_merge($options['options'], $options['second_options'], $default_options));
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['type' => Text_Type::class, 'options' => [], 'first_options' => [], 'second_options' => [], 'first_name' => 'first', 'second_name' => 'second', 'error_bubbling' => false, 'invalid_message' => 'The values do not match.']);
        $resolver->set_allowed_types('options', 'array');
        $resolver->set_allowed_types('first_options', 'array');
        $resolver->set_allowed_types('second_options', 'array');
    }
    public function get_block_prefix(): string
    {
        return 'repeated';
    }
}