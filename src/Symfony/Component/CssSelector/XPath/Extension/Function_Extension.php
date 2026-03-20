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

use Symfony\Component\Css_Selector\Exception\Expression_Error_Exception;
use Symfony\Component\Css_Selector\Exception\Syntax_Error_Exception;
use Symfony\Component\Css_Selector\Node\Function_Node;
use Symfony\Component\Css_Selector\Parser\Parser;
use Symfony\Component\Css_Selector\X_Path\Translator;
use Symfony\Component\Css_Selector\X_Path\X_Path_Expr;
/**
 * XPath expression translator function extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Function_Extension extends Abstract_Extension
{
    public function get_function_translators(): array
    {
        return ['nth-child' => $this->translate_nth_child(...), 'nth-last-child' => $this->translate_nth_last_child(...), 'nth-of-type' => $this->translate_nth_of_type(...), 'nth-last-of-type' => $this->translate_nth_last_of_type(...), 'contains' => $this->translate_contains(...), 'lang' => $this->translate_lang(...)];
    }
    /**
     * @throws ExpressionErrorException
     */
    public function translate_nth_child(X_Path_Expr $xpath, Function_Node $function, bool $last = false, bool $add_name_test = true): X_Path_Expr
    {
        try {
            [$a, $b] = Parser::parse_series($function->get_arguments());
        } catch (Syntax_Error_Exception $e) {
            throw new Expression_Error_Exception(\sprintf('Invalid series: "%s".', implode('", "', $function->get_arguments())), 0, $e);
        }
        $xpath->add_star_prefix();
        if ($add_name_test) {
            $xpath->add_name_test();
        }
        if (0 === $a) {
            return $xpath->add_condition('position() = ' . ($last ? 'last() - ' . ($b - 1) : $b));
        }
        if ($a < 0) {
            if ($b < 1) {
                return $xpath->add_condition('false()');
            }
            $sign = '<=';
        } else {
            $sign = '>=';
        }
        $expr = 'position()';
        if ($last) {
            $expr = 'last() - ' . $expr;
            --$b;
        }
        if (0 !== $b) {
            $expr .= ' - ' . $b;
        }
        $conditions = [\sprintf('%s %s 0', $expr, $sign)];
        if (1 !== $a && -1 !== $a) {
            $conditions[] = \sprintf('(%s) mod %d = 0', $expr, $a);
        }
        return $xpath->add_condition(implode(' and ', $conditions));
        // todo: handle an+b, odd, even
        // an+b means every-a, plus b, e.g., 2n+1 means odd
        // 0n+b means b
        // n+0 means a=1, i.e., all elements
        // an means every a elements, i.e., 2n means even
        // -n means -1n
        // -1n+6 means elements 6 and previous
    }
    public function translate_nth_last_child(X_Path_Expr $xpath, Function_Node $function): X_Path_Expr
    {
        return $this->translate_nth_child($xpath, $function, true);
    }
    public function translate_nth_of_type(X_Path_Expr $xpath, Function_Node $function): X_Path_Expr
    {
        return $this->translate_nth_child($xpath, $function, false, false);
    }
    /**
     * @throws ExpressionErrorException
     */
    public function translate_nth_last_of_type(X_Path_Expr $xpath, Function_Node $function): X_Path_Expr
    {
        if ('*' === $xpath->get_element()) {
            throw new Expression_Error_Exception('"*:nth-of-type()" is not implemented.');
        }
        return $this->translate_nth_child($xpath, $function, true, false);
    }
    /**
     * @throws ExpressionErrorException
     */
    public function translate_contains(X_Path_Expr $xpath, Function_Node $function): X_Path_Expr
    {
        $arguments = $function->get_arguments();
        foreach ($arguments as $token) {
            if (!($token->is_string() || $token->is_identifier())) {
                throw new Expression_Error_Exception('Expected a single string or identifier for :contains(), got ' . implode(', ', $arguments));
            }
        }
        return $xpath->add_condition(\sprintf('contains(string(.), %s)', Translator::get_xpath_literal($arguments[0]->get_value())));
    }
    /**
     * @throws ExpressionErrorException
     */
    public function translate_lang(X_Path_Expr $xpath, Function_Node $function): X_Path_Expr
    {
        $arguments = $function->get_arguments();
        foreach ($arguments as $token) {
            if (!($token->is_string() || $token->is_identifier())) {
                throw new Expression_Error_Exception('Expected a single string or identifier for :lang(), got ' . implode(', ', $arguments));
            }
        }
        return $xpath->add_condition(\sprintf('lang(%s)', Translator::get_xpath_literal($arguments[0]->get_value())));
    }
    public function get_name(): string
    {
        return 'function';
    }
}