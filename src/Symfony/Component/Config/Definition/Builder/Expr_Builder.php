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

use Symfony\Component\Config\Definition\Exception\Unset_Key_Exception;
/**
 * This class builds an if expression.
 *
 * @template T of NodeDefinition
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class Expr_Builder
{
    public const TYPE_ANY = 'any';
    public const TYPE_STRING = 'string';
    public const TYPE_NULL = 'null';
    public const TYPE_ARRAY = 'array';
    public const TYPE_BOOL = 'bool';
    public const TYPE_INT = 'int';
    public const TYPE_BACKED_ENUM = 'backed-enum';
    public string $allowed_types;
    public ?\Closure $if_part = null;
    public ?\Closure $then_part = null;
    /**
     * @param T $node
     */
    public function __construct(protected Node_Definition $node)
    {
    }
    /**
     * Marks the expression as being always used.
     *
     * @return $this
     */
    public function always(?\Closure $then = null): static
    {
        $this->if_part = static fn(): true => true;
        $this->allowed_types = self::TYPE_ANY;
        if (null !== $then) {
            $this->then_part = $then;
        }
        return $this;
    }
    /**
     * Sets a closure to use as tests.
     *
     * The default one tests if the value is true.
     *
     * @return $this
     */
    public function if_true(?\Closure $closure = null): static
    {
        $this->if_part = $closure ?? static fn($v): bool => true === $v;
        $this->allowed_types = $closure ? self::TYPE_ANY : self::TYPE_BOOL;
        return $this;
    }
    /**
     * Sets a closure to use as tests.
     *
     * The default one tests if the value is false.
     *
     * @return $this
     */
    public function if_false(?\Closure $closure = null): static
    {
        $this->if_part = $closure ? static fn($v): bool => !$closure($v) : static fn($v): bool => false === $v;
        $this->allowed_types = $closure ? self::TYPE_ANY : self::TYPE_BOOL;
        return $this;
    }
    /**
     * Tests if the value is a string.
     *
     * @return $this
     */
    public function if_string(): static
    {
        $this->if_part = \is_string(...);
        $this->allowed_types = self::TYPE_STRING;
        return $this;
    }
    /**
     * Tests if the value is null.
     *
     * @return $this
     */
    public function if_null(): static
    {
        $this->if_part = \is_null(...);
        $this->allowed_types = self::TYPE_NULL;
        return $this;
    }
    /**
     * Tests if the value is empty.
     *
     * @return $this
     */
    public function if_empty(): static
    {
        $this->if_part = static fn($v): bool => !$v;
        $this->allowed_types = self::TYPE_ANY;
        return $this;
    }
    /**
     * Tests if the value is an array.
     *
     * @return $this
     */
    public function if_array(): static
    {
        $this->if_part = \is_array(...);
        $this->allowed_types = self::TYPE_ARRAY;
        return $this;
    }
    /**
     * Tests if the value is in an array.
     *
     * @return $this
     */
    public function if_in_array(array $array): static
    {
        $this->if_part = static fn($v): bool => \in_array($v, $array, true);
        $this->allowed_types = self::TYPE_ANY;
        return $this;
    }
    /**
     * Tests if the value is not in an array.
     *
     * @return $this
     */
    public function if_not_in_array(array $array): static
    {
        $this->if_part = static fn($v): bool => !\in_array($v, $array, true);
        $this->allowed_types = self::TYPE_ANY;
        return $this;
    }
    /**
     * Transforms variables of any type into an array.
     *
     * @return $this
     */
    public function cast_to_array(): static
    {
        $this->if_part = static fn($v): bool => !\is_array($v);
        $this->allowed_types = self::TYPE_ANY;
        $this->then_part = static fn($v): array => [$v];
        return $this;
    }
    /**
     * Sets the closure to run if the test pass.
     *
     * @return $this
     */
    public function then(\Closure $closure): static
    {
        $this->then_part = $closure;
        return $this;
    }
    /**
     * Sets a closure returning an empty array.
     *
     * @return $this
     */
    public function then_empty_array(): static
    {
        $this->then_part = static fn(): array => [];
        return $this;
    }
    /**
     * Sets a closure marking the value as invalid at processing time.
     *
     * if you want to add the value of the node in your message just use a %s placeholder.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function then_invalid(string $message): static
    {
        $this->then_part = static fn($v) => throw new \InvalidArgumentException(\sprintf($message, json_encode($v)));
        return $this;
    }
    /**
     * Sets a closure unsetting this key of the array at processing time.
     *
     * @return $this
     *
     * @throws UnsetKeyException
     */
    public function then_unset(): static
    {
        $this->then_part = static fn() => throw new Unset_Key_Exception('Unsetting key.');
        return $this;
    }
    /**
     * Returns the related node.
     *
     * @return T
     *
     * @throws \RuntimeException
     */
    public function end(): Node_Definition
    {
        if (null === $this->if_part) {
            throw new \RuntimeException('You must specify an if part.');
        }
        if (null === $this->then_part) {
            throw new \RuntimeException('You must specify a then part.');
        }
        return $this->node;
    }
    /**
     * Builds the expressions.
     *
     * @param (ExprBuilder|\Closure)[] $expressions
     *
     * @return \Closure[]
     */
    public static function build_expressions(array $expressions): array
    {
        foreach ($expressions as $k => $expr) {
            if ($expr instanceof self) {
                $if = $expr->if_part;
                $then = $expr->then_part;
                $expressions[$k] = static fn($v) => $if($v) ? $then($v) : $v;
            }
        }
        return $expressions;
    }
}