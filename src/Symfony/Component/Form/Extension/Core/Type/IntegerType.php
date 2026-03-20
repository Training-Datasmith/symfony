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
use Symfony\Component\Form\Extension\Core\Data_Transformer\Integer_To_Localized_String_Transformer;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Integer_Type extends Abstract_Type
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->add_view_transformer(new Integer_To_Localized_String_Transformer($options['grouping'], $options['rounding_mode'], !$options['grouping'] ? 'en' : null));
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        if ($options['grouping']) {
            $view->vars['type'] = 'text';
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults([
            'grouping' => false,
            // Integer cast rounds towards 0, so do the same when displaying fractions
            'rounding_mode' => \Number_Formatter::ROUND_DOWN,
            'compound' => false,
            'invalid_message' => 'Please enter an integer.',
        ]);
        $resolver->set_allowed_values('rounding_mode', [\Number_Formatter::ROUND_FLOOR, \Number_Formatter::ROUND_DOWN, \Number_Formatter::ROUND_HALFDOWN, \Number_Formatter::ROUND_HALFEVEN, \Number_Formatter::ROUND_HALFUP, \Number_Formatter::ROUND_UP, \Number_Formatter::ROUND_CEILING]);
    }
    public function get_block_prefix(): string
    {
        return 'integer';
    }
}