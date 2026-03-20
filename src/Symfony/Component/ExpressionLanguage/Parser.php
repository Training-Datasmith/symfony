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
 * Parses a token stream.
 *
 * This parser implements a "Precedence climbing" algorithm.
 *
 * @see http://www.engr.mun.ca/~theo/Misc/exp_parsing.htm
 * @see http://en.wikipedia.org/wiki/Operator-precedence_parser
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Parser
{
    public const OPERATOR_LEFT = 1;
    public const OPERATOR_RIGHT = 2;
    public const IGNORE_UNKNOWN_VARIABLES = 1;
    public const IGNORE_UNKNOWN_FUNCTIONS = 2;
    private Token_Stream $stream;
    private array $unary_operators;
    private array $binary_operators;
    private array $names;
    private int $flags = 0;
    public function __construct(private array $functions)
    {
        $this->unary_operators = ['not' => ['precedence' => 50], '!' => ['precedence' => 50], '-' => ['precedence' => 500], '+' => ['precedence' => 500], '~' => ['precedence' => 500]];
        $this->binary_operators = ['or' => ['precedence' => 10, 'associativity' => self::OPERATOR_LEFT], '||' => ['precedence' => 10, 'associativity' => self::OPERATOR_LEFT], 'xor' => ['precedence' => 12, 'associativity' => self::OPERATOR_LEFT], 'and' => ['precedence' => 15, 'associativity' => self::OPERATOR_LEFT], '&&' => ['precedence' => 15, 'associativity' => self::OPERATOR_LEFT], '|' => ['precedence' => 16, 'associativity' => self::OPERATOR_LEFT], '^' => ['precedence' => 17, 'associativity' => self::OPERATOR_LEFT], '&' => ['precedence' => 18, 'associativity' => self::OPERATOR_LEFT], '==' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], '===' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], '!=' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], '!==' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], '<' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], '>' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], '>=' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], '<=' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], 'not in' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], 'in' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], 'contains' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], 'starts with' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], 'ends with' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], 'matches' => ['precedence' => 20, 'associativity' => self::OPERATOR_LEFT], '..' => ['precedence' => 25, 'associativity' => self::OPERATOR_LEFT], '<<' => ['precedence' => 25, 'associativity' => self::OPERATOR_LEFT], '>>' => ['precedence' => 25, 'associativity' => self::OPERATOR_LEFT], '+' => ['precedence' => 30, 'associativity' => self::OPERATOR_LEFT], '-' => ['precedence' => 30, 'associativity' => self::OPERATOR_LEFT], '~' => ['precedence' => 40, 'associativity' => self::OPERATOR_LEFT], '*' => ['precedence' => 60, 'associativity' => self::OPERATOR_LEFT], '/' => ['precedence' => 60, 'associativity' => self::OPERATOR_LEFT], '%' => ['precedence' => 60, 'associativity' => self::OPERATOR_LEFT], '**' => ['precedence' => 200, 'associativity' => self::OPERATOR_RIGHT]];
    }
    /**
     * Converts a token stream to a node tree.
     *
     * The valid names is an array where the values
     * are the names that the user can use in an expression.
     *
     * If the variable name in the compiled PHP code must be
     * different, define it as the key.
     *
     * For instance, ['this' => 'container'] means that the
     * variable 'container' can be used in the expression
     * but the compiled code will use 'this'.
     *
     * @param int-mask-of<Parser::IGNORE_*> $flags
     *
     * @throws SyntaxError
     */
    public function parse(Token_Stream $stream, array $names = [], int $flags = 0): Node\Node
    {
        return $this->do_parse($stream, $names, $flags);
    }
    /**
     * Validates the syntax of an expression.
     *
     * The syntax of the passed expression will be checked, but not parsed.
     * If you want to skip checking dynamic variable names, pass `Parser::IGNORE_UNKNOWN_VARIABLES` instead of the array.
     *
     * @param int-mask-of<Parser::IGNORE_*> $flags
     *
     * @throws SyntaxError When the passed expression is invalid
     */
    public function lint(Token_Stream $stream, array $names = [], int $flags = 0): void
    {
        $this->do_parse($stream, $names, $flags);
    }
    /**
     * @param int-mask-of<Parser::IGNORE_*> $flags
     *
     * @throws SyntaxError
     */
    private function do_parse(Token_Stream $stream, array $names, int $flags): Node\Node
    {
        $this->flags = $flags;
        $this->stream = $stream;
        $this->names = $names;
        $node = $this->parse_expression();
        if (!$stream->is_eof()) {
            throw new Syntax_Error(\sprintf('Unexpected token "%s" of value "%s".', $stream->current->type, $stream->current->value), $stream->current->cursor, $stream->get_expression());
        }
        unset($this->stream, $this->names);
        return $node;
    }
    public function parse_expression(int $precedence = 0): Node\Node
    {
        $expr = $this->get_primary();
        $token = $this->stream->current;
        while ($token->test(Token::OPERATOR_TYPE) && isset($this->binary_operators[$token->value]) && $this->binary_operators[$token->value]['precedence'] >= $precedence) {
            $op = $this->binary_operators[$token->value];
            $this->stream->next();
            $expr1 = $this->parse_expression(self::OPERATOR_LEFT === $op['associativity'] ? $op['precedence'] + 1 : $op['precedence']);
            $expr = new Node\Binary_Node($token->value, $expr, $expr1);
            $token = $this->stream->current;
        }
        if (0 === $precedence) {
            return $this->parse_conditional_expression($expr);
        }
        return $expr;
    }
    protected function get_primary(): Node\Node
    {
        $token = $this->stream->current;
        if ($token->test(Token::OPERATOR_TYPE) && isset($this->unary_operators[$token->value])) {
            $operator = $this->unary_operators[$token->value];
            $this->stream->next();
            $expr = $this->parse_expression($operator['precedence']);
            return $this->parse_postfix_expression(new Node\Unary_Node($token->value, $expr));
        }
        if ($token->test(Token::PUNCTUATION_TYPE, '(')) {
            $this->stream->next();
            $expr = $this->parse_expression();
            $this->stream->expect(Token::PUNCTUATION_TYPE, ')', 'An opened parenthesis is not properly closed');
            return $this->parse_postfix_expression($expr);
        }
        return $this->parse_primary_expression();
    }
    protected function parse_conditional_expression(Node\Node $expr): Node\Node
    {
        while ($this->stream->current->test(Token::PUNCTUATION_TYPE, '??')) {
            $this->stream->next();
            $expr2 = $this->parse_expression();
            $expr = new Node\Null_Coalesce_Node($expr, $expr2);
        }
        while ($this->stream->current->test(Token::PUNCTUATION_TYPE, '?')) {
            $this->stream->next();
            if (!$this->stream->current->test(Token::PUNCTUATION_TYPE, ':')) {
                $expr2 = $this->parse_expression();
                if ($this->stream->current->test(Token::PUNCTUATION_TYPE, ':')) {
                    $this->stream->next();
                    $expr3 = $this->parse_expression();
                } else {
                    $expr3 = new Node\Constant_Node(null);
                }
            } else {
                $this->stream->next();
                $expr2 = $expr;
                $expr3 = $this->parse_expression();
            }
            $expr = new Node\Conditional_Node($expr, $expr2, $expr3);
        }
        return $expr;
    }
    public function parse_primary_expression(): Node\Node
    {
        $token = $this->stream->current;
        switch ($token->type) {
            case Token::NAME_TYPE:
                $this->stream->next();
                switch ($token->value) {
                    case 'true':
                    case 'TRUE':
                        return new Node\Constant_Node(true);
                    case 'false':
                    case 'FALSE':
                        return new Node\Constant_Node(false);
                    case 'null':
                    case 'NULL':
                        return new Node\Constant_Node(null);
                    default:
                        if ('(' === $this->stream->current->value) {
                            if (!($this->flags & self::IGNORE_UNKNOWN_FUNCTIONS) && false === isset($this->functions[$token->value])) {
                                throw new Syntax_Error(\sprintf('The function "%s" does not exist.', $token->value), $token->cursor, $this->stream->get_expression(), $token->value, array_keys($this->functions));
                            }
                            $node = new Node\Function_Node($token->value, $this->parse_arguments());
                        } else {
                            if (!($this->flags & self::IGNORE_UNKNOWN_VARIABLES)) {
                                if (!\in_array($token->value, $this->names, true)) {
                                    if ($this->stream->current->test(Token::PUNCTUATION_TYPE, '??')) {
                                        return new Node\Null_Coalesced_Name_Node($token->value);
                                    }
                                    throw new Syntax_Error(\sprintf('Variable "%s" is not valid.', $token->value), $token->cursor, $this->stream->get_expression(), $token->value, $this->names);
                                }
                                // is the name used in the compiled code different
                                // from the name used in the expression?
                                if (\is_int($name = array_search($token->value, $this->names))) {
                                    $name = $token->value;
                                }
                            } else {
                                $name = $token->value;
                            }
                            $node = new Node\Name_Node($name);
                        }
                }
                break;
            case Token::NUMBER_TYPE:
            case Token::STRING_TYPE:
                $this->stream->next();
                return new Node\Constant_Node($token->value);
            default:
                if ($token->test(Token::PUNCTUATION_TYPE, '[')) {
                    $node = $this->parse_array_expression();
                } elseif ($token->test(Token::PUNCTUATION_TYPE, '{')) {
                    $node = $this->parse_hash_expression();
                } else {
                    throw new Syntax_Error(\sprintf('Unexpected token "%s" of value "%s".', $token->type, $token->value), $token->cursor, $this->stream->get_expression());
                }
        }
        return $this->parse_postfix_expression($node);
    }
    public function parse_array_expression(): Node\Array_Node
    {
        $this->stream->expect(Token::PUNCTUATION_TYPE, '[', 'An array element was expected');
        $node = new Node\Array_Node();
        $first = true;
        while (!$this->stream->current->test(Token::PUNCTUATION_TYPE, ']')) {
            if (!$first) {
                $this->stream->expect(Token::PUNCTUATION_TYPE, ',', 'An array element must be followed by a comma');
                // trailing ,?
                if ($this->stream->current->test(Token::PUNCTUATION_TYPE, ']')) {
                    break;
                }
            }
            $first = false;
            $node->add_element($this->parse_expression());
        }
        $this->stream->expect(Token::PUNCTUATION_TYPE, ']', 'An opened array is not properly closed');
        return $node;
    }
    public function parse_hash_expression(): Node\Array_Node
    {
        $this->stream->expect(Token::PUNCTUATION_TYPE, '{', 'A hash element was expected');
        $node = new Node\Array_Node();
        $first = true;
        while (!$this->stream->current->test(Token::PUNCTUATION_TYPE, '}')) {
            if (!$first) {
                $this->stream->expect(Token::PUNCTUATION_TYPE, ',', 'A hash value must be followed by a comma');
                // trailing ,?
                if ($this->stream->current->test(Token::PUNCTUATION_TYPE, '}')) {
                    break;
                }
            }
            $first = false;
            // a hash key can be:
            //
            //  * a number -- 12
            //  * a string -- 'a'
            //  * a name, which is equivalent to a string -- a
            //  * an expression, which must be enclosed in parentheses -- (1 + 2)
            if ($this->stream->current->test(Token::STRING_TYPE) || $this->stream->current->test(Token::NAME_TYPE) || $this->stream->current->test(Token::NUMBER_TYPE)) {
                $key = new Node\Constant_Node($this->stream->current->value);
                $this->stream->next();
            } elseif ($this->stream->current->test(Token::PUNCTUATION_TYPE, '(')) {
                $key = $this->parse_expression();
            } else {
                $current = $this->stream->current;
                throw new Syntax_Error(\sprintf('A hash key must be a quoted string, a number, a name, or an expression enclosed in parentheses (unexpected token "%s" of value "%s".', $current->type, $current->value), $current->cursor, $this->stream->get_expression());
            }
            $this->stream->expect(Token::PUNCTUATION_TYPE, ':', 'A hash key must be followed by a colon (:)');
            $value = $this->parse_expression();
            $node->add_element($value, $key);
        }
        $this->stream->expect(Token::PUNCTUATION_TYPE, '}', 'An opened hash is not properly closed');
        return $node;
    }
    public function parse_postfix_expression(Node\Node $node): Node\Get_Attr_Node|Node\Node
    {
        $token = $this->stream->current;
        while (Token::PUNCTUATION_TYPE == $token->type) {
            if ('.' === $token->value || '?.' === $token->value) {
                $is_null_safe = '?.' === $token->value;
                $this->stream->next();
                $token = $this->stream->current;
                if ($token->test(Token::PUNCTUATION_TYPE, '[')) {
                    if (!$is_null_safe) {
                        throw new Syntax_Error('Expected name.', $token->cursor, $this->stream->get_expression());
                    }
                    $this->stream->next();
                    $arg = $this->parse_expression();
                    $this->stream->expect(Token::PUNCTUATION_TYPE, ']');
                    $node = new Node\Get_Attr_Node($node, $arg, new Node\Arguments_Node(), Node\Get_Attr_Node::ARRAY_CALL, true);
                } else {
                    $this->stream->next();
                    if (Token::NAME_TYPE !== $token->type && (Token::OPERATOR_TYPE !== $token->type || !preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/A', $token->value))) {
                        throw new Syntax_Error('Expected name.', $token->cursor, $this->stream->get_expression());
                    }
                    $arg = new Node\Constant_Node($token->value, true, $is_null_safe);
                    $arguments = new Node\Arguments_Node();
                    if ($this->stream->current->test(Token::PUNCTUATION_TYPE, '(')) {
                        $type = Node\Get_Attr_Node::METHOD_CALL;
                        foreach ($this->parse_arguments()->nodes as $n) {
                            $arguments->add_element($n);
                        }
                    } else {
                        $type = Node\Get_Attr_Node::PROPERTY_CALL;
                    }
                    $node = new Node\Get_Attr_Node($node, $arg, $arguments, $type, $is_null_safe);
                }
            } elseif ('[' === $token->value) {
                $this->stream->next();
                $arg = $this->parse_expression();
                $this->stream->expect(Token::PUNCTUATION_TYPE, ']');
                $node = new Node\Get_Attr_Node($node, $arg, new Node\Arguments_Node(), Node\Get_Attr_Node::ARRAY_CALL);
            } else {
                break;
            }
            $token = $this->stream->current;
        }
        return $node;
    }
    /**
     * Parses arguments.
     */
    public function parse_arguments(): Node\Node
    {
        $args = [];
        $this->stream->expect(Token::PUNCTUATION_TYPE, '(', 'A list of arguments must begin with an opening parenthesis');
        while (!$this->stream->current->test(Token::PUNCTUATION_TYPE, ')')) {
            if ($args) {
                $this->stream->expect(Token::PUNCTUATION_TYPE, ',', 'Arguments must be separated by a comma');
            }
            $args[] = $this->parse_expression();
        }
        $this->stream->expect(Token::PUNCTUATION_TYPE, ')', 'A list of arguments must be closed by a parenthesis');
        return new Node\Node($args);
    }
}