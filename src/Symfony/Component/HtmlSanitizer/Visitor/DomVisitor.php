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
namespace Symfony\Component\Html_Sanitizer\Visitor;

use Symfony\Component\Html_Sanitizer\Html_Sanitizer_Action;
use Symfony\Component\Html_Sanitizer\Html_Sanitizer_Config;
use Symfony\Component\Html_Sanitizer\Text_Sanitizer\String_Sanitizer;
use Symfony\Component\Html_Sanitizer\Visitor\Attribute_Sanitizer\Attribute_Sanitizer_Interface;
use Symfony\Component\Html_Sanitizer\Visitor\Model\Cursor;
use Symfony\Component\Html_Sanitizer\Visitor\Node\Blocked_Node;
use Symfony\Component\Html_Sanitizer\Visitor\Node\Document_Node;
use Symfony\Component\Html_Sanitizer\Visitor\Node\Node;
use Symfony\Component\Html_Sanitizer\Visitor\Node\Node_Interface;
use Symfony\Component\Html_Sanitizer\Visitor\Node\Text_Node;
/**
 * Iterates over the parsed DOM tree to build the sanitized tree.
 *
 * The DomVisitor iterates over the parsed DOM tree, visits its nodes and build
 * a sanitized tree with their attributes and content.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 *
 * @internal
 */
final class Dom_Visitor
{
    private Html_Sanitizer_Action $default_action = Html_Sanitizer_Action::Drop;
    /**
     * Registry of attributes to forcefully set on nodes, index by element and attribute.
     *
     * @var array<string, array<string, string>>
     */
    private array $forced_attributes;
    /**
     * Registry of attributes sanitizers indexed by element name and attribute name for
     * faster sanitization.
     *
     * @var array<string, array<string, list<AttributeSanitizerInterface>>>
     */
    private array $attribute_sanitizers = [];
    /**
     * @param array<string, HtmlSanitizerAction|array<string, bool>> $elementsConfig Registry of allowed/blocked elements:
     *                                                                               * If an element is present as a key and contains an array, the element should be allowed
     *                                                                               and the array is the list of allowed attributes.
     *                                                                               * If an element is present as a key and contains an HtmlSanitizerAction, that action applies.
     *                                                                               * If an element is not present as a key, the default action applies.
     */
    public function __construct(private readonly Html_Sanitizer_Config $config, private array $elements_config)
    {
        $this->forced_attributes = $config->get_forced_attributes();
        foreach ($config->get_attribute_sanitizers() as $attribute_sanitizer) {
            foreach ($attribute_sanitizer->get_supported_elements() ?? ['*'] as $element) {
                foreach ($attribute_sanitizer->get_supported_attributes() ?? ['*'] as $attribute) {
                    $this->attribute_sanitizers[$element][$attribute][] = $attribute_sanitizer;
                }
            }
        }
        $this->default_action = $config->get_default_action();
    }
    public function visit(\Dom\Node|\Dom_Node $dom_node): ?Node_Interface
    {
        $cursor = new Cursor(new Document_Node());
        $this->visit_children($dom_node, $cursor);
        return $cursor->node;
    }
    private function visit_node(\Dom\Node|\Dom_Node $dom_node, Cursor $cursor): void
    {
        $node_name = String_Sanitizer::html_lower($dom_node->node_name);
        // Visit recursively if the node was not dropped
        if ($this->enter_node($node_name, $dom_node, $cursor)) {
            $this->visit_children($dom_node, $cursor);
            $cursor->node = $cursor->node->get_parent();
        }
    }
    private function enter_node(string $dom_node_name, \Dom\Node|\Dom_Node $dom_node, Cursor $cursor): bool
    {
        if (!\array_key_exists($dom_node_name, $this->elements_config)) {
            $action = $this->default_action;
            $allowed_attributes = [];
        } else if (\is_array($this->elements_config[$dom_node_name])) {
            $action = Html_Sanitizer_Action::Allow;
            $allowed_attributes = $this->elements_config[$dom_node_name];
        } else {
            $action = $this->elements_config[$dom_node_name];
            $allowed_attributes = [];
        }
        if (Html_Sanitizer_Action::Drop === $action) {
            return false;
        }
        // Element should be blocked, retaining its children
        if (Html_Sanitizer_Action::Block === $action) {
            $node = new Blocked_Node($cursor->node);
            $cursor->node->add_child($node);
            $cursor->node = $node;
            return true;
        }
        // Otherwise create the node
        $node = new Node($cursor->node, $dom_node_name);
        $this->set_attributes($dom_node_name, $dom_node, $node, $allowed_attributes);
        // Force configured attributes
        foreach ($this->forced_attributes[$dom_node_name] ?? [] as $attribute => $value) {
            $node->set_attribute($attribute, $value, true);
        }
        $cursor->node->add_child($node);
        $cursor->node = $node;
        return true;
    }
    private function visit_children(\Dom\Node|\Dom_Node $dom_node, Cursor $cursor): void
    {
        /** @var \Dom\Node|\DOMNode $child */
        foreach ($dom_node->child_nodes ?? [] as $child) {
            if ('#text' === $child->node_name) {
                // Add text directly for performance
                $cursor->node->add_child(new Text_Node($cursor->node, $child instanceof \Dom\Node ? $child->text_content ?? '' : $child->node_value));
            } elseif (!$child instanceof \Dom\Text && !$child instanceof \Dom\Processing_Instruction && !$child instanceof \Dom_Text && !$child instanceof \Dom_Processing_Instruction) {
                // Otherwise continue the visit recursively
                // Ignore comments for security reasons (interpreted differently by browsers)
                // Ignore processing instructions (treated as comments)
                $this->visit_node($child, $cursor);
            }
        }
    }
    /**
     * Set attributes from a DOM node to a sanitized node.
     */
    private function set_attributes(string $dom_node_name, \Dom\Node|\Dom_Node $dom_node, Node $node, array $allowed_attributes = []): void
    {
        /** @var iterable<\Dom\Attr|\DOMAttr> $domAttributes */
        if (!$dom_attributes = $dom_node->attributes?->getIterator()) {
            return;
        }
        foreach ($dom_attributes as $attribute) {
            $name = String_Sanitizer::html_lower($attribute->name);
            if (isset($allowed_attributes[$name])) {
                $value = $attribute->value;
                // Sanitize the attribute value if there are attribute sanitizers for it
                $attribute_sanitizers = array_merge($this->attribute_sanitizers[$dom_node_name][$name] ?? [], $this->attribute_sanitizers['*'][$name] ?? [], $this->attribute_sanitizers[$dom_node_name]['*'] ?? []);
                foreach ($attribute_sanitizers as $sanitizer) {
                    if (null === $sanitized_value = $sanitizer->sanitize_attribute($dom_node_name, $name, $value, $this->config)) {
                        continue 2;
                    }
                    $value = $sanitized_value;
                }
                $node->set_attribute($name, $value);
            }
        }
    }
}