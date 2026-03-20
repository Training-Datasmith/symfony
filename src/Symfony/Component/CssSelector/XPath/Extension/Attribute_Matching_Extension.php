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
namespace Symfony\Component\Css_Selector\X_Path\Extension;

use Symfony\Component\Css_Selector\X_Path\Translator;
use Symfony\Component\Css_Selector\X_Path\X_Path_Expr;
/**
 * XPath expression translator attribute extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Attribute_Matching_Extension extends Abstract_Extension
{
    public function get_attribute_matching_translators(): array
    {
        return ['exists' => $this->translate_exists(...), '=' => $this->translate_equals(...), '~=' => $this->translate_includes(...), '|=' => $this->translate_dash_match(...), '^=' => $this->translate_prefix_match(...), '$=' => $this->translate_suffix_match(...), '*=' => $this->translate_substring_match(...), '!=' => $this->translate_different(...)];
    }
    public function translate_exists(X_Path_Expr $xpath, string $attribute, ?string $value): X_Path_Expr
    {
        return $xpath->add_condition($attribute);
    }
    public function translate_equals(X_Path_Expr $xpath, string $attribute, ?string $value): X_Path_Expr
    {
        return $xpath->add_condition(\sprintf('%s = %s', $attribute, Translator::get_xpath_literal($value)));
    }
    public function translate_includes(X_Path_Expr $xpath, string $attribute, ?string $value): X_Path_Expr
    {
        return $xpath->add_condition($value ? \sprintf('%1$s and contains(concat(\' \', normalize-space(%1$s), \' \'), %2$s)', $attribute, Translator::get_xpath_literal(' ' . $value . ' ')) : '0');
    }
    public function translate_dash_match(X_Path_Expr $xpath, string $attribute, ?string $value): X_Path_Expr
    {
        return $xpath->add_condition(\sprintf('%1$s and (%1$s = %2$s or starts-with(%1$s, %3$s))', $attribute, Translator::get_xpath_literal($value), Translator::get_xpath_literal($value . '-')));
    }
    public function translate_prefix_match(X_Path_Expr $xpath, string $attribute, ?string $value): X_Path_Expr
    {
        return $xpath->add_condition($value ? \sprintf('%1$s and starts-with(%1$s, %2$s)', $attribute, Translator::get_xpath_literal($value)) : '0');
    }
    public function translate_suffix_match(X_Path_Expr $xpath, string $attribute, ?string $value): X_Path_Expr
    {
        return $xpath->add_condition($value ? \sprintf('%1$s and substring(%1$s, string-length(%1$s)-%2$s) = %3$s', $attribute, \strlen($value) - 1, Translator::get_xpath_literal($value)) : '0');
    }
    public function translate_substring_match(X_Path_Expr $xpath, string $attribute, ?string $value): X_Path_Expr
    {
        return $xpath->add_condition($value ? \sprintf('%1$s and contains(%1$s, %2$s)', $attribute, Translator::get_xpath_literal($value)) : '0');
    }
    public function translate_different(X_Path_Expr $xpath, string $attribute, ?string $value): X_Path_Expr
    {
        return $xpath->add_condition(\sprintf($value ? 'not(%1$s) or %1$s != %2$s' : '%s != %s', $attribute, Translator::get_xpath_literal($value)));
    }
    public function get_name(): string
    {
        return 'attribute-matching';
    }
}