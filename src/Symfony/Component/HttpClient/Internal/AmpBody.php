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
namespace Symfony\Component\Http_Client\Internal;

use Amp\Byte_Stream\Readable_Buffer;
use Amp\Byte_Stream\Readable_Iterable_Stream;
use Amp\Byte_Stream\Readable_Resource_Stream;
use Amp\Byte_Stream\Readable_Stream;
use Amp\Cancellation;
use Amp\Http\Client\Http_Content;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Amp_Body implements Http_Content, Readable_Stream, \IteratorAggregate
{
    private Readable_Stream $body;
    private array $info;
    private ?int $offset = 0;
    private int $length = -1;
    private ?int $uploaded = null;
    /**
     * @param \Closure|resource|string $body
     */
    public function __construct($body, &$info, private readonly \Closure $on_progress)
    {
        $this->info =& $info;
        if (\is_resource($body)) {
            $this->offset = ftell($body);
            $this->length = fstat($body)['size'];
            $this->body = new Readable_Resource_Stream($body);
        } elseif (\is_string($body)) {
            $this->length = \strlen($body);
            $this->body = new Readable_Buffer($body);
        } else {
            $this->body = new Readable_Iterable_Stream((static function () use ($body) {
                while ('' !== $data = $body(16372)) {
                    if (!\is_string($data)) {
                        throw new Transport_Exception(\sprintf('Return value of the "body" option callback must be string, "%s" returned.', get_debug_type($data)));
                    }
                    yield $data;
                }
            })());
        }
    }
    public function get_content(): Readable_Stream
    {
        if (null !== $this->uploaded) {
            $this->uploaded = null;
            if (\is_string($this->body)) {
                $this->offset = 0;
            } elseif ($this->body instanceof Readable_Resource_Stream) {
                fseek($this->body->get_resource(), $this->offset);
            }
        }
        return $this;
    }
    public function get_content_type(): ?string
    {
        return null;
    }
    public function get_content_length(): ?int
    {
        return 0 <= $this->length ? $this->length - $this->offset : null;
    }
    public function read(?Cancellation $cancellation = null): ?string
    {
        $this->info['size_upload'] += $this->uploaded;
        $this->uploaded = 0;
        ($this->on_progress)();
        if (null !== $data = $this->body->read($cancellation)) {
            $this->uploaded = \strlen($data);
        } else {
            $this->info['upload_content_length'] = $this->info['size_upload'];
        }
        return $data;
    }
    public function is_readable(): bool
    {
        return $this->body->is_readable();
    }
    public function close(): void
    {
        $this->body->close();
    }
    public function is_closed(): bool
    {
        return $this->body->is_closed();
    }
    public function on_close(\Closure $on_close): void
    {
        $this->body->on_close($on_close);
    }
    public function getIterator(): \Traversable
    {
        return $this->body;
    }
    public static function rewind(Http_Content $body): Http_Content
    {
        if (!$body instanceof self) {
            return $body;
        }
        $body->uploaded = null;
        if ($body->body instanceof Readable_Resource_Stream && !$body->body->is_closed()) {
            fseek($body->body->get_resource(), $body->offset);
        }
        if ($body->body instanceof Readable_Buffer) {
            return new $body($body->content, $body->info, $body->on_progress);
        }
        return $body;
    }
}