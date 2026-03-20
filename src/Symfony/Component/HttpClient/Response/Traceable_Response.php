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
use Symfony\Component\Http_Client\Exception\Client_Exception;
use Symfony\Component\Http_Client\Exception\Redirection_Exception;
use Symfony\Component\Http_Client\Exception\Server_Exception;
use Symfony\Component\Http_Client\Traceable_Http_Client;
use Symfony\Component\Stopwatch\Stopwatch_Event;
use Symfony\Contracts\Http_Client\Exception\Client_Exception_Interface;
use Symfony\Contracts\Http_Client\Exception\Redirection_Exception_Interface;
use Symfony\Contracts\Http_Client\Exception\Server_Exception_Interface;
use Symfony\Contracts\Http_Client\Exception\Transport_Exception_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Traceable_Response implements Response_Interface, Streamable_Interface
{
    public function __construct(private readonly Http_Client_Interface $client, private readonly Response_Interface $response, private mixed &$content = false, private readonly ?Stopwatch_Event $event = null)
    {
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
        try {
            if (method_exists($this->response, '__destruct')) {
                $this->response->__destruct();
            }
        } finally {
            if ($this->event?->is_started()) {
                $this->event->stop();
            }
        }
    }
    public function get_status_code(): int
    {
        try {
            return $this->response->get_status_code();
        } finally {
            if ($this->event?->is_started()) {
                $this->event->lap();
            }
        }
    }
    public function get_headers(bool $throw = true): array
    {
        try {
            return $this->response->get_headers($throw);
        } finally {
            if ($this->event?->is_started()) {
                $this->event->lap();
            }
        }
    }
    public function get_content(bool $throw = true): string
    {
        try {
            if (false === $this->content) {
                return $this->response->get_content($throw);
            }
            return $this->content = $this->response->get_content(false);
        } finally {
            if ($this->event?->is_started()) {
                $this->event->stop();
            }
            if ($throw) {
                $this->check_status_code($this->response->get_status_code());
            }
        }
    }
    public function to_array(bool $throw = true): array
    {
        try {
            if (false === $this->content) {
                return $this->response->to_array($throw);
            }
            return $this->content = $this->response->to_array(false);
        } finally {
            if ($this->event?->is_started()) {
                $this->event->stop();
            }
            if ($throw) {
                $this->check_status_code($this->response->get_status_code());
            }
        }
    }
    public function cancel(): void
    {
        $this->response->cancel();
        if ($this->event?->is_started()) {
            $this->event->stop();
        }
    }
    public function get_info(?string $type = null): mixed
    {
        return $this->response->get_info($type);
    }
    /**
     * Casts the response to a PHP stream resource.
     *
     * @return resource
     *
     * @throws TransportExceptionInterface   When a network error occurs
     * @throws RedirectionExceptionInterface On a 3xx when $throw is true and the "max_redirects" option has been reached
     * @throws ClientExceptionInterface      On a 4xx when $throw is true
     * @throws ServerExceptionInterface      On a 5xx when $throw is true
     */
    public function to_stream(bool $throw = true)
    {
        if ($throw) {
            // Ensure headers arrived
            $this->response->get_headers(true);
        }
        if ($this->response instanceof Streamable_Interface) {
            return $this->response->to_stream(false);
        }
        return Stream_Wrapper::create_resource($this->response, $this->client);
    }
    /**
     * @internal
     */
    public static function stream(Http_Client_Interface $client, iterable $responses, ?float $timeout): \Generator
    {
        $wrapped_responses = [];
        $traceable_map = new \Spl_Object_Storage();
        foreach ($responses as $r) {
            if (!$r instanceof self) {
                throw new \TypeError(\sprintf('"%s::stream()" expects parameter 1 to be an iterable of TraceableResponse objects, "%s" given.', Traceable_Http_Client::class, get_debug_type($r)));
            }
            $traceable_map[$r->response] = $r;
            $wrapped_responses[] = $r->response;
            if ($r->event && !$r->event->is_started()) {
                $r->event->start();
            }
        }
        foreach ($client->stream($wrapped_responses, $timeout) as $r => $chunk) {
            if ($traceable_map[$r]->event && $traceable_map[$r]->event->is_started()) {
                try {
                    if ($chunk->is_timeout() || !$chunk->is_last()) {
                        $traceable_map[$r]->event->lap();
                    } else {
                        $traceable_map[$r]->event->stop();
                    }
                } catch (Transport_Exception_Interface $e) {
                    $traceable_map[$r]->event->stop();
                    if ($chunk instanceof Error_Chunk) {
                        $chunk->did_throw(false);
                    } else {
                        $chunk = new Error_Chunk($chunk->get_offset(), $e);
                    }
                }
            }
            yield $traceable_map[$r] => $chunk;
        }
    }
    private function check_status_code(int $code): void
    {
        if (500 <= $code) {
            throw new Server_Exception($this);
        }
        if (400 <= $code) {
            throw new Client_Exception($this);
        }
        if (300 <= $code) {
            throw new Redirection_Exception($this);
        }
    }
}