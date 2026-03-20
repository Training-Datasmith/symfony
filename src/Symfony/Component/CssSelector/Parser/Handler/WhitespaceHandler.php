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
namespace Symfony\Component\Css_Selector\Parser\Handler;

use Symfony\Component\Css_Selector\Parser\Reader;
use Symfony\Component\Css_Selector\Parser\Token;
use Symfony\Component\Css_Selector\Parser\Token_Stream;
/**
 * CSS selector whitespace handler.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Whitespace_Handler implements Handler_Interface
{
    public function handle(Reader $reader, Token_Stream $stream): bool
    {
        $match = $reader->find_pattern('~^[ \t\r\n\f]+~');
        if (false === $match) {
            return false;
        }
        $stream->push(new Token(Token::TYPE_WHITESPACE, $match[0], $reader->get_position()));
        $reader->move_forward(\strlen((string) $match[0]));
        return true;
    }
}