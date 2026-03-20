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
/**
 * CSS selector token stream.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Token_Stream
{
    /**
     * @var Token[]
     */
    private array $tokens = [];
    /**
     * @var Token[]
     */
    private array $used = [];
    private int $cursor = 0;
    private ?Token $peeked = null;
    private bool $peeking = false;
    /**
     * Pushes a token.
     *
     * @return $this
     */
    public function push(Token $token): static
    {
        $this->tokens[] = $token;
        return $this;
    }
    /**
     * Freezes stream.
     *
     * @return $this
     */
    public function freeze(): static
    {
        return $this;
    }
    /**
     * Returns next token.
     *
     * @throws InternalErrorException If there is no more token
     */
    public function get_next(): Token
    {
        if ($this->peeking) {
            $this->peeking = false;
            $this->used[] = $this->peeked;
            return $this->peeked;
        }
        if (!isset($this->tokens[$this->cursor])) {
            throw new Internal_Error_Exception('Unexpected token stream end.');
        }
        return $this->tokens[$this->cursor++];
    }
    /**
     * Returns peeked token.
     */
    public function get_peek(): Token
    {
        if (!$this->peeking) {
            $this->peeked = $this->get_next();
            $this->peeking = true;
        }
        return $this->peeked;
    }
    /**
     * Returns used tokens.
     *
     * @return Token[]
     */
    public function get_used(): array
    {
        return $this->used;
    }
    /**
     * Returns next identifier token.
     *
     * @throws SyntaxErrorException If next token is not an identifier
     */
    public function get_next_identifier(): string
    {
        $next = $this->get_next();
        if (!$next->is_identifier()) {
            throw Syntax_Error_Exception::unexpected_token('identifier', $next);
        }
        return $next->get_value();
    }
    /**
     * Returns next identifier or null if star delimiter token is found.
     *
     * @throws SyntaxErrorException If next token is not an identifier or a star delimiter
     */
    public function get_next_identifier_or_star(): ?string
    {
        $next = $this->get_next();
        if ($next->is_identifier()) {
            return $next->get_value();
        }
        if ($next->is_delimiter(['*'])) {
            return null;
        }
        throw Syntax_Error_Exception::unexpected_token('identifier or "*"', $next);
    }
    /**
     * Skips next whitespace if any.
     */
    public function skip_whitespace(): void
    {
        $peek = $this->get_peek();
        if ($peek->is_whitespace()) {
            $this->get_next();
        }
    }
}