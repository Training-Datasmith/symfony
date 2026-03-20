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
namespace Symfony\Component\Config\Definition\Dumper;

use Symfony\Component\Config\Definition\Array_Node;
use Symfony\Component\Config\Definition\Base_Node;
use Symfony\Component\Config\Definition\Boolean_Node;
use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Config\Definition\Enum_Node;
use Symfony\Component\Config\Definition\Float_Node;
use Symfony\Component\Config\Definition\Integer_Node;
use Symfony\Component\Config\Definition\Node_Interface;
use Symfony\Component\Config\Definition\Prototyped_Array_Node;
use Symfony\Component\Config\Definition\Scalar_Node;
/**
 * Dumps an XML reference configuration for the given configuration/node instance.
 *
 * @author Wouter J <waldio.webdesign@gmail.com>
 */
class Xml_Reference_Dumper
{
    private ?string $reference = null;
    public function dump(Configuration_Interface $configuration, ?string $namespace = null): string
    {
        return $this->dump_node($configuration->get_config_tree_builder()->build_tree(), $namespace);
    }
    public function dump_node(Node_Interface $node, ?string $namespace = null): string
    {
        $this->reference = '';
        $this->write_node($node, 0, true, $namespace);
        $ref = $this->reference;
        $this->reference = null;
        return $ref;
    }
    private function write_node(Node_Interface $node, int $depth = 0, bool $root = false, ?string $namespace = null): void
    {
        $root_name = $root ? 'config' : $node->get_name();
        $root_namespace = $namespace ?: ($root ? 'http://example.org/schema/dic/' . $node->get_name() : null);
        // xml remapping
        if ($node->get_parent()) {
            $remapping = array_filter($node->get_parent()->get_xml_remappings(), static fn(array $mapping): bool => $root_name === $mapping[1]);
            if (\count($remapping)) {
                [$singular] = current($remapping);
                $root_name = $singular;
            }
        }
        $root_name = str_replace('_', '-', $root_name);
        $root_attributes = [];
        $root_attribute_comments = [];
        $root_children = [];
        $root_comments = [];
        if ($node instanceof Array_Node) {
            $children = $node->get_children();
            // comments about the root node
            if ($root_info = $node->get_info()) {
                $root_comments[] = $root_info;
            }
            if ($root_namespace) {
                $root_comments[] = 'Namespace: ' . $root_namespace;
            }
            // render prototyped nodes
            if ($node instanceof Prototyped_Array_Node) {
                $prototype = $node->get_prototype();
                $info = 'prototype';
                if (null !== $prototype->get_info()) {
                    $info .= ': ' . $prototype->get_info();
                }
                array_unshift($root_comments, $info);
                if ($key = $node->get_key_attribute()) {
                    $root_attributes[$key] = str_replace('-', ' ', $root_name) . ' ' . $key;
                }
                if ($prototype instanceof Prototyped_Array_Node) {
                    $prototype->set_name($key ?? '');
                    $children = [$key => $prototype];
                } elseif ($prototype instanceof Array_Node) {
                    $children = $prototype->get_children();
                } else if ($prototype->has_default_value()) {
                    $prototype_value = $prototype->get_default_value();
                } else {
                    $prototype_value = match ($prototype::class) {
                        Scalar_Node::class => 'scalar value',
                        Float_Node::class, Integer_Node::class => 'numeric value',
                        Boolean_Node::class => 'true|false',
                        Enum_Node::class => $prototype->get_permissible_values('|'),
                        default => 'value',
                    };
                }
            }
            // get attributes and elements
            foreach ($children as $child) {
                if ($child instanceof Array_Node) {
                    // get elements
                    $root_children[] = $child;
                    continue;
                }
                // get attributes
                // metadata
                $name = str_replace('_', '-', $child->get_name());
                $value = '%%%%not_defined%%%%';
                // use a string which isn't used in the normal world
                // comments
                $comments = [];
                if ($child instanceof Base_Node && $info = $child->get_info()) {
                    $comments[] = $info;
                }
                if ($child instanceof Base_Node && $example = $child->get_example()) {
                    $comments[] = 'Example: ' . (\is_array($example) ? implode(', ', $example) : $example);
                }
                if ($child->is_required()) {
                    $comments[] = 'Required';
                }
                if ($child instanceof Base_Node && $child->is_deprecated()) {
                    $comments[] = \sprintf('Deprecated (%s)', $child->get_deprecation_message($node));
                }
                if ($child instanceof Enum_Node) {
                    $comments[] = 'One of ' . $child->get_permissible_values('; ');
                }
                if (\count($comments)) {
                    $root_attribute_comments[$name] = implode(";\n", $comments);
                }
                // default values
                if ($child->has_default_value()) {
                    $value = $child->get_default_value();
                }
                // append attribute
                $root_attributes[$name] = $value;
            }
        }
        // render comments
        // root node comment
        if (\count($root_comments)) {
            foreach ($root_comments as $comment) {
                $this->write_line('<!-- ' . $comment . ' -->', $depth);
            }
        }
        // attribute comments
        if (\count($root_attribute_comments)) {
            foreach ($root_attribute_comments as $attr_name => $comment) {
                $comment_depth = $depth + 4 + \strlen($attr_name) + 2;
                $comment_lines = explode("\n", $comment);
                $multiline = \count($comment_lines) > 1;
                $comment = implode(\PHP_EOL . str_repeat(' ', $comment_depth), $comment_lines);
                if ($multiline) {
                    $this->write_line('<!--', $depth);
                    $this->write_line($attr_name . ': ' . $comment, $depth + 4);
                    $this->write_line('-->', $depth);
                } else {
                    $this->write_line('<!-- ' . $attr_name . ': ' . $comment . ' -->', $depth);
                }
            }
        }
        // render start tag + attributes
        $root_is_variable_prototype = isset($prototype_value);
        $root_is_empty_tag = 0 === \count($root_children) && !$root_is_variable_prototype;
        $root_open_tag = '<' . $root_name;
        if (1 >= $attributes_count = \count($root_attributes)) {
            if (1 === $attributes_count) {
                $root_open_tag .= \sprintf(' %s="%s"', current(array_keys($root_attributes)), $this->write_value(current($root_attributes)));
            }
            $root_open_tag .= $root_is_empty_tag ? ' />' : '>';
            if ($root_is_variable_prototype) {
                $root_open_tag .= $prototype_value . '</' . $root_name . '>';
            }
            $this->write_line($root_open_tag, $depth);
        } else {
            $this->write_line($root_open_tag, $depth);
            $i = 1;
            foreach ($root_attributes as $attr_name => $attr_value) {
                $attr = \sprintf('%s="%s"', $attr_name, $this->write_value($attr_value));
                $this->write_line($attr, $depth + 4);
                if ($attributes_count === $i++) {
                    $this->write_line($root_is_empty_tag ? '/>' : '>', $depth);
                    if ($root_is_variable_prototype) {
                        $root_open_tag .= $prototype_value . '</' . $root_name . '>';
                    }
                }
            }
        }
        // render children tags
        foreach ($root_children as $child) {
            $this->write_line('');
            $this->write_node($child, $depth + 4);
        }
        // render end tag
        if (!$root_is_empty_tag && !$root_is_variable_prototype) {
            $this->write_line('');
            $root_end_tag = '</' . $root_name . '>';
            $this->write_line($root_end_tag, $depth);
        }
    }
    /**
     * Outputs a single config reference line.
     */
    private function write_line(string $text, int $indent = 0): void
    {
        $indent = \strlen($text) + $indent;
        $format = '%' . $indent . 's';
        $this->reference .= \sprintf($format, $text) . \PHP_EOL;
    }
    /**
     * Renders the string conversion of the value.
     */
    private function write_value(mixed $value): string
    {
        if ('%%%%not_defined%%%%' === $value) {
            return '';
        }
        if (\is_string($value) || is_numeric($value)) {
            return $value;
        }
        if (false === $value) {
            return 'false';
        }
        if (true === $value) {
            return 'true';
        }
        if (null === $value) {
            return 'null';
        }
        if (!$value) {
            return '';
        }
        if (\is_array($value)) {
            return implode(',', $value);
        }
        return '';
    }
}