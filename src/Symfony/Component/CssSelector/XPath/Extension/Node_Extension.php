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

use Symfony\Component\Css_Selector\Node;
use Symfony\Component\Css_Selector\X_Path\Translator;
use Symfony\Component\Css_Selector\X_Path\X_Path_Expr;
/**
 * XPath expression translator node extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/scrapy/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Node_Extension extends Abstract_Extension
{
    public const ELEMENT_NAME_IN_LOWER_CASE = 1;
    public const ATTRIBUTE_NAME_IN_LOWER_CASE = 2;
    public const ATTRIBUTE_VALUE_IN_LOWER_CASE = 4;
    public function __construct(private int $flags = 0)
    {
    }
    /**
     * @return $this
     */
    public function set_flag(int $flag, bool $on): static
    {
        if ($on && !$this->has_flag($flag)) {
            $this->flags += $flag;
        }
        if (!$on && $this->has_flag($flag)) {
            $this->flags -= $flag;
        }
        return $this;
    }
    public function has_flag(int $flag): bool
    {
        return (bool) ($this->flags & $flag);
    }
    public function get_node_translators(): array
    {
        return ['Selector' => $this->translate_selector(...), 'CombinedSelector' => $this->translate_combined_selector(...), 'Negation' => $this->translate_negation(...), 'Matching' => $this->translate_matching(...), 'SpecificityAdjustment' => $this->translate_specificity_adjustment(...), 'Function' => $this->translate_function(...), 'Pseudo' => $this->translate_pseudo(...), 'Attribute' => $this->translate_attribute(...), 'Class' => $this->translate_class(...), 'Hash' => $this->translate_hash(...), 'Element' => $this->translate_element(...), 'Relation' => $this->translate_relation(...)];
    }
    public function translate_selector(Node\Selector_Node $node, Translator $translator): X_Path_Expr
    {
        return $translator->node_to_x_path($node->get_tree());
    }
    public function translate_combined_selector(Node\Combined_Selector_Node $node, Translator $translator): X_Path_Expr
    {
        return $translator->add_combination($node->get_combinator(), $node->get_selector(), $node->get_sub_selector());
    }
    public function translate_negation(Node\Negation_Node $node, Translator $translator): X_Path_Expr
    {
        $xpath = $translator->node_to_x_path($node->get_selector());
        $sub_xpath = $translator->node_to_x_path($node->get_sub_selector());
        $sub_xpath->add_name_test();
        if ($sub_xpath->get_condition()) {
            return $xpath->add_condition(\sprintf('not(%s)', $sub_xpath->get_condition()));
        }
        return $xpath->add_condition('0');
    }
    public function translate_matching(Node\Matching_Node $node, Translator $translator): X_Path_Expr
    {
        $xpath = $translator->node_to_x_path($node->selector);
        foreach ($node->arguments as $argument) {
            $expr = $translator->node_to_x_path($argument);
            $expr->add_name_test();
            if ($condition = $expr->get_condition()) {
                $xpath->add_condition($condition, 'or');
            }
        }
        return $xpath;
    }
    public function translate_specificity_adjustment(Node\Specificity_Adjustment_Node $node, Translator $translator): X_Path_Expr
    {
        $xpath = $translator->node_to_x_path($node->selector);
        foreach ($node->arguments as $argument) {
            $expr = $translator->node_to_x_path($argument);
            $expr->add_name_test();
            if ($condition = $expr->get_condition()) {
                $xpath->add_condition($condition, 'or');
            }
        }
        return $xpath;
    }
    public function translate_function(Node\Function_Node $node, Translator $translator): X_Path_Expr
    {
        $xpath = $translator->node_to_x_path($node->get_selector());
        return $translator->add_function($xpath, $node);
    }
    public function translate_pseudo(Node\Pseudo_Node $node, Translator $translator): X_Path_Expr
    {
        $xpath = $translator->node_to_x_path($node->get_selector());
        return $translator->add_pseudo_class($xpath, $node->get_identifier());
    }
    public function translate_attribute(Node\Attribute_Node $node, Translator $translator): X_Path_Expr
    {
        $name = $node->get_attribute();
        $safe = $this->is_safe_name($name);
        if ($this->has_flag(self::ATTRIBUTE_NAME_IN_LOWER_CASE)) {
            $name = strtolower($name);
        }
        if ($node->get_namespace()) {
            $name = \sprintf('%s:%s', $node->get_namespace(), $name);
            $safe = $safe && $this->is_safe_name($node->get_namespace());
        }
        $attribute = $safe ? '@' . $name : \sprintf('attribute::*[name() = %s]', Translator::get_xpath_literal($name));
        $value = $node->get_value();
        $xpath = $translator->node_to_x_path($node->get_selector());
        if ($this->has_flag(self::ATTRIBUTE_VALUE_IN_LOWER_CASE)) {
            $value = strtolower((string) $value);
        }
        return $translator->add_attribute_matching($xpath, $node->get_operator(), $attribute, $value);
    }
    public function translate_class(Node\Class_Node $node, Translator $translator): X_Path_Expr
    {
        $xpath = $translator->node_to_x_path($node->get_selector());
        return $translator->add_attribute_matching($xpath, '~=', '@class', $node->get_name());
    }
    public function translate_hash(Node\Hash_Node $node, Translator $translator): X_Path_Expr
    {
        $xpath = $translator->node_to_x_path($node->get_selector());
        return $translator->add_attribute_matching($xpath, '=', '@id', $node->get_id());
    }
    public function translate_element(Node\Element_Node $node): X_Path_Expr
    {
        $element = $node->get_element();
        if ($element && $this->has_flag(self::ELEMENT_NAME_IN_LOWER_CASE)) {
            $element = strtolower($element);
        }
        if ($element) {
            $safe = $this->is_safe_name($element);
        } else {
            $element = '*';
            $safe = true;
        }
        if ($node->get_namespace()) {
            $element = \sprintf('%s:%s', $node->get_namespace(), $element);
            $safe = $safe && $this->is_safe_name($node->get_namespace());
        }
        $xpath = new X_Path_Expr('', $element);
        if (!$safe) {
            $xpath->add_name_test();
        }
        return $xpath;
    }
    public function translate_relation(Node\Relation_Node $node, Translator $translator): X_Path_Expr
    {
        $combinator = $node->get_combinator();
        return $translator->add_relative_combination($combinator, $node->get_selector(), $node->get_sub_selector());
    }
    public function get_name(): string
    {
        return 'node';
    }
    private function is_safe_name(string $name): bool
    {
        return 0 < preg_match('~^[a-zA-Z_][a-zA-Z0-9_.-]*$~', $name);
    }
}