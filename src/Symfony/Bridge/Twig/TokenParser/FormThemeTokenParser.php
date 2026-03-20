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

use Symfony\Bridge\Twig\Node\Form_Theme_Node;
use Twig\Node\Expression\Array_Expression;
use Twig\Node\Node;
use Twig\Token;
use Twig\Token_Parser\Abstract_Token_Parser;
/**
 * Token Parser for the 'form_theme' tag.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Form_Theme_Token_Parser extends Abstract_Token_Parser
{
    public function parse(Token $token): Node
    {
        $lineno = $token->get_line();
        $stream = $this->parser->get_stream();
        $form = $this->parser->parse_expression();
        $only = false;
        if ($this->parser->get_stream()->test(Token::NAME_TYPE, 'with')) {
            $this->parser->get_stream()->next();
            $resources = $this->parser->parse_expression();
            if ($this->parser->get_stream()->next_if(Token::NAME_TYPE, 'only')) {
                $only = true;
            }
        } else {
            $resources = new Array_Expression([], $stream->get_current()->get_line());
            do {
                $resources->add_element($this->parser->parse_expression());
            } while (!$stream->test(Token::BLOCK_END_TYPE));
        }
        $stream->expect(Token::BLOCK_END_TYPE);
        return new Form_Theme_Node($form, $resources, $lineno, $only);
    }
    public function get_tag(): string
    {
        return 'form_theme';
    }
}