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
 * Represents a node in the AST.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Node implements \Stringable
{
    /**
     * @param array $nodes      An array of nodes
     * @param array $attributes An array of attributes
     */
    public function __construct(public array $nodes = [], public array $attributes = [])
    {
    }
    public function __toString(): string
    {
        $attributes = [];
        foreach ($this->attributes as $name => $value) {
            $attributes[] = \sprintf('%s: %s', $name, str_replace("\n", '', var_export($value, true)));
        }
        $repr = [str_replace('Symfony\Component\ExpressionLanguage\Node\\', '', static::class) . '(' . implode(', ', $attributes)];
        if (\count($this->nodes)) {
            foreach ($this->nodes as $node) {
                foreach (explode("\n", (string) $node) as $line) {
                    $repr[] = '    ' . $line;
                }
            }
            $repr[] = ')';
        } else {
            $repr[0] .= ')';
        }
        return implode("\n", $repr);
    }
    public function compile(Compiler $compiler): void
    {
        foreach ($this->nodes as $node) {
            $node->compile($compiler);
        }
    }
    public function evaluate(array $functions, array $values): mixed
    {
        $results = [];
        foreach ($this->nodes as $node) {
            $results[] = $node->evaluate($functions, $values);
        }
        return $results;
    }
    /**
     * @throws \BadMethodCallException when this node cannot be transformed to an array
     */
    public function to_array(): array
    {
        throw new \BadMethodCallException(\sprintf('Dumping a "%s" instance is not supported yet.', static::class));
    }
    public function dump(): string
    {
        $dump = '';
        foreach ($this->to_array() as $v) {
            $dump .= \is_scalar($v) ? $v : $v->dump();
        }
        return $dump;
    }
    protected function dump_string(string $value): string
    {
        return \sprintf('"%s"', addcslashes($value, "\x00\t\"\\"));
    }
    protected function is_hash(array $value): bool
    {
        $expected_key = 0;
        foreach ($value as $key => $val) {
            if ($key !== $expected_key++) {
                return true;
            }
        }
        return false;
    }
}