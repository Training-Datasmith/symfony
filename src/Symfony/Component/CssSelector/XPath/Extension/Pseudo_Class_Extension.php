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
use Symfony\Component\Css_Selector\X_Path\X_Path_Expr;
/**
 * XPath expression translator pseudo-class extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Pseudo_Class_Extension extends Abstract_Extension
{
    public function get_pseudo_class_translators(): array
    {
        return ['root' => $this->translate_root(...), 'scope' => $this->translate_scope_pseudo(...), 'first-child' => $this->translate_first_child(...), 'last-child' => $this->translate_last_child(...), 'first-of-type' => $this->translate_first_of_type(...), 'last-of-type' => $this->translate_last_of_type(...), 'only-child' => $this->translate_only_child(...), 'only-of-type' => $this->translate_only_of_type(...), 'empty' => $this->translate_empty(...)];
    }
    public function translate_root(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_condition('not(parent::*)');
    }
    public function translate_scope_pseudo(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_condition('1');
    }
    public function translate_first_child(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_star_prefix()->add_name_test()->add_condition('position() = 1');
    }
    public function translate_last_child(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_star_prefix()->add_name_test()->add_condition('position() = last()');
    }
    /**
     * @throws ExpressionErrorException
     */
    public function translate_first_of_type(X_Path_Expr $xpath): X_Path_Expr
    {
        if ('*' === $xpath->get_element()) {
            throw new Expression_Error_Exception('"*:first-of-type" is not implemented.');
        }
        return $xpath->add_star_prefix()->add_condition('position() = 1');
    }
    /**
     * @throws ExpressionErrorException
     */
    public function translate_last_of_type(X_Path_Expr $xpath): X_Path_Expr
    {
        if ('*' === $xpath->get_element()) {
            throw new Expression_Error_Exception('"*:last-of-type" is not implemented.');
        }
        return $xpath->add_star_prefix()->add_condition('position() = last()');
    }
    public function translate_only_child(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_star_prefix()->add_name_test()->add_condition('last() = 1');
    }
    public function translate_only_of_type(X_Path_Expr $xpath): X_Path_Expr
    {
        $element = $xpath->get_element();
        return $xpath->add_condition(\sprintf('count(preceding-sibling::%s)=0 and count(following-sibling::%s)=0', $element, $element));
    }
    public function translate_empty(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_condition('not(*) and not(string-length())');
    }
    public function get_name(): string
    {
        return 'pseudo-class';
    }
}