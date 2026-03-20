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
namespace Symfony\Component\Http_Client\Chunk;

use Symfony\Contracts\Http_Client\Chunk_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Data_Chunk implements Chunk_Interface
{
    public function __construct(private readonly int $offset = 0, private readonly string $content = '')
    {
    }
    public function is_timeout(): bool
    {
        return false;
    }
    public function is_first(): bool
    {
        return false;
    }
    public function is_last(): bool
    {
        return false;
    }
    public function get_informational_status(): ?array
    {
        return null;
    }
    public function get_content(): string
    {
        return $this->content;
    }
    public function get_offset(): int
    {
        return $this->offset;
    }
    public function get_error(): ?string
    {
        return null;
    }
}