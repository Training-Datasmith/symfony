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

use Psr\Log\Logger_Interface;
use Symfony\Component\Http_Client\Chunk\Data_Chunk;
use Symfony\Component\Http_Client\Chunk\Error_Chunk;
use Symfony\Component\Http_Client\Chunk\First_Chunk;
use Symfony\Component\Http_Client\Chunk\Last_Chunk;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Component\Http_Client\Internal\Canary;
use Symfony\Component\Http_Client\Internal\Client_State;
/**
 * Implements common logic for transport-level response classes.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait Transport_Response_Trait
{
    private Canary $canary;
    /** @var array<string, list<string>> */
    private array $headers = [];
    private array $info = ['response_headers' => [], 'http_code' => 0, 'error' => null, 'canceled' => false];
    /** @var object|resource|null */
    private $handle;
    private int|string $id;
    private ?float $timeout = 0;
    private \Inflate_Context|bool|null $inflate = null;
    private ?array $final_info = null;
    private ?Logger_Interface $logger = null;
    private bool $did_timeout = false;
    public function get_status_code(): int
    {
        if ($this->initializer) {
            self::initialize($this);
        }
        return $this->info['http_code'];
    }
    public function get_headers(bool $throw = true): array
    {
        if ($this->initializer) {
            self::initialize($this);
        }
        if ($throw) {
            $this->check_status_code();
        }
        return $this->headers;
    }
    public function cancel(): void
    {
        $this->info['canceled'] = true;
        $this->info['error'] = 'Response has been canceled.';
        $this->close();
    }
    /**
     * Closes the response and all its network handles.
     */
    protected function close(): void
    {
        $this->canary->cancel();
        $this->inflate = null;
    }
    /**
     * Adds pending responses to the activity list.
     */
    abstract protected static function schedule(self $response, array &$running_responses): void;
    /**
     * Performs all pending non-blocking operations.
     */
    abstract protected static function perform(Client_State $multi, array $responses): void;
    /**
     * Waits for network activity.
     */
    abstract protected static function select(Client_State $multi, float $timeout): int;
    private static function add_response_headers(array $response_headers, array &$info, array &$headers, string &$debug = ''): void
    {
        foreach ($response_headers as $h) {
            if (11 <= \strlen((string) $h) && '/' === $h[4] && preg_match('#^HTTP/\d+(?:\.\d+)? (\d\d\d)(?: |$)#', (string) $h, $m)) {
                if ($headers) {
                    $debug .= "< \r\n";
                    $headers = [];
                }
                $info['http_code'] = (int) $m[1];
            } elseif (2 === \count($m = explode(':', (string) $h, 2))) {
                $headers[strtolower($m[0])][] = ltrim($m[1]);
            }
            $debug .= "< {$h}\r\n";
            $info['response_headers'][] = $h;
        }
        $debug .= "< \r\n";
    }
    /**
     * Ensures the request is always sent and that the response code was checked.
     */
    private function do_destruct(): void
    {
        $this->should_buffer = true;
        if ($this->initializer && null === $this->info['error'] && !$this->did_timeout) {
            self::initialize($this);
            $this->check_status_code();
        }
    }
    /**
     * Implements an event loop based on a buffer activity queue.
     *
     * @param iterable<array-key, self> $responses
     *
     * @internal
     */
    public static function stream(iterable $responses, ?float $timeout = null): \Generator
    {
        $running_responses = [];
        foreach ($responses as $response) {
            self::schedule($response, $running_responses);
        }
        $last_activity = hrtime(true) / 1000000000.0;
        $elapsed_timeout = 0;
        if (0.0 === $timeout && '-0' === (string) $timeout || 0 > $timeout) {
            $timeout = $timeout ? -$timeout : null;
            /** @var ClientState $multi */
            foreach ($running_responses as [$multi]) {
                if (null !== $multi->last_timeout) {
                    $elapsed_timeout = max($elapsed_timeout, $last_activity - $multi->last_timeout);
                }
            }
        }
        while (true) {
            $has_activity = false;
            $timeout_max = 0;
            $timeout_min = $timeout ?? \INF;
            /** @var ClientState $multi */
            foreach ($running_responses as $i => [$multi, &$responses]) {
                self::perform($multi, $responses);
                foreach ($responses as $j => $response) {
                    $timeout_max = $timeout ?? max($timeout_max, $response->timeout);
                    $timeout_min = min($timeout_min, $response->timeout, 1);
                    $chunk = false;
                    if (isset($multi->handles_activity[$j])) {
                        $multi->last_timeout = null;
                        $elapsed_timeout = 0;
                    } elseif (!isset($multi->open_handles[$j])) {
                        $has_activity = true;
                        unset($responses[$j]);
                        continue;
                    } elseif ($elapsed_timeout >= $timeout_max) {
                        $response->did_timeout = true;
                        $multi->handles_activity[$j] = [new Error_Chunk($response->offset, \sprintf('Idle timeout reached for "%s".', $response->get_info('url')))];
                        $multi->last_timeout ??= $last_activity;
                        $elapsed_timeout = $timeout_max;
                    } else {
                        continue;
                    }
                    $last_activity = null;
                    $has_activity = true;
                    while ($multi->handles_activity[$j] ?? false) {
                        if (\is_string($chunk = array_shift($multi->handles_activity[$j]))) {
                            if (null !== $response->inflate && false === $chunk = @inflate_add($response->inflate, $chunk)) {
                                $multi->handles_activity[$j] = [null, new Transport_Exception(\sprintf('Error while processing content unencoding for "%s".', $response->get_info('url')))];
                                continue;
                            }
                            if ('' !== $chunk && null !== $response->content && \strlen($chunk) !== fwrite($response->content, $chunk)) {
                                $multi->handles_activity[$j] = [null, new Transport_Exception(\sprintf('Failed writing %d bytes to the response buffer.', \strlen($chunk)))];
                                continue;
                            }
                            $chunk_len = \strlen($chunk);
                            $chunk = new Data_Chunk($response->offset, $chunk);
                            $response->offset += $chunk_len;
                        } elseif (null === $chunk) {
                            $e = $multi->handles_activity[$j][0];
                            unset($responses[$j], $multi->handles_activity[$j]);
                            $response->close();
                            if (null !== $e) {
                                $response->info['error'] = $e->get_message();
                                if ($e instanceof \Error) {
                                    throw $e;
                                }
                                $chunk = new Error_Chunk($response->offset, $e);
                            } else {
                                if (0 === $response->offset && null === $response->content) {
                                    $response->content = fopen('php://memory', 'w+');
                                }
                                $chunk = new Last_Chunk($response->offset);
                            }
                        } elseif ($chunk instanceof Error_Chunk) {
                            unset($responses[$j]);
                        } elseif ($chunk instanceof First_Chunk) {
                            if ($response->logger) {
                                $info = $response->get_info();
                                $response->logger->info('Response: "{http_code} {url}" {total_time} seconds', ['http_code' => $info['http_code'], 'url' => $info['url'], 'total_time' => $info['total_time']]);
                            }
                            $response->inflate = \extension_loaded('zlib') && $response->inflate && 'gzip' === ($response->headers['content-encoding'][0] ?? null) ? inflate_init(\ZLIB_ENCODING_GZIP) : null;
                            if ($response->should_buffer instanceof \Closure) {
                                try {
                                    $response->should_buffer = ($response->should_buffer)($response->headers);
                                    if (null !== $response->info['error']) {
                                        throw new Transport_Exception($response->info['error']);
                                    }
                                } catch (\Throwable $e) {
                                    $response->close();
                                    $multi->handles_activity[$j] = [null, $e];
                                }
                            }
                            if (true === $response->should_buffer) {
                                $response->content = fopen('php://temp', 'w+');
                            } elseif (\is_resource($response->should_buffer)) {
                                $response->content = $response->should_buffer;
                            }
                            $response->should_buffer = null;
                            yield $response => $chunk;
                            if ($response->initializer && null === $response->info['error']) {
                                // Ensure the HTTP status code is always checked
                                $response->get_headers(true);
                            }
                            continue;
                        }
                        yield $response => $chunk;
                    }
                    unset($multi->handles_activity[$j]);
                    if ($chunk instanceof Error_Chunk && !$chunk->did_throw()) {
                        // Ensure transport exceptions are always thrown
                        $chunk->get_content();
                        throw new \LogicException('A transport exception should have been thrown.');
                    }
                }
                if (!$responses) {
                    $has_activity = true;
                    unset($running_responses[$i]);
                }
                // Prevent memory leaks
                $multi->handles_activity = $multi->handles_activity ?: [];
                $multi->open_handles = $multi->open_handles ?: [];
            }
            if (!$running_responses) {
                break;
            }
            if ($has_activity) {
                $last_activity ??= hrtime(true) / 1000000000.0;
                continue;
            }
            if (-1 === self::select($multi, min($timeout_min, max(0, $timeout_max - $elapsed_timeout)))) {
                usleep((int) min(500, 1000000.0 * $timeout_min));
            }
            $elapsed_timeout = hrtime(true) / 1000000000.0 - $last_activity;
        }
    }
}