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

use Psr\Log\Logger_Interface;
use Symfony\Component\Http_Client\Response\Async_Context;
use Symfony\Component\Http_Client\Response\Async_Response;
use Symfony\Component\Http_Client\Retry\Generic_Retry_Strategy;
use Symfony\Component\Http_Client\Retry\Retry_Strategy_Interface;
use Symfony\Contracts\Http_Client\Chunk_Interface;
use Symfony\Contracts\Http_Client\Exception\Transport_Exception_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Automatically retries failing HTTP requests.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class Retryable_Http_Client implements Http_Client_Interface, Reset_Interface
{
    use Async_Decorator_Trait;
    private array $base_uris = [];
    /**
     * @param int $maxRetries The maximum number of times to retry
     */
    public function __construct(Http_Client_Interface $client, private ?Retry_Strategy_Interface $strategy = new Generic_Retry_Strategy(), private int $max_retries = 3, private ?Logger_Interface $logger = null)
    {
        $this->client = $client;
    }
    public function with_options(array $options): static
    {
        if (\array_key_exists('base_uri', $options)) {
            if (\is_array($options['base_uri'])) {
                $this->base_uris = $options['base_uri'];
                unset($options['base_uri']);
            } else {
                $this->base_uris = [];
            }
        }
        $clone = clone $this;
        $clone->max_retries = (int) ($options['max_retries'] ?? $this->max_retries);
        unset($options['max_retries']);
        $clone->client = $this->client->with_options($options);
        return $clone;
    }
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        $base_uris = \array_key_exists('base_uri', $options) ? $options['base_uri'] : $this->base_uris;
        $base_uris = \is_array($base_uris) ? $base_uris : [];
        $options = self::shift_base_uri($options, $base_uris);
        $max_retries = (int) ($options['max_retries'] ?? $this->max_retries);
        unset($options['max_retries']);
        if ($max_retries <= 0) {
            return new Async_Response($this->client, $method, $url, $options);
        }
        return new Async_Response($this->client, $method, $url, $options, function (Chunk_Interface $chunk, Async_Context $context) use ($method, $url, $options, $max_retries, &$base_uris) {
            static $retry_count = 0;
            static $content = '';
            static $first_chunk;
            $exception = null;
            try {
                if ($context->get_info('canceled') || $chunk->is_timeout() || null !== $chunk->get_informational_status()) {
                    yield $chunk;
                    return;
                }
            } catch (Transport_Exception_Interface $exception) {
                // catch TransportExceptionInterface to send it to the strategy
            }
            if (null !== $exception) {
                // always retry request that fail to resolve DNS
                if ('' !== $context->get_info('primary_ip')) {
                    $should_retry = $this->strategy->should_retry($context, null, $exception);
                    if (null === $should_retry) {
                        throw new \LogicException(\sprintf('The "%s::shouldRetry()" method must not return null when called with an exception.', $this->strategy::class));
                    }
                    if (false === $should_retry) {
                        yield from $this->passthru($context, $first_chunk, $content, $chunk);
                        return;
                    }
                }
            } elseif ($chunk->is_first()) {
                if (false === $should_retry = $this->strategy->should_retry($context, null, null)) {
                    yield from $this->passthru($context, $first_chunk, $content, $chunk);
                    return;
                }
                // Body is needed to decide
                if (null === $should_retry) {
                    $first_chunk = $chunk;
                    $content = '';
                    return;
                }
            } else {
                if (!$chunk->is_last()) {
                    $content .= $chunk->get_content();
                    return;
                }
                if (null === $should_retry = $this->strategy->should_retry($context, $content, null)) {
                    throw new \LogicException(\sprintf('The "%s::shouldRetry()" method must not return null when called with a body.', $this->strategy::class));
                }
                if (false === $should_retry) {
                    yield from $this->passthru($context, $first_chunk, $content, $chunk);
                    return;
                }
            }
            $context->get_response()->cancel();
            $delay = $this->get_delay_from_header($context->get_headers()) ?? $this->strategy->get_delay($context, !$exception && $chunk->is_last() ? $content : null, $exception);
            ++$retry_count;
            $content = '';
            $first_chunk = null;
            $this->logger?->info('Try #{count} after {delay}ms' . ($exception ? ': ' . $exception->get_message() : ', status code: ' . $context->get_status_code()), ['count' => $retry_count, 'delay' => $delay]);
            $context->set_info('retry_count', $retry_count);
            $context->replace_request($method, $url, self::shift_base_uri($options, $base_uris));
            $context->pause($delay / 1000);
            if ($retry_count >= $max_retries) {
                $context->passthru();
            }
        });
    }
    private function get_delay_from_header(array $headers): ?int
    {
        if (null !== $after = $headers['retry-after'][0] ?? null) {
            if (is_numeric($after)) {
                return (int) ($after * 1000);
            }
            if (false !== $time = strtotime((string) $after)) {
                return max(0, $time - time()) * 1000;
            }
        }
        return null;
    }
    private function passthru(Async_Context $context, ?Chunk_Interface $first_chunk, string &$content, Chunk_Interface $last_chunk): \Generator
    {
        $context->passthru();
        if (null !== $first_chunk) {
            yield $first_chunk;
        }
        if ('' !== $content) {
            $chunk = $context->create_chunk($content);
            $content = '';
            yield $chunk;
        }
        yield $last_chunk;
    }
    private static function shift_base_uri(array $options, array &$base_uris): array
    {
        if ($base_uris) {
            $base_uri = 1 < \count($base_uris) ? array_shift($base_uris) : current($base_uris);
            $options['base_uri'] = \is_array($base_uri) ? $base_uri[array_rand($base_uri)] : $base_uri;
        } elseif (\is_array($options['base_uri'] ?? null)) {
            unset($options['base_uri']);
        }
        return $options;
    }
}