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
use Symfony\Component\Http_Client\Chunk\First_Chunk;
use Symfony\Component\Http_Client\Exception\InvalidArgumentException;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Component\Http_Client\Internal\Client_State;
use Symfony\Contracts\Http_Client\Response_Interface;
/**
 * A test-friendly response.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Mock_Response implements Response_Interface, Streamable_Interface
{
    use Common_Response_Trait;
    use Transport_Response_Trait;
    private array $request_options = [];
    private string $request_url;
    private string $request_method;
    private static Client_State $main_multi;
    private static int $id_sequence = 0;
    /**
     * @param string|iterable<string|\Throwable> $body The response body as a string or an iterable of strings,
     *                                                 yielding an empty string simulates an idle timeout,
     *                                                 throwing or yielding an exception yields an ErrorChunk
     *
     * @see ResponseInterface::getInfo() for possible info, e.g. "response_headers"
     */
    public function __construct(private string|iterable|null $body = '', array $info = [])
    {
        $this->info = $info + ['http_code' => 200] + $this->info;
        if (!isset($info['response_headers'])) {
            return;
        }
        $response_headers = [];
        foreach ($info['response_headers'] as $k => $v) {
            foreach ((array) $v as $v) {
                $response_headers[] = (\is_string($k) ? $k . ': ' : '') . $v;
            }
        }
        $this->info['response_headers'] = [];
        self::add_response_headers($response_headers, $this->info, $this->headers);
    }
    public static function from_file(string $path, array $info = []): static
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('File not found: "%s".', $path));
        }
        return new static(file_get_contents($path), $info);
    }
    /**
     * Returns the options used when doing the request.
     */
    public function get_request_options(): array
    {
        return $this->request_options;
    }
    /**
     * Returns the URL used when doing the request.
     */
    public function get_request_url(): string
    {
        return $this->request_url;
    }
    /**
     * Returns the method used when doing the request.
     */
    public function get_request_method(): string
    {
        return $this->request_method;
    }
    public function get_info(?string $type = null): mixed
    {
        return null !== $type ? $this->info[$type] ?? null : $this->info;
    }
    public function cancel(): void
    {
        $this->info['canceled'] = true;
        $this->info['error'] = 'Response has been canceled.';
        try {
            $this->body = null;
        } catch (Transport_Exception) {
            // ignore errors when canceling
        }
        $on_progress = $this->request_options['on_progress'] ?? static function (): void {
        };
        $dl_size = isset($this->headers['content-encoding']) || 'HEAD' === ($this->info['http_method'] ?? null) || \in_array($this->info['http_code'], [204, 304], true) ? 0 : (int) ($this->headers['content-length'][0] ?? 0);
        $on_progress($this->offset, $dl_size, $this->info);
    }
    public function __destruct()
    {
        $this->do_destruct();
    }
    protected function close(): void
    {
        $this->inflate = null;
        $this->body = [];
    }
    /**
     * @internal
     */
    public static function from_request(string $method, string $url, array $options, Response_Interface $mock): self
    {
        $response = new self([]);
        $response->request_options = $options;
        $response->id = ++self::$id_sequence;
        $response->should_buffer = $options['buffer'] ?? true;
        $response->initializer = static fn(self $response): bool => \is_array($response->body[0] ?? null);
        $response->info['redirect_count'] = 0;
        $response->info['redirect_url'] = null;
        $response->info['start_time'] = microtime(true);
        $response->info['http_method'] = $method;
        $response->info['http_code'] = 0;
        $response->info['user_data'] = $options['user_data'] ?? null;
        $response->info['max_duration'] = $options['max_duration'] ?? null;
        $response->info['max_connect_duration'] = $options['max_connect_duration'] ?? null;
        $response->info['url'] = $url;
        $response->info['original_url'] = $url;
        if ($mock instanceof self) {
            $mock->request_options = $response->request_options;
            $mock->request_method = $method;
            $mock->request_url = $url;
        }
        self::write_request($response, $options, $mock);
        $response->body[] = [$options, $mock];
        return $response;
    }
    protected static function schedule(self $response, array &$running_responses): void
    {
        if (!isset($response->id)) {
            throw new InvalidArgumentException('MockResponse instances must be issued by MockHttpClient before processing.');
        }
        $multi = self::$main_multi ??= new Client_State();
        if (!isset($running_responses[0])) {
            $running_responses[0] = [$multi, []];
        }
        $running_responses[0][1][$response->id] = $response;
    }
    protected static function perform(Client_State $multi, array $responses): void
    {
        foreach ($responses as $response) {
            $id = $response->id;
            if (null === $response->body) {
                // Canceled response
                $response->body = [];
            } elseif ([] === $response->body) {
                // Error chunk
                $multi->handles_activity[$id][] = null;
                $multi->handles_activity[$id][] = null !== $response->info['error'] ? new Transport_Exception($response->info['error']) : null;
            } elseif (null === $chunk = array_shift($response->body)) {
                // Last chunk
                $multi->handles_activity[$id][] = null;
                $multi->handles_activity[$id][] = array_shift($response->body);
            } elseif (\is_array($chunk)) {
                // First chunk
                try {
                    $offset = 0;
                    $chunk[1]->get_status_code();
                    $chunk[1]->get_headers(false);
                    self::read_response($response, $chunk[0], $chunk[1], $offset);
                    $multi->handles_activity[$id][] = new First_Chunk();
                } catch (\Throwable $e) {
                    $multi->handles_activity[$id][] = null;
                    $multi->handles_activity[$id][] = $e;
                }
            } elseif ($chunk instanceof \Throwable) {
                $multi->handles_activity[$id][] = null;
                $multi->handles_activity[$id][] = $chunk;
            } else {
                // Data or timeout chunk
                $multi->handles_activity[$id][] = $chunk;
            }
        }
    }
    protected static function select(Client_State $multi, float $timeout): int
    {
        return 42;
    }
    /**
     * Simulates sending the request.
     */
    private static function write_request(self $response, array $options, Response_Interface $mock): void
    {
        $on_progress = $options['on_progress'] ?? static function (): void {
        };
        $response->info += $mock->get_info() ?: [];
        if (null !== $mock->get_info('start_time')) {
            $response->info['start_time'] = $mock->get_info('start_time');
        }
        // simulate "size_upload" if it is set
        if (isset($response->info['size_upload'])) {
            $response->info['size_upload'] = 0.0;
        }
        // simulate "total_time" if it is not set
        if (!isset($response->info['total_time'])) {
            $response->info['total_time'] = microtime(true) - $response->info['start_time'];
        }
        // "notify" DNS resolution
        $on_progress(0, 0, $response->info);
        // consume the request body
        if (\is_resource($body = $options['body'] ?? '')) {
            $data = stream_get_contents($body);
            if (isset($response->info['size_upload'])) {
                $response->info['size_upload'] += \strlen($data);
            }
        } elseif ($body instanceof \Closure) {
            while ('' !== $data = $body(16372)) {
                if (!\is_string($data)) {
                    throw new Transport_Exception(\sprintf('Return value of the "body" option callback must be string, "%s" returned.', get_debug_type($data)));
                }
                // "notify" upload progress
                if (isset($response->info['size_upload'])) {
                    $response->info['size_upload'] += \strlen($data);
                }
                $on_progress(0, 0, $response->info);
            }
        }
    }
    /**
     * Simulates reading the response.
     */
    private static function read_response(self $response, array $options, Response_Interface $mock, int &$offset): void
    {
        $on_progress = $options['on_progress'] ?? static function (): void {
        };
        // populate info related to headers
        $info = $mock->get_info() ?: [];
        $response->info['http_code'] = ($info['http_code'] ?? 0 ?: $mock->get_status_code()) ?: 200;
        $response->add_response_headers($info['response_headers'] ?? [], $response->info, $response->headers);
        $dl_size = isset($response->headers['content-encoding']) || 'HEAD' === $response->info['http_method'] || \in_array($response->info['http_code'], [204, 304], true) ? 0 : (int) ($response->headers['content-length'][0] ?? 0);
        $response->info = ['start_time' => $response->info['start_time'], 'user_data' => $response->info['user_data'], 'max_duration' => $response->info['max_duration'], 'max_connect_duration' => $response->info['max_connect_duration'], 'http_code' => $response->info['http_code']] + $info + $response->info;
        if (null !== $response->info['error']) {
            throw new Transport_Exception($response->info['error']);
        }
        if (!isset($response->info['total_time'])) {
            $response->info['total_time'] = microtime(true) - $response->info['start_time'];
        }
        // "notify" headers arrival
        $on_progress(0, $dl_size, $response->info);
        // cast response body to activity list
        $body = $mock instanceof self ? $mock->body : $mock->get_content(false);
        if (!\is_string($body)) {
            try {
                foreach ($body as $chunk) {
                    if ($chunk instanceof \Throwable) {
                        throw $chunk;
                    }
                    if ('' === $chunk = (string) $chunk) {
                        // simulate an idle timeout
                        $response->body[] = new Error_Chunk($offset, \sprintf('Idle timeout reached for "%s".', $response->info['url']));
                    } else {
                        $response->body[] = $chunk;
                        $offset += \strlen($chunk);
                        // "notify" download progress
                        $on_progress($offset, $dl_size, $response->info);
                    }
                }
            } catch (\Throwable $e) {
                $response->body[] = $e;
            }
        } elseif ('' !== $body) {
            $response->body[] = $body;
            $offset = \strlen($body);
        }
        if (!isset($response->info['total_time'])) {
            $response->info['total_time'] = microtime(true) - $response->info['start_time'];
        }
        // "notify" completion
        $on_progress($offset, $dl_size, $response->info);
        if ($dl_size && $offset !== $dl_size) {
            throw new Transport_Exception(\sprintf('Transfer closed with %d bytes remaining to read.', $dl_size - $offset));
        }
    }
}