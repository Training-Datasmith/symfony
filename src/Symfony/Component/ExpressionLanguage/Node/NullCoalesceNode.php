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
class Null_Coalesce_Node extends Node
{
    public function __construct(Node $expr1, Node $expr2)
    {
        parent::__construct(['expr1' => $expr1, 'expr2' => $expr2]);
    }
    public function compile(Compiler $compiler): void
    {
        $compiler->raw('((')->compile($this->nodes['expr1'])->raw(') ?? (')->compile($this->nodes['expr2'])->raw('))');
    }
    public function evaluate(array $functions, array $values): mixed
    {
        if ($this->nodes['expr1'] instanceof Get_Attr_Node) {
            $this->add_null_coalesce_attribute_to_get_attr_nodes($this->nodes['expr1']);
        }
        return $this->nodes['expr1']->evaluate($functions, $values) ?? $this->nodes['expr2']->evaluate($functions, $values);
    }
    public function to_array(): array
    {
        return ['(', $this->nodes['expr1'], ') ?? (', $this->nodes['expr2'], ')'];
    }
    private function add_null_coalesce_attribute_to_get_attr_nodes(Node $node): void
    {
        if (!$node instanceof Get_Attr_Node) {
            return;
        }
        $node->attributes['is_null_coalesce'] = true;
        foreach ($node->nodes as $node) {
            $this->add_null_coalesce_attribute_to_get_attr_nodes($node);
        }
    }
}