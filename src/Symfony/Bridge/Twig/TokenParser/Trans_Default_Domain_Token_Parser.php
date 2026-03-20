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

use Symfony\Bridge\Twig\Node\Trans_Default_Domain_Node;
use Twig\Node\Node;
use Twig\Token;
use Twig\Token_Parser\Abstract_Token_Parser;
/**
 * Token Parser for the 'trans_default_domain' tag.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Trans_Default_Domain_Token_Parser extends Abstract_Token_Parser
{
    public function parse(Token $token): Node
    {
        $expr = $this->parser->parse_expression();
        $this->parser->get_stream()->expect(Token::BLOCK_END_TYPE);
        return new Trans_Default_Domain_Node($expr, $token->get_line());
    }
    public function get_tag(): string
    {
        return 'trans_default_domain';
    }
}