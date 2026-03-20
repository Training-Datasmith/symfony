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

use Symfony\Component\Http_Client\Chunk\Error_Chunk;
use Symfony\Component\Http_Client\Chunk\Last_Chunk;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Contracts\Http_Client\Chunk_Interface;
use Symfony\Contracts\Http_Client\Exception\Exception_Interface;
use Symfony\Contracts\Http_Client\Exception\Http_Exception_Interface;
use Symfony\Contracts\Http_Client\Exception\Transport_Exception_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
/**
 * Provides a single extension point to process a response's content stream.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Async_Response implements Response_Interface, Streamable_Interface
{
    use Common_Response_Trait;
    private const FIRST_CHUNK_YIELDED = 1;
    private const LAST_CHUNK_YIELDED = 2;
    private Response_Interface $response;
    private array $info = ['canceled' => false];
    /** @var callable|null */
    private $passthru;
    private ?\Iterator $stream = null;
    private ?int $yielded_state = null;
    private bool $has_thrown = false;
    /**
     * @param ?callable(ChunkInterface, AsyncContext): ?\Iterator $passthru
     */
    public function __construct(private ?Http_Client_Interface $client, string $method, string $url, array $options, ?callable $passthru = null)
    {
        $this->should_buffer = $options['buffer'] ?? true;
        if (null !== $on_progress = $options['on_progress'] ?? null) {
            $this_info =& $this->info;
            $options['on_progress'] = static function (int $dl_now, int $dl_size, array $info) use (&$this_info, $on_progress): void {
                $on_progress($dl_now, $dl_size, $this_info + $info);
            };
        }
        $this->response = $this->client->request($method, $url, ['buffer' => false] + $options);
        $this->passthru = $passthru;
        $this->initializer = static function (self $response, ?float $timeout = null) {
            if (null === $response->should_buffer) {
                return false;
            }
            while (true) {
                foreach (self::stream([$response], $timeout) as $chunk) {
                    if ($chunk->is_timeout() && ($response->passthru || $response = self::find_inner_passthru($response))) {
                        // Timeouts thrown during initialization are transport errors
                        foreach (self::passthru($response->client, $response, new Error_Chunk($response->offset, new Transport_Exception($chunk->get_error()))) as $chunk) {
                            if ($chunk->is_first()) {
                                return false;
                            }
                        }
                        continue 2;
                    }
                    if ($chunk->is_first()) {
                        return false;
                    }
                }
                return false;
            }
        };
        if (\array_key_exists('user_data', $options)) {
            $this->info['user_data'] = $options['user_data'];
        }
        if (\array_key_exists('max_duration', $options)) {
            $this->info['max_duration'] = $options['max_duration'];
        }
    }
    public function get_status_code(): int
    {
        if ($this->initializer) {
            self::initialize($this);
        }
        return $this->response->get_status_code();
    }
    public function get_headers(bool $throw = true): array
    {
        if ($this->initializer) {
            self::initialize($this);
        }
        $headers = $this->response->get_headers(false);
        if ($throw) {
            $this->check_status_code();
        }
        return $headers;
    }
    public function get_info(?string $type = null): mixed
    {
        if ('debug' === ($type ?? 'debug')) {
            $debug = implode('', array_column($this->info['previous_info'] ?? [], 'debug'));
            $debug .= $this->response->get_info('debug');
            if ('debug' === $type) {
                return $debug;
            }
        }
        if (null !== $type) {
            return $this->info[$type] ?? $this->response->get_info($type);
        }
        return array_merge($this->info + $this->response->get_info(), ['debug' => $debug]);
    }
    /**
     * @return resource
     */
    public function to_stream(bool $throw = true)
    {
        if ($throw) {
            // Ensure headers arrived
            $this->get_headers(true);
        }
        $handle = function () {
            $stream = $this->response instanceof Streamable_Interface ? $this->response->to_stream(false) : Stream_Wrapper::create_resource($this->response);
            return stream_get_meta_data($stream)['wrapper_data']->stream_cast(\STREAM_CAST_FOR_SELECT);
        };
        $stream = Stream_Wrapper::create_resource($this);
        stream_get_meta_data($stream)['wrapper_data']->bind_handles($handle, $this->content);
        return $stream;
    }
    public function cancel(): void
    {
        if ($this->info['canceled']) {
            return;
        }
        $this->info['canceled'] = true;
        $this->info['error'] = 'Response has been canceled.';
        $this->close();
        $client = $this->client;
        $this->client = null;
        if (!$this->passthru) {
            return;
        }
        try {
            foreach (self::passthru($client, $this, new Last_Chunk()) as $chunk) {
                // no-op
            }
            $this->passthru = null;
        } catch (Exception_Interface) {
            // ignore any errors when canceling
        }
    }
    public function __destruct()
    {
        $http_exception = null;
        if ($this->initializer && null === $this->get_info('error') && !$this->has_thrown) {
            try {
                self::initialize($this);
                $this->get_headers(true);
            } catch (Http_Exception_Interface) {
                // no-op
            }
        }
        if ($this->passthru && null === $this->get_info('error')) {
            $this->info['canceled'] = true;
            try {
                foreach (self::passthru($this->client, $this, new Last_Chunk()) as $chunk) {
                    // no-op
                }
            } catch (Exception_Interface) {
                // ignore any errors when destructing
            }
        }
        if (null !== $http_exception) {
            throw $http_exception;
        }
    }
    /**
     * @internal
     */
    public static function stream(iterable $responses, ?float $timeout = null, ?string $class = null): \Generator
    {
        while ($responses) {
            $wrapped_responses = [];
            $async_map = new \Spl_Object_Storage();
            $client = null;
            foreach ($responses as $r) {
                if (!$r instanceof self) {
                    throw new \TypeError(\sprintf('"%s::stream()" expects parameter 1 to be an iterable of AsyncResponse objects, "%s" given.', $class ?? static::class, get_debug_type($r)));
                }
                if (null !== $e = $r->info['error'] ?? null) {
                    yield $r => $chunk = new Error_Chunk($r->offset, new Transport_Exception($e));
                    $chunk->did_throw() ?: $chunk->get_content();
                    continue;
                }
                if (null === $client) {
                    $client = $r->client;
                } elseif ($r->client !== $client) {
                    throw new Transport_Exception('Cannot stream AsyncResponse objects with many clients.');
                }
                $async_map[$r->response] = $r;
                $wrapped_responses[] = $r->response;
                if ($r->stream) {
                    yield from self::passthru_stream($response = $r->response, $r, $async_map, new Last_Chunk());
                    if (!isset($async_map[$response])) {
                        array_pop($wrapped_responses);
                    }
                    if ($r->response !== $response && !isset($async_map[$r->response])) {
                        $async_map[$r->response] = $r;
                        $wrapped_responses[] = $r->response;
                    }
                }
            }
            if (!$client || !$wrapped_responses) {
                return;
            }
            $chunk = null;
            foreach ($client->stream($wrapped_responses, $timeout) as $response => $chunk) {
                $r = $async_map[$response];
                if (null === $chunk->get_error()) {
                    if ($chunk->is_first()) {
                        // Ensure no exception is thrown on destruct for the wrapped response
                        $r->response->get_status_code();
                    } elseif (0 === $r->offset && null === $r->content && $chunk->is_last()) {
                        $r->content = fopen('php://memory', 'w+');
                    }
                }
                $inner_r = null;
                if (!$r->passthru && !$inner_r = null !== $chunk->get_error() ? self::find_inner_passthru($r) : null) {
                    $r->stream = (static fn() => yield $chunk)();
                    yield from self::passthru_stream($response, $r, $async_map);
                    continue;
                }
                if (null !== $chunk->get_error()) {
                    // no-op
                } elseif ($chunk->is_first()) {
                    $r->yielded_state = self::FIRST_CHUNK_YIELDED;
                } elseif (self::FIRST_CHUNK_YIELDED !== $r->yielded_state && null === $chunk->get_informational_status()) {
                    throw new \LogicException(\sprintf('Instance of "%s" is already consumed and cannot be managed by "%s". A decorated client should not call any of the response\'s methods in its "request()" method.', get_debug_type($response), $class ?? static::class));
                }
                $inner_r ??= $r;
                foreach (self::passthru($inner_r->client, $inner_r, $chunk, $async_map) as $chunk) {
                    yield $r => $chunk;
                }
                if ($inner_r->response !== $response && isset($async_map[$response])) {
                    break;
                }
            }
            if (null === $chunk) {
                throw new \LogicException(\sprintf('"%s" is not compliant with HttpClientInterface: its "stream()" method didn\'t yield any chunks when it should have.', get_debug_type($client)));
            }
            if (null === $chunk->get_error() && $chunk->is_last()) {
                $r->yielded_state = self::LAST_CHUNK_YIELDED;
            }
            if (null === $chunk->get_error() && self::LAST_CHUNK_YIELDED !== $r->yielded_state && $r->response === $response && null !== $r->client) {
                throw new \LogicException('A chunk passthru must yield an "isLast()" chunk before ending a stream.');
            }
            $responses = [];
            foreach ($async_map as $response) {
                $r = $async_map[$response];
                if (null !== $r->client) {
                    $responses[] = $r;
                }
            }
        }
    }
    /**
     * @param \SplObjectStorage<ResponseInterface, AsyncResponse>|null $asyncMap
     */
    private static function passthru(Http_Client_Interface $client, self $r, Chunk_Interface $chunk, ?\Spl_Object_Storage $async_map = null): \Generator
    {
        $r->stream = null;
        $response = $r->response;
        $context = new Async_Context($r->passthru, $client, $r->response, $r->info, $r->content, $r->offset);
        if (null === $stream = ($r->passthru)($chunk, $context)) {
            if ($r->response === $response && (null !== $chunk->get_error() || $chunk->is_last())) {
                throw new \LogicException('A chunk passthru cannot swallow the last chunk.');
            }
            return;
        }
        if (!$stream instanceof \Iterator) {
            throw new \LogicException(\sprintf('A chunk passthru must return an "Iterator", "%s" returned.', get_debug_type($stream)));
        }
        $r->stream = $stream;
        yield from self::passthru_stream($response, $r, $async_map);
    }
    private static function find_inner_passthru(self $response): ?self
    {
        $inner_response = $response->response ?? null;
        while ($inner_response instanceof self) {
            if ($inner_response->passthru) {
                return $inner_response;
            }
            $inner_response = $inner_response->response ?? null;
        }
        return null;
    }
    /**
     * @param \SplObjectStorage<ResponseInterface, AsyncResponse>|null $asyncMap
     */
    private static function passthru_stream(Response_Interface $response, self $r, ?\Spl_Object_Storage $async_map, ?Chunk_Interface $chunk = null): \Generator
    {
        while (true) {
            try {
                if (null !== $chunk && $r->stream) {
                    $r->stream->next();
                }
                if (!$r->stream || !$r->stream->valid() || !$r->stream) {
                    $r->stream = null;
                    break;
                }
            } catch (\Throwable $e) {
                unset($async_map[$response]);
                $r->stream = null;
                $r->info['error'] = $e->get_message();
                $r->response->cancel();
                yield $r => $chunk = new Error_Chunk($r->offset, $e);
                $chunk->did_throw() ?: $chunk->get_content();
                break;
            }
            $chunk = $r->stream->current();
            if (!$chunk instanceof Chunk_Interface) {
                throw new \LogicException(\sprintf('A chunk passthru must yield instances of "%s", "%s" yielded.', Chunk_Interface::class, get_debug_type($chunk)));
            }
            if (null !== $chunk->get_error()) {
                // no-op
            } elseif ($chunk->is_first()) {
                $e = $r->open_buffer();
                yield $r => $chunk;
                if ($r->initializer && null === $r->get_info('error')) {
                    // Ensure the HTTP status code is always checked
                    $r->get_headers(true);
                }
                if (null === $e) {
                    continue;
                }
                $r->response->cancel();
                $chunk = new Error_Chunk($r->offset, $e);
            } elseif ('' !== $content = $chunk->get_content()) {
                if (null !== $r->should_buffer) {
                    throw new \LogicException('A chunk passthru must yield an "isFirst()" chunk before any content chunk.');
                }
                if (null !== $r->content && \strlen($content) !== fwrite($r->content, $content)) {
                    $chunk = new Error_Chunk($r->offset, new Transport_Exception(\sprintf('Failed writing %d bytes to the response buffer.', \strlen($content))));
                    $r->info['error'] = $chunk->get_error();
                    $r->response->cancel();
                }
            }
            if (null !== $chunk->get_error() || $chunk->is_last()) {
                $stream = $r->stream;
                $r->stream = null;
                unset($async_map[$response]);
            }
            if (null === $chunk->get_error()) {
                $r->offset += \strlen($content);
                yield $r => $chunk;
                if (!$chunk->is_last()) {
                    continue;
                }
                $stream->next();
                if ($stream->valid()) {
                    throw new \LogicException('A chunk passthru cannot yield after an "isLast()" chunk.');
                }
                $r->passthru = null;
            } else {
                if ($chunk instanceof Error_Chunk) {
                    $chunk->did_throw(false);
                } else {
                    try {
                        $chunk = new Error_Chunk($chunk->get_offset(), !$chunk->is_timeout() ?: $chunk->get_error());
                    } catch (Transport_Exception_Interface $e) {
                        $chunk = new Error_Chunk($chunk->get_offset(), $e);
                    }
                }
                $r->has_thrown = true;
                yield $r => $chunk;
                $chunk->did_throw() ?: $chunk->get_content();
            }
            break;
        }
    }
    private function open_buffer(): ?\Throwable
    {
        if (null === $should_buffer = $this->should_buffer) {
            throw new \LogicException('A chunk passthru cannot yield more than one "isFirst()" chunk.');
        }
        $e = $this->should_buffer = null;
        if ($should_buffer instanceof \Closure) {
            try {
                $should_buffer = $should_buffer($this->get_headers(false));
                if (null !== $e = $this->response->get_info('error')) {
                    throw new Transport_Exception($e);
                }
            } catch (\Throwable $e) {
                $this->info['error'] = $e->get_message();
                $this->response->cancel();
            }
        }
        if (true === $should_buffer) {
            $this->content = fopen('php://temp', 'w+');
        } elseif (\is_resource($should_buffer)) {
            $this->content = $should_buffer;
        }
        return $e;
    }
    private function close(): void
    {
        $this->response->cancel();
    }
}