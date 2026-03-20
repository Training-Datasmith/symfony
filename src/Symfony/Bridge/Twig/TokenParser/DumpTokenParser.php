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

use Symfony\Bridge\Twig\Node\Dump_Node;
use Twig\Node\Expression\Variable\Local_Variable;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Token;
use Twig\Token_Parser\Abstract_Token_Parser;
/**
 * Token Parser for the 'dump' tag.
 *
 * Dump variables with:
 *
 *     {% dump %}
 *     {% dump foo %}
 *     {% dump foo, bar %}
 *
 * @author Julien Galenski <julien.galenski@gmail.com>
 */
final class Dump_Token_Parser extends Abstract_Token_Parser
{
    public function parse(Token $token): Node
    {
        $values = null;
        if (!$this->parser->get_stream()->test(Token::BLOCK_END_TYPE)) {
            $values = $this->parse_multitarget_expression();
        }
        $this->parser->get_stream()->expect(Token::BLOCK_END_TYPE);
        return new Dump_Node(new Local_Variable(null, $token->get_line()), $values, $token->get_line());
    }
    private function parse_multitarget_expression(): Node
    {
        $targets = [];
        while (true) {
            $targets[] = $this->parser->parse_expression();
            if (!$this->parser->get_stream()->next_if(Token::PUNCTUATION_TYPE, ',')) {
                break;
            }
        }
        return new Nodes($targets);
    }
    public function get_tag(): string
    {
        return 'dump';
    }
}