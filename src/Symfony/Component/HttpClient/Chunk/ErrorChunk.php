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

use Symfony\Component\Http_Client\Exception\Timeout_Exception;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Contracts\Http_Client\Chunk_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Error_Chunk implements Chunk_Interface
{
    private bool $did_throw = false;
    private string $error_message;
    private ?\Throwable $error = null;
    public function __construct(private readonly int $offset, \Throwable|string $error)
    {
        if (\is_string($error)) {
            $this->error_message = $error;
        } else {
            $this->error = $error;
            $this->error_message = $error->get_message();
        }
    }
    public function is_timeout(): bool
    {
        $this->did_throw = true;
        if (null !== $this->error) {
            throw new Transport_Exception($this->error_message, 0, $this->error);
        }
        return true;
    }
    public function is_first(): bool
    {
        $this->did_throw = true;
        throw null !== $this->error ? new Transport_Exception($this->error_message, 0, $this->error) : new Timeout_Exception($this->error_message);
    }
    public function is_last(): bool
    {
        $this->did_throw = true;
        throw null !== $this->error ? new Transport_Exception($this->error_message, 0, $this->error) : new Timeout_Exception($this->error_message);
    }
    public function get_informational_status(): ?array
    {
        $this->did_throw = true;
        throw null !== $this->error ? new Transport_Exception($this->error_message, 0, $this->error) : new Timeout_Exception($this->error_message);
    }
    public function get_content(): string
    {
        $this->did_throw = true;
        throw null !== $this->error ? new Transport_Exception($this->error_message, 0, $this->error) : new Timeout_Exception($this->error_message);
    }
    public function get_offset(): int
    {
        return $this->offset;
    }
    public function get_error(): ?string
    {
        return $this->error_message;
    }
    public function did_throw(?bool $did_throw = null): bool
    {
        if (null !== $did_throw && $this->did_throw !== $did_throw) {
            return !$this->did_throw = $did_throw;
        }
        return $this->did_throw;
    }
    public function __serialize(): array
    {
        throw new \BadMethodCallException('Cannot serialize ' . self::class);
    }
    public function __unserialize(array $data): void
    {
        throw new \BadMethodCallException('Cannot unserialize ' . self::class);
    }
    public function __destruct()
    {
        if (!$this->did_throw) {
            $this->did_throw = true;
            throw null !== $this->error ? new Transport_Exception($this->error_message, 0, $this->error) : new Timeout_Exception($this->error_message);
        }
    }
}