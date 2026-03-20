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
namespace Symfony\Component\Http_Foundation;

/**
 * An event generated on the server intended for streaming to the client
 * as part of the SSE streaming technique.
 *
 * @implements \IteratorAggregate<string>
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class Server_Event implements \IteratorAggregate
{
    /**
     * @param string|iterable<string> $data    The event data field for the message
     * @param string|null             $type    The event type
     * @param int|null                $retry   The number of milliseconds the client should wait
     *                                         before reconnecting in case of network failure
     * @param string|null             $id      The event ID to set the EventSource object's last event ID value
     * @param string|null             $comment The event comment
     */
    public function __construct(private string|iterable $data, private ?string $type = null, private ?int $retry = null, private ?string $id = null, private ?string $comment = null)
    {
    }
    public function get_data(): iterable|string
    {
        return $this->data;
    }
    /**
     * @return $this
     */
    public function set_data(iterable|string $data): static
    {
        $this->data = $data;
        return $this;
    }
    public function get_type(): ?string
    {
        return $this->type;
    }
    /**
     * @return $this
     */
    public function set_type(string $type): static
    {
        $this->type = $type;
        return $this;
    }
    public function get_retry(): ?int
    {
        return $this->retry;
    }
    /**
     * @return $this
     */
    public function set_retry(?int $retry): static
    {
        $this->retry = $retry;
        return $this;
    }
    public function get_id(): ?string
    {
        return $this->id;
    }
    /**
     * @return $this
     */
    public function set_id(string $id): static
    {
        $this->id = $id;
        return $this;
    }
    public function get_comment(): ?string
    {
        return $this->comment;
    }
    public function set_comment(string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }
    /**
     * @return \Traversable<string>
     */
    public function getIterator(): \Traversable
    {
        static $last_retry = null;
        $head = '';
        if ($this->comment) {
            $head .= \sprintf(': %s', $this->comment) . "\n";
        }
        if ($this->id) {
            $head .= \sprintf('id: %s', $this->id) . "\n";
        }
        if ($this->retry > 0 && $this->retry !== $last_retry) {
            $head .= \sprintf('retry: %s', $last_retry = $this->retry) . "\n";
        }
        if ($this->type) {
            $head .= \sprintf('event: %s', $this->type) . "\n";
        }
        yield $head;
        if (is_iterable($this->data)) {
            foreach ($this->data as $data) {
                yield \sprintf('data: %s', $data) . "\n";
            }
        } elseif ('' !== $this->data) {
            yield \sprintf('data: %s', $this->data) . "\n";
        }
        yield "\n";
    }
}