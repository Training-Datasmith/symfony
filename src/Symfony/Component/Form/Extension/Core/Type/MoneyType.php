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
use Symfony\Component\Form\Extension\Core\Data_Transformer\Money_To_Localized_String_Transformer;
use Symfony\Component\Form\Extension\Core\Data_Transformer\String_To_Float_Transformer;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Money_Type extends Abstract_Type
{
    protected static array $patterns = [];
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        // Values used in HTML5 number inputs should be formatted as in "1234.5", ie. 'en' format without grouping,
        // according to https://www.w3.org/TR/html51/sec-forms.html#date-time-and-number-formats
        $builder->add_view_transformer(new Money_To_Localized_String_Transformer($options['scale'], $options['grouping'], $options['rounding_mode'], $options['divisor'], $options['html5'] ? 'en' : null, $options['input']));
        if ('string' === $options['input']) {
            $builder->add_model_transformer(new String_To_Float_Transformer($options['scale']));
        }
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $view->vars['money_pattern'] = self::get_pattern($options['currency']);
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
        $resolver->set_defaults(['scale' => 2, 'grouping' => false, 'rounding_mode' => \Number_Formatter::ROUND_HALFUP, 'divisor' => 1, 'currency' => 'EUR', 'compound' => false, 'html5' => false, 'invalid_message' => 'Please enter a valid money amount.', 'input' => 'float']);
        $resolver->set_allowed_values('rounding_mode', [\Number_Formatter::ROUND_FLOOR, \Number_Formatter::ROUND_DOWN, \Number_Formatter::ROUND_HALFDOWN, \Number_Formatter::ROUND_HALFEVEN, \Number_Formatter::ROUND_HALFUP, \Number_Formatter::ROUND_UP, \Number_Formatter::ROUND_CEILING]);
        $resolver->set_allowed_types('scale', 'int');
        $resolver->set_allowed_types('html5', 'bool');
        $resolver->set_allowed_values('input', ['float', 'integer', 'string']);
        $resolver->set_normalizer('grouping', static function (Options $options, $value) {
            if ($value && $options['html5']) {
                throw new LogicException('Cannot use the "grouping" option when the "html5" option is enabled.');
            }
            return $value;
        });
    }
    public function get_block_prefix(): string
    {
        return 'money';
    }
    /**
     * Returns the pattern for this locale in UTF-8.
     *
     * The pattern contains the placeholder "{{ widget }}" where the HTML tag should
     * be inserted
     */
    protected static function get_pattern(?string $currency): string
    {
        if (!$currency) {
            return '{{ widget }}';
        }
        $locale = \Locale::get_default();
        if (!isset(self::$patterns[$locale])) {
            self::$patterns[$locale] = [];
        }
        if (!isset(self::$patterns[$locale][$currency])) {
            $format = new \Number_Formatter($locale, \Number_Formatter::CURRENCY);
            $pattern = $format->format_currency('123', $currency);
            // the spacings between currency symbol and number are ignored, because
            // a single space leads to better readability in combination with input
            // fields
            // the regex also considers non-break spaces (0xC2 or 0xA0 in UTF-8)
            preg_match('/^([^\s\xc2\xa0]*)[\s\xc2\xa0]*123(?:[,.]0+)?[\s\xc2\xa0]*([^\s\xc2\xa0]*)$/u', $pattern, $matches);
            if (!empty($matches[1])) {
                self::$patterns[$locale][$currency] = $matches[1] . ' {{ widget }}';
            } elseif (!empty($matches[2])) {
                self::$patterns[$locale][$currency] = '{{ widget }} ' . $matches[2];
            } else {
                self::$patterns[$locale][$currency] = '{{ widget }}';
            }
        }
        return self::$patterns[$locale][$currency];
    }
}