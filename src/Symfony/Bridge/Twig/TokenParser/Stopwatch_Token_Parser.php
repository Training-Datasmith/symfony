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
namespace Symfony\Bridge\Twig\Token_Parser;

use Symfony\Bridge\Twig\Node\Stopwatch_Node;
use Twig\Node\Expression\Variable\Local_Variable;
use Twig\Node\Node;
use Twig\Token;
use Twig\Token_Parser\Abstract_Token_Parser;
/**
 * Token Parser for the stopwatch tag.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
final class Stopwatch_Token_Parser extends Abstract_Token_Parser
{
    public function __construct(private readonly bool $stopwatch_is_available)
    {
    }
    public function parse(Token $token): Node
    {
        $lineno = $token->get_line();
        $stream = $this->parser->get_stream();
        // {% stopwatch 'bar' %}
        $name = $this->parser->parse_expression();
        $stream->expect(Token::BLOCK_END_TYPE);
        // {% endstopwatch %}
        $body = $this->parser->subparse($this->decide_stopwatch_end(...), true);
        $stream->expect(Token::BLOCK_END_TYPE);
        if ($this->stopwatch_is_available) {
            return new Stopwatch_Node($name, $body, new Local_Variable(null, $token->get_line()), $lineno);
        }
        return $body;
    }
    public function decide_stopwatch_end(Token $token): bool
    {
        return $token->test('endstopwatch');
    }
    public function get_tag(): string
    {
        return 'stopwatch';
    }
}