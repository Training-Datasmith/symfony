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
namespace Symfony\Component\Http_Client\Response;

use Symfony\Contracts\Http_Client\Chunk_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Http_Client\Response_Stream_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final readonly class Response_Stream implements Response_Stream_Interface
{
    public function __construct(private \Generator $generator)
    {
    }
    public function key(): Response_Interface
    {
        return $this->generator->key();
    }
    public function current(): Chunk_Interface
    {
        return $this->generator->current();
    }
    public function next(): void
    {
        $this->generator->next();
    }
    public function rewind(): void
    {
        $this->generator->rewind();
    }
    public function valid(): bool
    {
        return $this->generator->valid();
    }
}