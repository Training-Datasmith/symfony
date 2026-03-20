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
namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Loader\Param_Configurator;
/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class Array_Shape_Generator
{
    public static function generate(Node_Interface $node): string
    {
        return str_replace("\n", "\n * ", self::do_generate_php_doc($node));
    }
    private static function do_generate_php_doc(Node_Interface $node, int $nesting_level = 1): string
    {
        if (!$node instanceof Array_Node) {
            $type_string = match (true) {
                $node instanceof Boolean_Node => $node->has_default_value() && null === $node->get_default_value() ? 'bool|null' : 'bool',
                $node instanceof String_Node => 'string',
                $node instanceof Numeric_Node => self::handle_numeric_node($node),
                $node instanceof Enum_Node => $node->get_permissible_values('|', false),
                $node instanceof Scalar_Node => 'scalar|null',
                default => 'mixed',
            };
            if ('mixed' === $type_string) {
                return $type_string;
            }
            if (str_ends_with($type_string, '|null')) {
                return substr_replace($type_string, '|\\' . Param_Configurator::class, -5, 0);
            }
            return $type_string . '|\\' . Param_Configurator::class;
        }
        if ($node instanceof Prototyped_Array_Node) {
            $is_hashmap = (bool) $node->get_key_attribute();
            $array_shape = ($is_hashmap ? 'array<string, ' : 'list<') . self::do_generate_php_doc($node->get_prototype(), 1 + $nesting_level) . '>';
            return implode('|', [...self::get_normalized_types($node, ['array', 'any']), $array_shape]);
        }
        if (!($children = $node->get_children()) && !$node->get_parent() instanceof Prototyped_Array_Node) {
            return $node->has_default_value() && null === $node->get_default_value() ? 'array<mixed>|null' : 'array<mixed>';
        }
        $array_shape = \sprintf("array{%s\n", self::generate_inline_php_doc_for_node($node));
        foreach ($children as $child) {
            $array_shape .= str_repeat('    ', $nesting_level) . self::dump_node_key($child, $node) . ': ';
            if ($child instanceof Prototyped_Array_Node) {
                $is_hashmap = (bool) $child->get_key_attribute();
                $child_array_type = ($is_hashmap ? 'array<string, ' : 'list<') . self::do_generate_php_doc($child->get_prototype(), 1 + $nesting_level) . '>';
                $array_shape .= $child->has_default_value() && null === $child->get_default_value() ? $child_array_type . '|null' : $child_array_type;
            } else {
                $array_shape .= self::do_generate_php_doc($child, 1 + $nesting_level);
            }
            $array_shape .= \sprintf(",%s\n", !$child instanceof Array_Node ? self::generate_inline_php_doc_for_node($child) : '');
        }
        if ($node->should_ignore_extra_keys()) {
            $array_shape .= str_repeat('    ', $nesting_level) . "...<mixed>\n";
        }
        $array_shape = $array_shape . str_repeat('    ', $nesting_level - 1) . '}';
        return implode('|', [...self::get_normalized_types($node, ['array', 'any']), $array_shape]);
    }
    private static function dump_node_key(Node_Interface $node, ?Array_Node $parent = null): string
    {
        $name = $node->get_name();
        $quoted = str_starts_with($name, '@') || \in_array(strtolower($name), ['int', 'float', 'bool', 'null', 'scalar'], true) || strpbrk($name, '\'"');
        if ($quoted) {
            $name = "'" . addslashes($name) . "'";
        }
        $optional = !$node->is_required() || $parent instanceof Array_Node && $parent->should_perform_deep_merging();
        return $name . ($optional ? '?' : '');
    }
    private static function handle_numeric_node(Numeric_Node $node): string
    {
        // We could use int<%s, %s> but PhpStorm doesn't support it yet
        // $min = $node->getMin() ?? 'min';
        // $max = $node->getMax() ?? 'max';
        if ($node instanceof Integer_Node) {
            return 'int';
        }
        if ($node instanceof Float_Node) {
            return 'float';
        }
        return 'int|float';
    }
    private static function generate_inline_php_doc_for_node(Base_Node $node): string
    {
        $comment = '';
        if ($node->is_deprecated()) {
            $comment .= ' // Deprecated: ' . $node->get_deprecation($node->get_name(), $node->get_path())['message'];
        }
        if ($info = $node->get_info()) {
            $comment .= ' // ' . $info;
        }
        if ((!$node instanceof Array_Node || ($node = $node->get_parent()) instanceof Prototyped_Array_Node) && $node->has_default_value()) {
            $comment .= ' // Default: ' . json_encode($node->get_default_value(), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
        }
        return rtrim((string) preg_replace('/\s+/', ' ', $comment));
    }
    /**
     * @return list<string>
     */
    private static function get_normalized_types(Base_Node $node, array $excluded = []): array
    {
        $types = array_diff($node->get_normalized_types(), $excluded);
        if ($node->has_default_value() && null === $node->get_default_value()) {
            $types[] = 'null';
        }
        $types = array_unique($types);
        sort($types);
        return $types;
    }
}