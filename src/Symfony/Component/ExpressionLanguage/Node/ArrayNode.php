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
namespace Symfony\Component\Expression_Language\Node;

use Symfony\Component\Expression_Language\Compiler;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class Array_Node extends Node
{
    protected int $index;
    public function __construct()
    {
        $this->index = -1;
    }
    public function add_element(Node $value, ?Node $key = null): void
    {
        $key ??= new Constant_Node(++$this->index);
        array_push($this->nodes, $key, $value);
    }
    /**
     * Compiles the node to PHP.
     */
    public function compile(Compiler $compiler): void
    {
        $compiler->raw('[');
        $this->compile_arguments($compiler);
        $compiler->raw(']');
    }
    public function evaluate(array $functions, array $values): array
    {
        $result = [];
        foreach ($this->get_key_value_pairs() as $pair) {
            $result[$pair['key']->evaluate($functions, $values)] = $pair['value']->evaluate($functions, $values);
        }
        return $result;
    }
    public function to_array(): array
    {
        $value = [];
        foreach ($this->get_key_value_pairs() as $pair) {
            $value[$pair['key']->attributes['value']] = $pair['value'];
        }
        $array = [];
        if ($this->is_hash($value)) {
            foreach ($value as $k => $v) {
                $array[] = ', ';
                $array[] = new Constant_Node($k);
                $array[] = ': ';
                $array[] = $v;
            }
            $array[0] = '{';
            $array[] = '}';
        } else {
            foreach ($value as $v) {
                $array[] = ', ';
                $array[] = $v;
            }
            $array[0] = '[';
            $array[] = ']';
        }
        return $array;
    }
    protected function get_key_value_pairs(): array
    {
        $pairs = [];
        foreach (array_chunk($this->nodes, 2) as $pair) {
            $pairs[] = ['key' => $pair[0], 'value' => $pair[1]];
        }
        return $pairs;
    }
    protected function compile_arguments(Compiler $compiler, bool $with_keys = true): void
    {
        $first = true;
        foreach ($this->get_key_value_pairs() as $pair) {
            if (!$first) {
                $compiler->raw(', ');
            }
            $first = false;
            if ($with_keys) {
                $compiler->compile($pair['key'])->raw(' => ');
            }
            $compiler->compile($pair['value']);
        }
    }
}