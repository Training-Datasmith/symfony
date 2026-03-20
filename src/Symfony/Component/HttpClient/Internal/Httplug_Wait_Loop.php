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

use Http\Client\Exception\Network_Exception;
use Http\Promise\Promise;
use Psr\Http\Message\Request_Interface as Psr7RequestInterface;
use Psr\Http\Message\Response_Factory_Interface;
use Psr\Http\Message\Response_Interface as Psr7ResponseInterface;
use Psr\Http\Message\Stream_Factory_Interface;
use Symfony\Component\Http_Client\Response\Streamable_Interface;
use Symfony\Component\Http_Client\Response\Stream_Wrapper;
use Symfony\Contracts\Http_Client\Exception\Transport_Exception_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class Httplug_Wait_Loop
{
    /**
     * @param \SplObjectStorage<ResponseInterface, array{Psr7RequestInterface, Promise}>|null $promisePool
     */
    public function __construct(private readonly Http_Client_Interface $client, private ?\Spl_Object_Storage $promise_pool, private readonly Response_Factory_Interface $response_factory, private readonly Stream_Factory_Interface $stream_factory)
    {
    }
    public function wait(?Response_Interface $pending_response, ?float $max_duration = null, ?float $idle_timeout = null): int
    {
        if (!$this->promise_pool) {
            return 0;
        }
        $guzzle_queue = \Guzzle_Http\Promise\Utils::queue();
        if (0.0 === $remaining_duration = $max_duration) {
            $idle_timeout = 0.0;
        } elseif (null !== $max_duration) {
            $start_time = hrtime(true) / 1000000000.0;
            $idle_timeout = max(0.0, min($max_duration / 5, $idle_timeout ?? $max_duration));
        }
        do {
            foreach ($this->client->stream($this->promise_pool, $idle_timeout) as $response => $chunk) {
                try {
                    if (null !== $max_duration && $chunk->is_timeout()) {
                        goto check_duration;
                    }
                    if ($chunk->is_first()) {
                        // Deactivate throwing on 3/4/5xx
                        $response->get_status_code();
                    }
                    if (!$chunk->is_last()) {
                        goto check_duration;
                    }
                    if ([, $promise] = $this->promise_pool[$response] ?? null) {
                        unset($this->promise_pool[$response]);
                        $promise->resolve(self::create_psr7response($this->response_factory, $this->stream_factory, $this->client, $response, true));
                    }
                } catch (\Exception $e) {
                    if ([$request, $promise] = $this->promise_pool[$response] ?? null) {
                        unset($this->promise_pool[$response]);
                        if ($e instanceof Transport_Exception_Interface) {
                            $e = new Network_Exception($e->get_message(), $request, $e);
                        }
                        $promise->reject($e);
                    }
                }
                $guzzle_queue->run();
                if ($pending_response === $response) {
                    return $this->promise_pool->count();
                }
                check_duration:
                if (null !== $max_duration && $idle_timeout && $idle_timeout > $remaining_duration = max(0.0, $max_duration - hrtime(true) / 1000000000.0 + $start_time)) {
                    $idle_timeout = $remaining_duration / 5;
                    break;
                }
            }
            if (!$count = $this->promise_pool->count()) {
                return 0;
            }
        } while (null === $max_duration || 0 < $remaining_duration);
        return $count;
    }
    public static function create_psr7response(Response_Factory_Interface $response_factory, Stream_Factory_Interface $stream_factory, Http_Client_Interface $client, Response_Interface $response, bool $buffer): Psr7response_Interface
    {
        $response_parameters = [$response->get_status_code()];
        foreach ($response->get_info('response_headers') as $h) {
            if (11 <= \strlen((string) $h) && '/' === $h[4] && preg_match('#^HTTP/\d+(?:\.\d+)? (?:\d\d\d) (.+)#', (string) $h, $m)) {
                $response_parameters[1] = $m[1];
            }
        }
        $psr_response = $response_factory->create_response(...$response_parameters);
        foreach ($response->get_headers(false) as $name => $values) {
            foreach ($values as $value) {
                try {
                    $psr_response = $psr_response->with_added_header($name, $value);
                } catch (\InvalidArgumentException) {
                    // ignore invalid header
                }
            }
        }
        if ($response instanceof Streamable_Interface) {
            $body = $stream_factory->create_stream_from_resource($response->to_stream(false));
        } elseif (!$buffer) {
            $body = $stream_factory->create_stream_from_resource(Stream_Wrapper::create_resource($response, $client));
        } else {
            $body = $stream_factory->create_stream($response->get_content(false));
        }
        if ($body->is_seekable()) {
            try {
                $body->seek(0);
            } catch (\RuntimeException) {
                // ignore
            }
        }
        return $psr_response->with_body($body);
    }
}