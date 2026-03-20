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
namespace Symfony\Component\Expression_Language;

/**
 * Represents a function that can be used in an expression.
 *
 * A function is defined by two PHP callables. The callables are used
 * by the language to compile and/or evaluate the function.
 *
 * The "compiler" function is used at compilation time and must return a
 * PHP representation of the function call (it receives the function
 * arguments as arguments).
 *
 * The "evaluator" function is used for expression evaluation and must return
 * the value of the function call based on the values defined for the
 * expression (it receives the values as a first argument and the function
 * arguments as remaining arguments).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Expression_Function
{
    private readonly \Closure $compiler;
    private readonly \Closure $evaluator;
    /**
     * @param string   $name      The function name
     * @param callable $compiler  A callable able to compile the function
     * @param callable $evaluator A callable able to evaluate the function
     */
    public function __construct(private readonly string $name, callable $compiler, callable $evaluator)
    {
        $this->compiler = $compiler(...);
        $this->evaluator = $evaluator(...);
    }
    public function get_name(): string
    {
        return $this->name;
    }
    public function get_compiler(): \Closure
    {
        return $this->compiler;
    }
    public function get_evaluator(): \Closure
    {
        return $this->evaluator;
    }
    /**
     * Creates an ExpressionFunction from a PHP function name.
     *
     * @param string|null $expressionFunctionName The expression function name (default: same than the PHP function name)
     *
     * @throws \InvalidArgumentException if given PHP function name does not exist
     * @throws \InvalidArgumentException if given PHP function name is in namespace
     *                                   and expression function name is not defined
     */
    public static function from_php(string $php_function_name, ?string $expression_function_name = null): self
    {
        $php_function_name = ltrim($php_function_name, '\\');
        if (!\function_exists($php_function_name)) {
            throw new \InvalidArgumentException(\sprintf('PHP function "%s" does not exist.', $php_function_name));
        }
        $parts = explode('\\', $php_function_name);
        if (!$expression_function_name && \count($parts) > 1) {
            throw new \InvalidArgumentException(\sprintf('An expression function name must be defined when PHP function "%s" is namespaced.', $php_function_name));
        }
        $compiler = static fn(...$args): string => \sprintf('\%s(%s)', $php_function_name, implode(', ', $args));
        $evaluator = static fn($p, ...$args) => $php_function_name(...$args);
        return new self($expression_function_name ?: end($parts), $compiler, $evaluator);
    }
}