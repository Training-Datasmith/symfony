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

use Symfony\Component\Css_Selector\X_Path\X_Path_Expr;
/**
 * XPath expression translator combination extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Combination_Extension extends Abstract_Extension
{
    public function get_combination_translators(): array
    {
        return [' ' => $this->translate_descendant(...), '>' => $this->translate_child(...), '+' => $this->translate_direct_adjacent(...), '~' => $this->translate_indirect_adjacent(...)];
    }
    public function translate_descendant(X_Path_Expr $xpath, X_Path_Expr $combined_xpath): X_Path_Expr
    {
        return $xpath->join('/descendant-or-self::*/', $combined_xpath);
    }
    public function translate_child(X_Path_Expr $xpath, X_Path_Expr $combined_xpath): X_Path_Expr
    {
        return $xpath->join('/', $combined_xpath);
    }
    public function translate_direct_adjacent(X_Path_Expr $xpath, X_Path_Expr $combined_xpath): X_Path_Expr
    {
        return $xpath->join('/following-sibling::', $combined_xpath)->add_name_test()->add_condition('position() = 1');
    }
    public function translate_indirect_adjacent(X_Path_Expr $xpath, X_Path_Expr $combined_xpath): X_Path_Expr
    {
        return $xpath->join('/following-sibling::', $combined_xpath);
    }
    public function get_name(): string
    {
        return 'combination';
    }
}