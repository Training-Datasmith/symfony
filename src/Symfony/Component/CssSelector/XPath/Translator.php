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
namespace Symfony\Component\Css_Selector\X_Path;

use Symfony\Component\Css_Selector\Exception\Expression_Error_Exception;
use Symfony\Component\Css_Selector\Node\Function_Node;
use Symfony\Component\Css_Selector\Node\Node_Interface;
use Symfony\Component\Css_Selector\Node\Selector_Node;
use Symfony\Component\Css_Selector\Parser\Parser;
use Symfony\Component\Css_Selector\Parser\Parser_Interface;
/**
 * XPath expression translator interface.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Translator implements Translator_Interface
{
    private readonly Parser_Interface $main_parser;
    /**
     * @var ParserInterface[]
     */
    private array $shortcut_parsers = [];
    /**
     * @var Extension\ExtensionInterface[]
     */
    private array $extensions = [];
    private array $node_translators = [];
    private array $combination_translators = [];
    private array $relative_combination_translators = [];
    private array $function_translators = [];
    private array $pseudo_class_translators = [];
    private array $attribute_matching_translators = [];
    public function __construct(?Parser_Interface $parser = null)
    {
        $this->main_parser = $parser ?? new Parser();
        $this->register_extension(new Extension\Node_Extension())->register_extension(new Extension\Combination_Extension())->register_extension(new Extension\Function_Extension())->register_extension(new Extension\Pseudo_Class_Extension())->register_extension(new Extension\Attribute_Matching_Extension())->register_extension(new Extension\Relation_Extension());
    }
    public static function get_xpath_literal(string $element): string
    {
        if (!str_contains($element, "'")) {
            return "'" . $element . "'";
        }
        if (!str_contains($element, '"')) {
            return '"' . $element . '"';
        }
        $string = $element;
        $parts = [];
        while (true) {
            if (false !== $pos = strpos($string, "'")) {
                $parts[] = \sprintf("'%s'", substr($string, 0, $pos));
                $parts[] = "\"'\"";
                $string = substr($string, $pos + 1);
            } else {
                $parts[] = "'{$string}'";
                break;
            }
        }
        return \sprintf('concat(%s)', implode(', ', $parts));
    }
    public function css_to_x_path(string $css_expr, string $prefix = 'descendant-or-self::'): string
    {
        $selectors = $this->parse_selectors($css_expr);
        foreach ($selectors as $index => $selector) {
            if (null !== $selector->get_pseudo_element()) {
                throw new Expression_Error_Exception('Pseudo-elements are not supported.');
            }
            $selectors[$index] = $this->selector_to_x_path($selector, $prefix);
        }
        return implode(' | ', $selectors);
    }
    public function selector_to_x_path(Selector_Node $selector, string $prefix = 'descendant-or-self::'): string
    {
        return ($prefix ?: '') . $this->node_to_x_path($selector);
    }
    /**
     * @return $this
     */
    public function register_extension(Extension\Extension_Interface $extension): static
    {
        $this->extensions[$extension->get_name()] = $extension;
        $this->node_translators = array_merge($this->node_translators, $extension->get_node_translators());
        $this->combination_translators = array_merge($this->combination_translators, $extension->get_combination_translators());
        $this->function_translators = array_merge($this->function_translators, $extension->get_function_translators());
        $this->pseudo_class_translators = array_merge($this->pseudo_class_translators, $extension->get_pseudo_class_translators());
        $this->attribute_matching_translators = array_merge($this->attribute_matching_translators, $extension->get_attribute_matching_translators());
        $this->relative_combination_translators = array_merge($this->relative_combination_translators, $extension->get_relative_combination_translators());
        return $this;
    }
    /**
     * @throws ExpressionErrorException
     */
    public function get_extension(string $name): Extension\Extension_Interface
    {
        if (!isset($this->extensions[$name])) {
            throw new Expression_Error_Exception(\sprintf('Extension "%s" not registered.', $name));
        }
        return $this->extensions[$name];
    }
    /**
     * @return $this
     */
    public function register_parser_shortcut(Parser_Interface $shortcut): static
    {
        $this->shortcut_parsers[] = $shortcut;
        return $this;
    }
    /**
     * @throws ExpressionErrorException
     */
    public function node_to_x_path(Node_Interface $node): X_Path_Expr
    {
        if (!isset($this->node_translators[$node->get_node_name()])) {
            throw new Expression_Error_Exception(\sprintf('Node "%s" not supported.', $node->get_node_name()));
        }
        return $this->node_translators[$node->get_node_name()]($node, $this);
    }
    /**
     * @throws ExpressionErrorException
     */
    public function add_combination(string $combiner, Node_Interface $xpath, Node_Interface $combined_xpath): X_Path_Expr
    {
        if (!isset($this->combination_translators[$combiner])) {
            throw new Expression_Error_Exception(\sprintf('Combiner "%s" not supported.', $combiner));
        }
        return $this->combination_translators[$combiner]($this->node_to_x_path($xpath), $this->node_to_x_path($combined_xpath));
    }
    /**
     * @throws ExpressionErrorException
     */
    public function add_relative_combination(string $combiner, Node_Interface $xpath, Node_Interface $combined_xpath): X_Path_Expr
    {
        if (!isset($this->relative_combination_translators[$combiner])) {
            throw new Expression_Error_Exception(\sprintf('Combiner "%s" not supported.', $combiner));
        }
        return $this->relative_combination_translators[$combiner]($this->node_to_x_path($xpath), $this->node_to_x_path($combined_xpath));
    }
    /**
     * @throws ExpressionErrorException
     */
    public function add_function(X_Path_Expr $xpath, Function_Node $function): X_Path_Expr
    {
        if (!isset($this->function_translators[$function->get_name()])) {
            throw new Expression_Error_Exception(\sprintf('Function "%s" not supported.', $function->get_name()));
        }
        return $this->function_translators[$function->get_name()]($xpath, $function);
    }
    /**
     * @throws ExpressionErrorException
     */
    public function add_pseudo_class(X_Path_Expr $xpath, string $pseudo_class): X_Path_Expr
    {
        if (!isset($this->pseudo_class_translators[$pseudo_class])) {
            throw new Expression_Error_Exception(\sprintf('Pseudo-class "%s" not supported.', $pseudo_class));
        }
        return $this->pseudo_class_translators[$pseudo_class]($xpath);
    }
    /**
     * @throws ExpressionErrorException
     */
    public function add_attribute_matching(X_Path_Expr $xpath, string $operator, string $attribute, ?string $value): X_Path_Expr
    {
        if (!isset($this->attribute_matching_translators[$operator])) {
            throw new Expression_Error_Exception(\sprintf('Attribute matcher operator "%s" not supported.', $operator));
        }
        return $this->attribute_matching_translators[$operator]($xpath, $attribute, $value);
    }
    /**
     * @return SelectorNode[]
     */
    private function parse_selectors(string $css): array
    {
        foreach ($this->shortcut_parsers as $shortcut) {
            $tokens = $shortcut->parse($css);
            if ($tokens) {
                return $tokens;
            }
        }
        return $this->main_parser->parse($css);
    }
}