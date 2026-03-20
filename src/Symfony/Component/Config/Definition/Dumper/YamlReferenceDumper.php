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
use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Config\Definition\Enum_Node;
use Symfony\Component\Config\Definition\Node_Interface;
use Symfony\Component\Config\Definition\Prototyped_Array_Node;
use Symfony\Component\Config\Definition\Scalar_Node;
use Symfony\Component\Yaml\Inline;
/**
 * Dumps a Yaml reference configuration for the given configuration/node instance.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class Yaml_Reference_Dumper
{
    private ?string $reference = null;
    public function dump(Configuration_Interface $configuration): string
    {
        return $this->dump_node($configuration->get_config_tree_builder()->build_tree());
    }
    public function dump_at_path(Configuration_Interface $configuration, string $path): string
    {
        $root_node = $node = $configuration->get_config_tree_builder()->build_tree();
        foreach (explode('.', $path) as $step) {
            if (!$node instanceof Array_Node) {
                throw new \UnexpectedValueException(\sprintf('Unable to find node at path "%s.%s".', $root_node->get_name(), $path));
            }
            /** @var NodeInterface[] $children */
            $children = $node instanceof Prototyped_Array_Node ? $this->get_prototype_children($node) : $node->get_children();
            foreach ($children as $child) {
                if ($child->get_name() === $step) {
                    $node = $child;
                    continue 2;
                }
            }
            throw new \UnexpectedValueException(\sprintf('Unable to find node at path "%s.%s".', $root_node->get_name(), $path));
        }
        return $this->dump_node($node);
    }
    public function dump_node(Node_Interface $node): string
    {
        $this->reference = '';
        $this->write_node($node);
        $ref = $this->reference;
        $this->reference = null;
        return $ref;
    }
    private function write_node(Node_Interface $node, ?Node_Interface $parent_node = null, int $depth = 0, bool $prototyped_array = false): void
    {
        $comments = [];
        $default = '';
        $default_array = null;
        $children = null;
        $example = null;
        if ($node instanceof Base_Node) {
            $example = $node->get_example();
        }
        // defaults
        if ($node instanceof Array_Node) {
            $children = $node->get_children();
            if ($node instanceof Prototyped_Array_Node) {
                $children = $this->get_prototype_children($node);
            }
            if (!$children && !($node->has_default_value() && $default_array = $node->get_default_value())) {
                $default = $node->has_default_value() && null === $default_array ? '~' : '[]';
            }
        } elseif ($node instanceof Enum_Node) {
            $comments[] = 'One of ' . $node->get_permissible_values('; ');
            $default = $node->has_default_value() ? Inline::dump($node->get_default_value()) : '~';
        } else {
            $default = '~';
            if ($node->has_default_value()) {
                $default = $node->get_default_value();
                if (\is_array($default)) {
                    if (\count($default_array = $node->get_default_value())) {
                        $default = '';
                    } elseif (!\is_array($example)) {
                        $default = '[]';
                    }
                } else {
                    $default = Inline::dump($default);
                }
            }
        }
        // required?
        if ($node->is_required()) {
            $comments[] = 'Required';
        }
        // deprecated?
        if ($node instanceof Base_Node && $node->is_deprecated()) {
            $comments[] = \sprintf('Deprecated (%s)', $node->get_deprecation_message($parent_node));
        }
        // example
        if ($example && !\is_array($example)) {
            $comments[] = 'Example: ' . Inline::dump($example);
        }
        $default = '' != (string) $default ? ' ' . $default : '';
        $comments = \count($comments) ? '# ' . implode(', ', $comments) : '';
        $key = $prototyped_array ? '-' : $node->get_name() . ':';
        $text = rtrim(\sprintf('%-21s%s %s', $key, $default, $comments), ' ');
        if ($node instanceof Base_Node && $info = $node->get_info()) {
            $this->write_line('');
            // indenting multi-line info
            $info = str_replace("\n", \sprintf("\n%" . $depth * 4 . 's# ', ' '), $info);
            $this->write_line('# ' . $info, $depth * 4);
        }
        $this->write_line($text, $depth * 4);
        // output defaults
        if ($default_array) {
            $this->write_line('');
            $message = \count($default_array) > 1 ? 'Defaults' : 'Default';
            $this->write_line('# ' . $message . ':', $depth * 4 + 4);
            $this->write_array($default_array, $depth + 1);
        }
        if (\is_array($example)) {
            $this->write_line('');
            $message = \count($example) > 1 ? 'Examples' : 'Example';
            $this->write_line('# ' . $message . ':', $depth * 4 + 4);
            $this->write_array(array_map(Inline::dump(...), $example), $depth + 1, true);
        }
        if ($children) {
            foreach ($children as $child_node) {
                $this->write_node($child_node, $node, $depth + 1, $node instanceof Prototyped_Array_Node && !$node->get_key_attribute());
            }
        }
    }
    /**
     * Outputs a single config reference line.
     */
    private function write_line(string $text, int $indent = 0): void
    {
        $indent = \strlen($text) + $indent;
        $format = '%' . $indent . 's';
        $this->reference .= \sprintf($format, $text) . "\n";
    }
    private function write_array(array $array, int $depth, bool $as_comment = false): void
    {
        $is_indexed = array_is_list($array);
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $val = '';
            } else {
                $val = $value;
            }
            $prefix = $as_comment ? '# ' : '';
            if ($is_indexed) {
                $this->write_line($prefix . '- ' . $val, $depth * 4);
            } else {
                $this->write_line(\sprintf('%s%-20s %s', $prefix, $key . ':', $val), $depth * 4);
            }
            if (\is_array($value)) {
                $this->write_array($value, $depth + 1, $as_comment);
            }
        }
    }
    private function get_prototype_children(Prototyped_Array_Node $node): array
    {
        $prototype = $node->get_prototype();
        $key = $node->get_key_attribute();
        // Do not expand prototype if it isn't an array node nor uses attribute as key
        if (!$key && !$prototype instanceof Array_Node) {
            return $node->get_children();
        }
        if ($prototype instanceof Array_Node) {
            $key_node = new Array_Node($key, $node);
            $children = $prototype->get_children();
            if ($prototype instanceof Prototyped_Array_Node && $prototype->get_key_attribute()) {
                $children = $this->get_prototype_children($prototype);
            }
            // add children
            foreach ($children as $child_node) {
                $key_node->add_child($child_node);
            }
        } else {
            $key_node = new Scalar_Node($key, $node);
        }
        $info = 'Prototype';
        if (null !== $prototype->get_info()) {
            $info .= ': ' . $prototype->get_info();
        }
        $key_node->set_info($info);
        return [$key ?? '' => $key_node];
    }
}