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
namespace Symfony\Component\Css_Selector\Parser\Tokenizer;

use Symfony\Component\Css_Selector\Parser\Handler;
use Symfony\Component\Css_Selector\Parser\Reader;
use Symfony\Component\Css_Selector\Parser\Token;
use Symfony\Component\Css_Selector\Parser\Token_Stream;
/**
 * CSS selector tokenizer.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Tokenizer
{
    /**
     * @var Handler\HandlerInterface[]
     */
    private readonly array $handlers;
    public function __construct()
    {
        $patterns = new Tokenizer_Patterns();
        $escaping = new Tokenizer_Escaping($patterns);
        $this->handlers = [new Handler\Whitespace_Handler(), new Handler\Identifier_Handler($patterns, $escaping), new Handler\Hash_Handler($patterns, $escaping), new Handler\String_Handler($patterns, $escaping), new Handler\Number_Handler($patterns), new Handler\Comment_Handler()];
    }
    /**
     * Tokenize selector source code.
     */
    public function tokenize(Reader $reader): Token_Stream
    {
        $stream = new Token_Stream();
        while (!$reader->is_eof()) {
            foreach ($this->handlers as $handler) {
                if ($handler->handle($reader, $stream)) {
                    continue 2;
                }
            }
            $stream->push(new Token(Token::TYPE_DELIMITER, $reader->get_substring(1), $reader->get_position()));
            $reader->move_forward(1);
        }
        return $stream->push(new Token(Token::TYPE_FILE_END, null, $reader->get_position()))->freeze();
    }
}