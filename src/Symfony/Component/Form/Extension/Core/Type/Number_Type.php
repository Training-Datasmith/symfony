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
use Symfony\Component\Form\Extension\Core\Data_Transformer\Number_To_Localized_String_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\String_To_Float_Transformer;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Number_Type extends Abstract_Type
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->add_view_transformer(new Number_To_Localized_String_Transformer($options['scale'], $options['grouping'], $options['rounding_mode'], $options['html5'] ? 'en' : null));
        if ('string' === $options['input']) {
            $builder->add_model_transformer(new String_To_Float_Transformer($options['scale']));
        }
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        if ($options['html5']) {
            $view->vars['type'] = 'number';
            if (!isset($view->vars['attr']['step'])) {
                $view->vars['attr']['step'] = 'any';
            }
        } else {
            $view->vars['attr']['inputmode'] = 0 === $options['scale'] ? 'numeric' : 'decimal';
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults([
            // default scale is locale specific (usually around 3)
            'scale' => null,
            'grouping' => false,
            'rounding_mode' => \Number_Formatter::ROUND_HALFUP,
            'compound' => false,
            'input' => 'number',
            'html5' => false,
            'invalid_message' => 'Please enter a number.',
        ]);
        $resolver->set_allowed_values('rounding_mode', [\Number_Formatter::ROUND_FLOOR, \Number_Formatter::ROUND_DOWN, \Number_Formatter::ROUND_HALFDOWN, \Number_Formatter::ROUND_HALFEVEN, \Number_Formatter::ROUND_HALFUP, \Number_Formatter::ROUND_UP, \Number_Formatter::ROUND_CEILING]);
        $resolver->set_allowed_values('input', ['number', 'string']);
        $resolver->set_allowed_types('scale', ['null', 'int']);
        $resolver->set_allowed_types('html5', 'bool');
        $resolver->set_normalizer('grouping', static function (Options $options, $value) {
            if (true === $value && $options['html5']) {
                throw new LogicException('Cannot use the "grouping" option when the "html5" option is enabled.');
            }
            return $value;
        });
    }
    public function get_block_prefix(): string
    {
        return 'number';
    }
}