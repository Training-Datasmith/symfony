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

use Symfony\Component\Css_Selector\Exception\Internal_Error_Exception;
use Symfony\Component\Css_Selector\Exception\Syntax_Error_Exception;
use Symfony\Component\Css_Selector\Parser\Reader;
use Symfony\Component\Css_Selector\Parser\Token;
use Symfony\Component\Css_Selector\Parser\Tokenizer\Tokenizer_Escaping;
use Symfony\Component\Css_Selector\Parser\Tokenizer\Tokenizer_Patterns;
use Symfony\Component\Css_Selector\Parser\Token_Stream;
/**
 * CSS selector comment handler.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class String_Handler implements Handler_Interface
{
    public function __construct(private readonly Tokenizer_Patterns $patterns, private readonly Tokenizer_Escaping $escaping)
    {
    }
    public function handle(Reader $reader, Token_Stream $stream): bool
    {
        $quote = $reader->get_substring(1);
        if (!\in_array($quote, ["'", '"'], true)) {
            return false;
        }
        $reader->move_forward(1);
        $match = $reader->find_pattern($this->patterns->get_quoted_string_pattern($quote));
        if (!$match) {
            throw new Internal_Error_Exception(\sprintf('Should have found at least an empty match at %d.', $reader->get_position()));
        }
        // check unclosed strings
        if (\strlen((string) $match[0]) === $reader->get_remaining_length()) {
            throw Syntax_Error_Exception::unclosed_string($reader->get_position() - 1);
        }
        // check quotes pairs validity
        if ($quote !== $reader->get_substring(1, \strlen((string) $match[0]))) {
            throw Syntax_Error_Exception::unclosed_string($reader->get_position() - 1);
        }
        $string = $this->escaping->escape_unicode_and_new_line($match[0]);
        $stream->push(new Token(Token::TYPE_STRING, $string, $reader->get_position()));
        $reader->move_forward(\strlen((string) $match[0]) + 1);
        return true;
    }
}