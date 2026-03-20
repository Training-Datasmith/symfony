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
namespace Symfony\Component\Css_Selector\Parser;

use Symfony\Component\Css_Selector\Exception\Internal_Error_Exception;
use Symfony\Component\Css_Selector\Exception\Syntax_Error_Exception;
use Symfony\Component\Css_Selector\Node;
use Symfony\Component\Css_Selector\Parser\Tokenizer\Tokenizer;
/**
 * CSS selector parser.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/scrapy/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Parser implements Parser_Interface
{
    public function __construct(private readonly ?Tokenizer $tokenizer = new Tokenizer())
    {
    }
    public function parse(string $source): array
    {
        $reader = new Reader($source);
        $stream = $this->tokenizer->tokenize($reader);
        return $this->parse_selector_list($stream);
    }
    /**
     * Parses the arguments for ":nth-child()" and friends.
     *
     * @param Token[] $tokens
     *
     * @throws SyntaxErrorException
     */
    public static function parse_series(array $tokens): array
    {
        foreach ($tokens as $token) {
            if ($token->is_string()) {
                throw Syntax_Error_Exception::string_as_function_argument();
            }
        }
        $joined = trim(implode('', array_map(static fn(Token $token): ?string => $token->get_value(), $tokens)));
        $int = static function ($string): int {
            if (!is_numeric($string)) {
                throw Syntax_Error_Exception::string_as_function_argument();
            }
            return (int) $string;
        };
        switch (true) {
            case 'odd' === $joined:
                return [2, 1];
            case 'even' === $joined:
                return [2, 0];
            case 'n' === $joined:
                return [1, 0];
            case !str_contains($joined, 'n'):
                return [0, $int($joined)];
        }
        $split = explode('n', $joined);
        $first = $split[0] ?? null;
        return [$first ? '-' === $first || '+' === $first ? $int($first . '1') : $int($first) : 1, isset($split[1]) && $split[1] ? $int($split[1]) : 0];
    }
    private function parse_selector_list(Token_Stream $stream, bool $is_argument = false): array
    {
        $stream->skip_whitespace();
        $selectors = [];
        while (true) {
            if ($is_argument && $stream->get_peek()->is_delimiter([')'])) {
                break;
            }
            $selectors[] = $this->parser_selector_node($stream, $is_argument);
            if ($stream->get_peek()->is_delimiter([','])) {
                $stream->get_next();
                $stream->skip_whitespace();
            } else {
                break;
            }
        }
        return $selectors;
    }
    private function parser_selector_node(Token_Stream $stream, bool $is_argument = false): Node\Selector_Node
    {
        [$result, $pseudo_element] = $this->parse_simple_selector($stream, false, $is_argument);
        while (true) {
            $stream->skip_whitespace();
            $peek = $stream->get_peek();
            if ($peek->is_file_end() || $peek->is_delimiter([',']) || $is_argument && $peek->is_delimiter([')'])) {
                break;
            }
            if (null !== $pseudo_element) {
                throw Syntax_Error_Exception::pseudo_element_found($pseudo_element, 'not at the end of a selector');
            }
            if ($peek->is_delimiter(['+', '>', '~'])) {
                $combinator = $stream->get_next()->get_value();
                $stream->skip_whitespace();
            } else {
                $combinator = ' ';
            }
            [$next_selector, $pseudo_element] = $this->parse_simple_selector($stream, false, $is_argument);
            $result = new Node\Combined_Selector_Node($result, $combinator, $next_selector);
        }
        return new Node\Selector_Node($result, $pseudo_element);
    }
    /**
     * @throws SyntaxErrorException
     * @throws InternalErrorException
     */
    private function parse_relative_selector(Token_Stream $stream): array
    {
        $stream->skip_whitespace();
        $sub_selector = '';
        $next = $stream->get_next();
        if ($next->is_delimiter(['+', '>', '~'])) {
            $combinator = $next->get_value();
            $stream->skip_whitespace();
            $next = $stream->get_next();
        } else {
            $combinator = ' ';
        }
        while (true) {
            if ($next->is_string() || $next->is_identifier() || $next->is_number() || $next->is_delimiter(['.', '*'])) {
                $sub_selector .= $next->get_value();
            } elseif ($next->is_hash()) {
                $sub_selector .= '#' . $next->get_value();
            } elseif ($next->is_delimiter([')'])) {
                $result = $this->parse($sub_selector);
                return [$combinator, $result[0]];
            } else {
                throw Syntax_Error_Exception::unexpected_token('an argument', $next);
            }
            $next = $stream->get_next();
        }
    }
    /**
     * Parses next simple node (hash, class, pseudo, negation).
     *
     * @throws SyntaxErrorException
     * @throws InternalErrorException
     */
    private function parse_simple_selector(Token_Stream $stream, bool $inside_negation = false, bool $is_argument = false): array
    {
        $stream->skip_whitespace();
        $selector_start = \count($stream->get_used());
        $result = $this->parse_element_node($stream);
        $pseudo_element = null;
        while (true) {
            $peek = $stream->get_peek();
            if ($peek->is_whitespace() || $peek->is_file_end() || $peek->is_delimiter([',', '+', '>', '~']) || $is_argument && $peek->is_delimiter([')'])) {
                break;
            }
            if (null !== $pseudo_element) {
                throw Syntax_Error_Exception::pseudo_element_found($pseudo_element, 'not at the end of a selector');
            }
            if ($peek->is_hash()) {
                $result = new Node\Hash_Node($result, $stream->get_next()->get_value());
            } elseif ($peek->is_delimiter(['.'])) {
                $stream->get_next();
                $result = new Node\Class_Node($result, $stream->get_next_identifier());
            } elseif ($peek->is_delimiter(['['])) {
                $stream->get_next();
                $result = $this->parse_attribute_node($result, $stream);
            } elseif ($peek->is_delimiter([':'])) {
                $stream->get_next();
                if ($stream->get_peek()->is_delimiter([':'])) {
                    $stream->get_next();
                    $pseudo_element = $stream->get_next_identifier();
                    continue;
                }
                $identifier = $stream->get_next_identifier();
                if (\in_array(strtolower($identifier), ['first-line', 'first-letter', 'before', 'after'], true)) {
                    // Special case: CSS 2.1 pseudo-elements can have a single ':'.
                    // Any new pseudo-element must have two.
                    $pseudo_element = $identifier;
                    continue;
                }
                if (!$stream->get_peek()->is_delimiter(['('])) {
                    $result = new Node\Pseudo_Node($result, $identifier);
                    if ('Pseudo[Element[*]:scope]' === $result->__toString()) {
                        $used = \count($stream->get_used());
                        if (!(2 === $used || 3 === $used && $stream->get_used()[0]->is_white_space() || $used >= 3 && $stream->get_used()[$used - 3]->is_delimiter([',']) || $used >= 4 && $stream->get_used()[$used - 3]->is_white_space() && $stream->get_used()[$used - 4]->is_delimiter([',']))) {
                            throw Syntax_Error_Exception::not_at_the_start_of_a_selector('scope');
                        }
                    }
                    continue;
                }
                $stream->get_next();
                $stream->skip_whitespace();
                if ('not' === strtolower($identifier)) {
                    if ($inside_negation) {
                        throw Syntax_Error_Exception::nested_not();
                    }
                    [$argument, $argument_pseudo_element] = $this->parse_simple_selector($stream, true, true);
                    $next = $stream->get_next();
                    if (null !== $argument_pseudo_element) {
                        throw Syntax_Error_Exception::pseudo_element_found($argument_pseudo_element, 'inside ::not()');
                    }
                    if (!$next->is_delimiter([')'])) {
                        throw Syntax_Error_Exception::unexpected_token('")"', $next);
                    }
                    $result = new Node\Negation_Node($result, $argument);
                } elseif ('is' === strtolower($identifier)) {
                    $selectors = $this->parse_selector_list($stream, true);
                    $next = $stream->get_next();
                    if (!$next->is_delimiter([')'])) {
                        throw Syntax_Error_Exception::unexpected_token('")"', $next);
                    }
                    $result = new Node\Matching_Node($result, $selectors);
                } elseif ('where' === strtolower($identifier)) {
                    $selectors = $this->parse_selector_list($stream, true);
                    $next = $stream->get_next();
                    if (!$next->is_delimiter([')'])) {
                        throw Syntax_Error_Exception::unexpected_token('")"', $next);
                    }
                    $result = new Node\Specificity_Adjustment_Node($result, $selectors);
                } elseif ('has' === strtolower($identifier)) {
                    [$combinator, $arguments] = $this->parse_relative_selector($stream);
                    $result = new Node\Relation_Node($result, $combinator, $arguments);
                } else {
                    $arguments = [];
                    $next = null;
                    while (true) {
                        $stream->skip_whitespace();
                        $next = $stream->get_next();
                        if ($next->is_identifier() || $next->is_string() || $next->is_number() || $next->is_delimiter(['+', '-'])) {
                            $arguments[] = $next;
                        } elseif ($next->is_delimiter([')'])) {
                            break;
                        } else {
                            throw Syntax_Error_Exception::unexpected_token('an argument', $next);
                        }
                    }
                    if (!$arguments) {
                        throw Syntax_Error_Exception::unexpected_token('at least one argument', $next);
                    }
                    $result = new Node\Function_Node($result, $identifier, $arguments);
                }
            } else {
                throw Syntax_Error_Exception::unexpected_token('selector', $peek);
            }
        }
        if (\count($stream->get_used()) === $selector_start) {
            throw Syntax_Error_Exception::unexpected_token('selector', $stream->get_peek());
        }
        return [$result, $pseudo_element];
    }
    private function parse_element_node(Token_Stream $stream): Node\Element_Node
    {
        $peek = $stream->get_peek();
        if ($peek->is_identifier() || $peek->is_delimiter(['*'])) {
            if ($peek->is_identifier()) {
                $namespace = $stream->get_next()->get_value();
            } else {
                $stream->get_next();
                $namespace = null;
            }
            if ($stream->get_peek()->is_delimiter(['|'])) {
                $stream->get_next();
                $element = $stream->get_next_identifier_or_star();
            } else {
                $element = $namespace;
                $namespace = null;
            }
        } else {
            $element = $namespace = null;
        }
        return new Node\Element_Node($namespace, $element);
    }
    private function parse_attribute_node(Node\Node_Interface $selector, Token_Stream $stream): Node\Attribute_Node
    {
        $stream->skip_whitespace();
        $attribute = $stream->get_next_identifier_or_star();
        if (null === $attribute && !$stream->get_peek()->is_delimiter(['|'])) {
            throw Syntax_Error_Exception::unexpected_token('"|"', $stream->get_peek());
        }
        if ($stream->get_peek()->is_delimiter(['|'])) {
            $stream->get_next();
            if ($stream->get_peek()->is_delimiter(['='])) {
                $namespace = null;
                $stream->get_next();
                $operator = '|=';
            } else {
                $namespace = $attribute;
                $attribute = $stream->get_next_identifier();
                $operator = null;
            }
        } else {
            $namespace = $operator = null;
        }
        if (null === $operator) {
            $stream->skip_whitespace();
            $next = $stream->get_next();
            if ($next->is_delimiter([']'])) {
                return new Node\Attribute_Node($selector, $namespace, $attribute, 'exists', null);
            }
            if ($next->is_delimiter(['='])) {
                $operator = '=';
            } elseif ($next->is_delimiter(['^', '$', '*', '~', '|', '!']) && $stream->get_peek()->is_delimiter(['='])) {
                $operator = $next->get_value() . '=';
                $stream->get_next();
            } else {
                throw Syntax_Error_Exception::unexpected_token('operator', $next);
            }
        }
        $stream->skip_whitespace();
        $value = $stream->get_next();
        if ($value->is_number()) {
            // if the value is a number, it's casted into a string
            $value = new Token(Token::TYPE_STRING, (string) $value->get_value(), $value->get_position());
        }
        if (!($value->is_identifier() || $value->is_string())) {
            throw Syntax_Error_Exception::unexpected_token('string or identifier', $value);
        }
        $stream->skip_whitespace();
        $next = $stream->get_next();
        if (!$next->is_delimiter([']'])) {
            throw Syntax_Error_Exception::unexpected_token('"]"', $next);
        }
        return new Node\Attribute_Node($selector, $namespace, $attribute, $operator, $value->get_value());
    }
}