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

use Symfony\Component\Http_Client\Chunk\Data_Chunk;
use Symfony\Component\Http_Client\Chunk\Last_Chunk;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Contracts\Http_Client\Chunk_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
/**
 * A DTO to work with AsyncResponse.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Async_Context
{
    /** @var callable|null */
    private $passthru;
    private Response_Interface $response;
    private array $info = [];
    /**
     * @param resource|null $content
     */
    public function __construct(?callable &$passthru, private readonly Http_Client_Interface $client, Response_Interface &$response, array &$info, private $content, private readonly int $offset)
    {
        $this->passthru =& $passthru;
        $this->response =& $response;
        $this->info =& $info;
    }
    /**
     * Returns the HTTP status without consuming the response.
     */
    public function get_status_code(): int
    {
        return $this->response->get_info('http_code');
    }
    /**
     * Returns the headers without consuming the response.
     */
    public function get_headers(): array
    {
        $headers = [];
        foreach ($this->response->get_info('response_headers') as $h) {
            if (11 <= \strlen((string) $h) && '/' === $h[4] && preg_match('#^HTTP/\d+(?:\.\d+)? ([123456789]\d\d)(?: |$)#', (string) $h, $m)) {
                $headers = [];
            } elseif (2 === \count($m = explode(':', (string) $h, 2))) {
                $headers[strtolower($m[0])][] = ltrim($m[1]);
            }
        }
        return $headers;
    }
    /**
     * @return resource|null The PHP stream resource where the content is buffered, if it is
     */
    public function get_content()
    {
        return $this->content;
    }
    /**
     * Creates a new chunk of content.
     */
    public function create_chunk(string $data): Chunk_Interface
    {
        return new Data_Chunk($this->offset, $data);
    }
    /**
     * Pauses the request for the given number of seconds.
     */
    public function pause(float $duration): void
    {
        if (\is_callable($pause = $this->response->get_info('pause_handler'))) {
            $pause($duration);
        } elseif (0 < $duration) {
            usleep((int) (1000000.0 * $duration));
        }
    }
    /**
     * Cancels the request and returns the last chunk to yield.
     */
    public function cancel(): Chunk_Interface
    {
        $this->info['canceled'] = true;
        $this->info['error'] = 'Response has been canceled.';
        $this->response->cancel();
        return new Last_Chunk();
    }
    /**
     * Returns the current info of the response.
     */
    public function get_info(?string $type = null): mixed
    {
        if (null !== $type) {
            return $this->info[$type] ?? $this->response->get_info($type);
        }
        return $this->info + $this->response->get_info();
    }
    /**
     * Attaches an info to the response.
     *
     * @return $this
     */
    public function set_info(string $type, mixed $value): static
    {
        if ('canceled' === $type && $value !== $this->info['canceled']) {
            throw new \LogicException('You cannot set the "canceled" info directly.');
        }
        if (null === $value) {
            unset($this->info[$type]);
        } else {
            $this->info[$type] = $value;
        }
        return $this;
    }
    /**
     * Returns the currently processed response.
     */
    public function get_response(): Response_Interface
    {
        return $this->response;
    }
    /**
     * Replaces the currently processed response by doing a new request.
     */
    public function replace_request(string $method, string $url, array $options = []): Response_Interface
    {
        $this->info['previous_info'][] = $info = $this->response->get_info();
        if (null !== $on_progress = $options['on_progress'] ?? null) {
            $this_info =& $this->info;
            $options['on_progress'] = static function (int $dl_now, int $dl_size, array $info) use (&$this_info, $on_progress): void {
                $on_progress($dl_now, $dl_size, $this_info + $info);
            };
        }
        if (0 < ($info['max_duration'] ?? 0) && 0 < ($info['total_time'] ?? 0)) {
            if (0 >= $options['max_duration'] = $info['max_duration'] - $info['total_time']) {
                throw new Transport_Exception(\sprintf('Max duration was reached for "%s".', $info['url']));
            }
        }
        return $this->response = $this->client->request($method, $url, ['buffer' => false] + $options);
    }
    /**
     * Replaces the currently processed response by another one.
     */
    public function replace_response(Response_Interface $response): Response_Interface
    {
        $this->info['previous_info'][] = $this->response->get_info();
        return $this->response = $response;
    }
    /**
     * Replaces or removes the chunk filter iterator.
     *
     * @param ?callable(ChunkInterface, self): ?\Iterator $passthru
     */
    public function passthru(?callable $passthru = null): void
    {
        $this->passthru = $passthru ?? static function ($chunk, $context) {
            $context->passthru = null;
            yield $chunk;
        };
    }
}