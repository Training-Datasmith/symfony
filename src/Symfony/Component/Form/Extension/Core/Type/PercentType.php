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
use Symfony\Component\Form\Extension\Core\Data_Transformer\Percent_To_Localized_String_Transformer;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Percent_Type extends Abstract_Type
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->add_view_transformer(new Percent_To_Localized_String_Transformer($options['scale'], $options['type'], $options['rounding_mode'], $options['html5']));
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $view->vars['symbol'] = $options['symbol'];
        if ($options['html5']) {
            $view->vars['type'] = 'number';
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['scale' => 0, 'rounding_mode' => \Number_Formatter::ROUND_HALFUP, 'symbol' => '%', 'type' => 'fractional', 'compound' => false, 'html5' => false, 'invalid_message' => 'Please enter a percentage value.']);
        $resolver->set_allowed_values('type', ['fractional', 'integer']);
        $resolver->set_allowed_values('rounding_mode', [\Number_Formatter::ROUND_FLOOR, \Number_Formatter::ROUND_DOWN, \Number_Formatter::ROUND_HALFDOWN, \Number_Formatter::ROUND_HALFEVEN, \Number_Formatter::ROUND_HALFUP, \Number_Formatter::ROUND_UP, \Number_Formatter::ROUND_CEILING]);
        $resolver->set_allowed_types('scale', 'int');
        $resolver->set_allowed_types('symbol', ['bool', 'string']);
        $resolver->set_allowed_types('html5', 'bool');
    }
    public function get_block_prefix(): string
    {
        return 'percent';
    }
}