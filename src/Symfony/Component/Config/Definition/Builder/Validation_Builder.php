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
namespace Symfony\Component\Config\Definition\Builder;

/**
 * This class builds validation conditions.
 *
 * @template T of NodeDefinition
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class Validation_Builder
{
    /**
     * @var (ExprBuilder<T>|\Closure)[]
     */
    public array $rules = [];
    /**
     * @param T $node
     */
    public function __construct(protected Node_Definition $node)
    {
    }
    /**
     * Registers a closure to run as normalization or an expression builder to build it if null is provided.
     *
     * @return ($closure is \Closure ? $this : ExprBuilder<T>)
     */
    public function rule(?\Closure $closure = null): Expr_Builder|static
    {
        if ($closure) {
            $this->rules[] = $closure;
            return $this;
        }
        return $this->rules[] = new Expr_Builder($this->node);
    }
}