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
 * which is copyright Ian Bicking, @see https://github.com/scrapy/cssselect.
 *
 * @author Franck Ranaivo-Harisoa <franckranaivo@gmail.com>
 *
 * @internal
 */
class Relation_Extension extends Abstract_Extension
{
    public function get_relative_combination_translators(): array
    {
        return [' ' => $this->translate_relation_descendant(...), '>' => $this->translate_relation_child(...), '+' => $this->translate_relation_direct_adjacent(...), '~' => $this->translate_relation_indirect_adjacent(...)];
    }
    public function translate_relation_descendant(X_Path_Expr $xpath, X_Path_Expr $combined_xpath): X_Path_Expr
    {
        return $xpath->join('[descendant-or-self::', $combined_xpath, ']', true);
    }
    public function translate_relation_child(X_Path_Expr $xpath, X_Path_Expr $combined_xpath): X_Path_Expr
    {
        return $xpath->join('[./', $combined_xpath, ']', true);
    }
    public function translate_relation_direct_adjacent(X_Path_Expr $xpath, X_Path_Expr $combined_xpath): X_Path_Expr
    {
        $combined_xpath->add_name_test()->add_condition('position() = 1');
        return $xpath->join('[following-sibling::', $combined_xpath, ']', true);
    }
    public function translate_relation_indirect_adjacent(X_Path_Expr $xpath, X_Path_Expr $combined_xpath): X_Path_Expr
    {
        return $xpath->join('[following-sibling::', $combined_xpath, ']', true);
    }
    public function get_name(): string
    {
        return 'relation';
    }
}