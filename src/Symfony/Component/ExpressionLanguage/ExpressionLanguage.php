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

use Psr\Cache\Cache_Item_Pool_Interface;
use Symfony\Component\Cache\Adapter\Array_Adapter;
// Help opcache.preload discover always-needed symbols
class_exists(Parsed_Expression::class);
/**
 * Allows to compile and evaluate expressions written in your own DSL.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Expression_Language
{
    private Lexer $lexer;
    private Parser $parser;
    private Compiler $compiler;
    protected array $functions = [];
    /**
     * @param iterable<ExpressionFunctionProviderInterface> $providers
     */
    public function __construct(private readonly ?Cache_Item_Pool_Interface $cache = new Array_Adapter(), iterable $providers = [])
    {
        $this->register_functions();
        foreach ($providers as $provider) {
            $this->register_provider($provider);
        }
    }
    /**
     * Compiles an expression source code.
     */
    public function compile(Expression|string $expression, array $names = []): string
    {
        return $this->get_compiler()->compile($this->parse($expression, $names)->get_nodes())->get_source();
    }
    /**
     * Evaluate an expression.
     */
    public function evaluate(Expression|string $expression, array $values = []): mixed
    {
        return $this->parse($expression, array_keys($values))->get_nodes()->evaluate($this->functions, $values);
    }
    /**
     * Parses an expression.
     *
     * @param int-mask-of<Parser::IGNORE_*> $flags
     */
    public function parse(Expression|string $expression, array $names, int $flags = 0): Parsed_Expression
    {
        if ($expression instanceof Parsed_Expression) {
            return $expression;
        }
        asort($names);
        $cache_key_items = [];
        foreach ($names as $name_key => $name) {
            $cache_key_items[] = \is_int($name_key) ? $name : $name_key . ':' . $name;
        }
        $cache_item = $this->cache->get_item(rawurlencode($expression . '//' . implode('|', $cache_key_items)));
        if (null === $parsed_expression = $cache_item->get()) {
            $nodes = $this->get_parser()->parse($this->get_lexer()->tokenize((string) $expression), $names, $flags);
            $parsed_expression = new Parsed_Expression((string) $expression, $nodes);
            $cache_item->set($parsed_expression);
            $this->cache->save($cache_item);
        }
        return $parsed_expression;
    }
    /**
     * Validates the syntax of an expression.
     *
     * @param array                         $names The list of acceptable variable names in the expression
     * @param int-mask-of<Parser::IGNORE_*> $flags
     *
     * @throws SyntaxError When the passed expression is invalid
     */
    public function lint(Expression|string $expression, array $names, int $flags = 0): void
    {
        if ($expression instanceof Parsed_Expression) {
            return;
        }
        $this->get_parser()->lint($this->get_lexer()->tokenize((string) $expression), $names, $flags);
    }
    /**
     * Registers a function.
     *
     * @param callable $compiler  A callable able to compile the function
     * @param callable $evaluator A callable able to evaluate the function
     *
     * @throws \LogicException when registering a function after calling evaluate(), compile() or parse()
     *
     * @see ExpressionFunction
     */
    public function register(string $name, callable $compiler, callable $evaluator): void
    {
        if (isset($this->parser)) {
            throw new \LogicException('Registering functions after calling evaluate(), compile() or parse() is not supported.');
        }
        $this->functions[$name] = ['compiler' => $compiler, 'evaluator' => $evaluator];
    }
    public function add_function(Expression_Function $function): void
    {
        $this->register($function->get_name(), $function->get_compiler(), $function->get_evaluator());
    }
    public function register_provider(Expression_Function_Provider_Interface $provider): void
    {
        foreach ($provider->get_functions() as $function) {
            $this->add_function($function);
        }
    }
    protected function register_functions(): void
    {
        $basic_php_functions = ['constant', 'min', 'max'];
        foreach ($basic_php_functions as $function) {
            $this->add_function(Expression_Function::from_php($function));
        }
        $this->add_function(new Expression_Function('enum', static fn(string $str): string => \sprintf("(\\constant(\$v = (%s))) instanceof \\UnitEnum ? \\constant(\$v) : throw new \\TypeError(\\sprintf('The string \"%%s\" is not the name of a valid enum case.', \$v))", $str), static function ($arguments, string $str): \Unit_Enum {
            $value = \constant($str);
            if (!$value instanceof \Unit_Enum) {
                throw new \TypeError(\sprintf('The string "%s" is not the name of a valid enum case.', $str));
            }
            return $value;
        }));
    }
    private function get_lexer(): Lexer
    {
        return $this->lexer ??= new Lexer();
    }
    private function get_parser(): Parser
    {
        return $this->parser ??= new Parser($this->functions);
    }
    private function get_compiler(): Compiler
    {
        $this->compiler ??= new Compiler($this->functions);
        return $this->compiler->reset();
    }
}