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
class Unary_Node extends Node
{
    private const OPERATORS = ['!' => '!', 'not' => '!', '+' => '+', '-' => '-', '~' => '~'];
    public function __construct(string $operator, Node $node)
    {
        parent::__construct(['node' => $node], ['operator' => $operator]);
    }
    public function compile(Compiler $compiler): void
    {
        $compiler->raw('(')->raw(self::OPERATORS[$this->attributes['operator']])->compile($this->nodes['node'])->raw(')');
    }
    public function evaluate(array $functions, array $values): mixed
    {
        $value = $this->nodes['node']->evaluate($functions, $values);
        return match ($this->attributes['operator']) {
            'not', '!' => !$value,
            '-' => -$value,
            '~' => ~$value,
            default => $value,
        };
    }
    public function to_array(): array
    {
        return ['(', $this->attributes['operator'] . ' ', $this->nodes['node'], ')'];
    }
}