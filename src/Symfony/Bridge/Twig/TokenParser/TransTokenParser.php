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

use Symfony\Bridge\Twig\Node\Trans_Node;
use Twig\Error\Syntax_Error;
use Twig\Node\Expression\Abstract_Expression;
use Twig\Node\Expression\Array_Expression;
use Twig\Node\Node;
use Twig\Node\Text_Node;
use Twig\Token;
use Twig\Token_Parser\Abstract_Token_Parser;
/**
 * Token Parser for the 'trans' tag.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Trans_Token_Parser extends Abstract_Token_Parser
{
    public function parse(Token $token): Node
    {
        $lineno = $token->get_line();
        $stream = $this->parser->get_stream();
        $count = null;
        $vars = new Array_Expression([], $lineno);
        $domain = null;
        $locale = null;
        if (!$stream->test(Token::BLOCK_END_TYPE)) {
            if ($stream->test('count')) {
                // {% trans count 5 %}
                $stream->next();
                $count = $this->parser->parse_expression();
            }
            if ($stream->test('with')) {
                // {% trans with vars %}
                $stream->next();
                $vars = $this->parser->parse_expression();
            }
            if ($stream->test('from')) {
                // {% trans from "messages" %}
                $stream->next();
                $domain = $this->parser->parse_expression();
            }
            if ($stream->test('into')) {
                // {% trans into "fr" %}
                $stream->next();
                $locale = $this->parser->parse_expression();
            } elseif (!$stream->test(Token::BLOCK_END_TYPE)) {
                throw new Syntax_Error('Unexpected token. Twig was looking for the "with", "from", or "into" keyword.', $stream->get_current()->get_line(), $stream->get_source_context());
            }
        }
        // {% trans %}message{% endtrans %}
        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse($this->decide_trans_fork(...), true);
        if (!$body instanceof Text_Node && !$body instanceof Abstract_Expression) {
            throw new Syntax_Error('A message inside a trans tag must be a simple text.', $body->get_template_line(), $stream->get_source_context());
        }
        $stream->expect(Token::BLOCK_END_TYPE);
        return new Trans_Node($body, $domain, $count, $vars, $locale, $lineno);
    }
    public function decide_trans_fork(Token $token): bool
    {
        return $token->test(['endtrans']);
    }
    public function get_tag(): string
    {
        return 'trans';
    }
}