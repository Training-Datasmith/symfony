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
namespace Symfony\Component\Http_Client;

use Symfony\Component\Http_Client\Chunk\Data_Chunk;
use Symfony\Component\Http_Client\Chunk\Server_Sent_Event;
use Symfony\Component\Http_Client\Exception\Event_Source_Exception;
use Symfony\Component\Http_Client\Response\Async_Context;
use Symfony\Component\Http_Client\Response\Async_Response;
use Symfony\Contracts\Http_Client\Chunk_Interface;
use Symfony\Contracts\Http_Client\Exception\Transport_Exception_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * @author Antoine Bluchet <soyuka@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Event_Source_Http_Client implements Http_Client_Interface, Reset_Interface
{
    use Async_Decorator_Trait, Http_Client_Trait {
        Async_Decorator_Trait::withOptions insteadof Http_Client_Trait;
    }
    public function __construct(?Http_Client_Interface $client = null, private float $reconnection_time = 10.0)
    {
        $this->client = $client ?? Http_Client::create();
    }
    public function connect(string $url, array $options = [], string $method = 'GET'): Response_Interface
    {
        return $this->request($method, $url, self::merge_default_options($options, ['buffer' => false, 'headers' => ['Accept' => 'text/event-stream', 'Cache-Control' => 'no-cache']], true));
    }
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        $state = new class
        {
            public ?string $buffer = null;
            public ?string $last_event_id = null;
            public float $reconnection_time;
            public ?float $last_error = null;
        };
        $state->reconnection_time = $this->reconnection_time;
        if ($accept = self::normalize_headers($options['headers'] ?? [])['accept'] ?? []) {
            $state->buffer = \in_array($accept, [['Accept: text/event-stream'], ['accept: text/event-stream']], true) ? '' : null;
            if (null !== $state->buffer) {
                $options['extra']['trace_content'] = false;
            }
        }
        return new Async_Response($this->client, $method, $url, $options, static function (Chunk_Interface $chunk, Async_Context $context) use ($state, $method, $url, $options) {
            if (null !== $state->buffer) {
                $context->set_info('reconnection_time', $state->reconnection_time);
                $is_timeout = false;
            }
            $last_error = $state->last_error;
            $state->last_error = null;
            try {
                $is_timeout = $chunk->is_timeout();
                if (null !== $chunk->get_informational_status() || $context->get_info('canceled')) {
                    yield $chunk;
                    return;
                }
            } catch (Transport_Exception_Interface) {
                $state->last_error = $last_error ?? hrtime(true) / 1000000000.0;
                if (null === $state->buffer || $is_timeout && hrtime(true) / 1000000000.0 - $state->last_error < $state->reconnection_time) {
                    yield $chunk;
                } else {
                    $options['headers']['Last-Event-ID'] = $state->last_event_id;
                    $state->buffer = '';
                    $state->last_error = hrtime(true) / 1000000000.0;
                    $context->get_response()->cancel();
                    $context->replace_request($method, $url, $options);
                    if ($is_timeout) {
                        yield $chunk;
                    } else {
                        $context->pause($state->reconnection_time);
                    }
                }
                return;
            }
            if ($chunk->is_first()) {
                if (preg_match('/^text\/event-stream(;|$)/i', $context->get_headers()['content-type'][0] ?? '')) {
                    $state->buffer = '';
                } elseif (null !== $last_error || null !== $state->buffer && 200 === $context->get_status_code()) {
                    throw new Event_Source_Exception(\sprintf('Response content-type is "%s" while "text/event-stream" was expected for "%s".', $context->get_headers()['content-type'][0] ?? '', $context->get_info('url')));
                } else {
                    $context->passthru();
                }
                if (null === $last_error) {
                    yield $chunk;
                }
                return;
            }
            if ($chunk->is_last()) {
                if ('' !== $content = $state->buffer) {
                    $state->buffer = '';
                    yield new Data_Chunk(-1, $content);
                }
                yield $chunk;
                return;
            }
            $content = $state->buffer . $chunk->get_content();
            $events = preg_split('/((?:\r\n){2,}|\r{2,}|\n{2,})/', $content, -1, \PREG_SPLIT_DELIM_CAPTURE);
            $state->buffer = array_pop($events);
            for ($i = 0; isset($events[$i]); $i += 2) {
                $content = $events[$i] . $events[1 + $i];
                if (!preg_match('/(?:^|\r\n|[\r\n])[^:\r\n]/', $content)) {
                    yield new Data_Chunk(-1, $content);
                    continue;
                }
                $event = new Server_Sent_Event($content);
                if ('' !== $event->get_id()) {
                    $context->set_info('last_event_id', $state->last_event_id = $event->get_id());
                }
                if ($event->get_retry()) {
                    $context->set_info('reconnection_time', $state->reconnection_time = $event->get_retry());
                }
                yield $event;
            }
        });
    }
}