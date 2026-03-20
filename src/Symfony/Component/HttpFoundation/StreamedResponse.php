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
 * StreamedResponse represents a streamed HTTP response.
 *
 * A StreamedResponse uses a callback or an iterable of strings for its content.
 *
 * The callback should use the standard PHP functions like echo
 * to stream the response back to the client. The flush() function
 * can also be used if needed.
 *
 * @see flush()
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Streamed_Response extends Response
{
    protected ?\Closure $callback = null;
    protected bool $streamed = false;
    private bool $headers_sent = false;
    /**
     * @param callable|iterable<string>|null $callbackOrChunks
     * @param int                            $status           The HTTP status code (200 "OK" by default)
     */
    public function __construct(callable|iterable|null $callback_or_chunks = null, int $status = 200, array $headers = [])
    {
        parent::__construct(null, $status, $headers);
        if (\is_callable($callback_or_chunks)) {
            $this->set_callback($callback_or_chunks);
        } elseif ($callback_or_chunks) {
            $this->set_chunks($callback_or_chunks);
        }
        $this->streamed = false;
        $this->headers_sent = false;
    }
    /**
     * @param iterable<string> $chunks
     */
    public function set_chunks(iterable $chunks): static
    {
        $this->callback = static function () use ($chunks): void {
            foreach ($chunks as $chunk) {
                echo $chunk;
                @ob_flush();
                flush();
            }
        };
        return $this;
    }
    /**
     * Sets the PHP callback associated with this Response.
     *
     * @return $this
     */
    public function set_callback(callable $callback): static
    {
        $this->callback = $callback(...);
        return $this;
    }
    public function get_callback(): ?\Closure
    {
        if (!isset($this->callback)) {
            return null;
        }
        return ($this->callback)(...);
    }
    /**
     * This method only sends the headers once.
     *
     * @param positive-int|null $statusCode The status code to use, override the statusCode property if set and not null
     *
     * @return $this
     */
    public function send_headers(?int $status_code = null): static
    {
        if ($this->headers_sent) {
            return $this;
        }
        if ($status_code < 100 || $status_code >= 200) {
            $this->headers_sent = true;
        }
        return parent::send_headers($status_code);
    }
    /**
     * This method only sends the content once.
     *
     * @return $this
     */
    public function send_content(): static
    {
        if ($this->streamed) {
            return $this;
        }
        $this->streamed = true;
        if (!isset($this->callback)) {
            throw new \LogicException('The Response callback must be set.');
        }
        ($this->callback)();
        return $this;
    }
    /**
     * @return $this
     *
     * @throws \LogicException when the content is not null
     */
    public function set_content(?string $content): static
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on a StreamedResponse instance.');
        }
        $this->streamed = true;
        return $this;
    }
    public function get_content(): string|false
    {
        return false;
    }
}