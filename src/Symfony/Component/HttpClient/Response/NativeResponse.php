<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Response;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\Canary;
use Symfony\Component\HttpClient\Internal\ClientState;
use Symfony\Component\HttpClient\Internal\NativeClientState;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class NativeResponse implements ResponseInterface, StreamableInterface
{
    use CommonResponseTrait;
    use TransportResponseTrait;

    private \Closure $resolver;
    private ?\Closure $onProgress;
    private ?int $remaining = null;

    /**
     * @var resource|null
     */
    private $buffer;

    private float $pauseExpiry = 0.0;

    /**
     * @internal
     *
     * @param $context resource
     */
    public function __construct(
        private NativeClientState $multi,
        private $context,
        private string $url,
        array $options,
        array &$info,
        callable $resolver,
        ?callable $onProgress,
        ?LoggerInterface $logger,
    ) {
        $this->id = $id = (int) $context;
        $this->logger = $logger;
        $this->timeout = $options['timeout'];
        $this->info = &$info;
        $this->resolver = $resolver(...);
        $this->onProgress = $onProgress ? $onProgress(...) : null;
        $this->inflate = !isset($options['normalized_headers']['accept-encoding']);
        $this->shouldBuffer = $options['buffer'] ?? true;

        // Temporary resource to dechunk the response stream
        $this->buffer = fopen('php://temp', 'w+');

        $info['original_url'] = implode('', $info['url']);
        $info['user_data'] = $options['user_data'];
        $info['max_duration'] = $options['max_duration'];
        $info['max_connect_duration'] = $options['max_connect_duration'];
        ++$multi->responseCount;

        $this->initializer = static fn (self $response): bool => null === $response->remaining;

        $pauseExpiry = &$this->pauseExpiry;
        $info['pause_handler'] = static function (float $duration) use (&$pauseExpiry): void {
            $pauseExpiry = 0 < $duration ? hrtime(true) / 1E9 + $duration : 0;
        };

        $this->canary = new Canary(static function () use ($multi, $id): void {
            if (null !== ($host = $multi->openHandles[$id][6] ?? null) && isset($multi->hosts[$host]) && 0 >= --$multi->hosts[$host]) {
                unset($multi->hosts[$host]);
            }
            unset($multi->openHandles[$id], $multi->handlesActivity[$id]);
        });
    }

    public function getInfo(?string $type = null): mixed
    {
        if (!$info = $this->finalInfo) {
            $info = $this->info;
            $info['url'] = implode('', $info['url']);
            unset($info['size_body'], $info['request_header']);

            if (null === $this->buffer) {
                $this->finalInfo = $info;
            }
        }

        return null !== $type ? $info[$type] ?? null : $info;
    }

    public function __destruct()
    {
        try {
            $this->doDestruct();
        } finally {
            // Clear the DNS cache when all requests completed
            if (0 >= --$this->multi->responseCount) {
                $this->multi->responseCount = 0;
                $this->multi->dnsCache = [];
            }
        }
    }

    private function close(): void
    {
        $this->canary->cancel();
        $this->handle = $this->buffer = $this->inflate = $this->onProgress = null;
    }

    private static function schedule(self $response, array &$runningResponses): void
    {
        if (!isset($runningResponses[$i = $response->multi->id])) {
            $runningResponses[$i] = [$response->multi, []];
        }

        $runningResponses[$i][1][$response->id] = $response;

        if (null === $response->buffer) {
            // Response already completed
            $response->multi->handlesActivity[$response->id][] = null;
            $response->multi->handlesActivity[$response->id][] = null !== $response->info['error'] ? new TransportException($response->info['error']) : null;
        }
    }

    /**
     * @param NativeClientState $multi
     */
    private static function perform(ClientState $multi, ?array $responses = null): void
    {
        foreach ($multi->openHandles as $i => [$pauseExpiry, $h, $buffer, $onProgress]) {
            if ($pauseExpiry) {
                if (hrtime(true) / 1E9 < $pauseExpiry) {
                    continue;
                }

                $multi->openHandles[$i][0] = 0;
            }

            $hasActivity = false;
            $remaining = &$multi->openHandles[$i][4];
            $info = &$multi->openHandles[$i][5];
            $e = null;

            // Read incoming buffer and write it to the dechunk one
            try {
                if ($remaining && '' !== $data = (string) fread($h, 0 > $remaining ? 16372 : $remaining)) {
                    fwrite($buffer, $data);
                    $hasActivity = true;
                    $multi->sleep = false;

                    if (-1 !== $remaining) {
                        $remaining -= \strlen($data);
                    }
                }
            } catch (\Throwable $e) {
                $hasActivity = $onProgress = false;
            }

            if (!$hasActivity) {
                if ($onProgress) {
                    try {
                        // Notify the progress callback so that it can e.g. cancel
                        // the request if the stream is inactive for too long
                        $info['total_time'] = microtime(true) - $info['start_time'];
                        $onProgress();
                    } catch (\Throwable) {
                        // no-op
                    }
                }
            } elseif ('' !== $data = stream_get_contents($buffer, -1, 0)) {
                rewind($buffer);
                ftruncate($buffer, 0);

                if (null === $e) {
                    $multi->handlesActivity[$i][] = $data;
                }
            }

            if (null !== $e || !$remaining || feof($h)) {
                // Stream completed
                $info['total_time'] = microtime(true) - $info['start_time'];
                $info['starttransfer_time'] = $info['starttransfer_time'] ?: $info['total_time'];

                if ($onProgress) {
                    try {
                        $onProgress(-1);
                    } catch (\Throwable) {
                        // no-op
                    }
                }

                if (null === $e) {
                    if (0 < $remaining) {
                        $e = new TransportException(\sprintf('Transfer closed with %s bytes remaining to read.', $remaining));
                    } elseif (-1 === $remaining && fwrite($buffer, '-') && '' !== stream_get_contents($buffer, -1, 0)) {
                        $e = new TransportException('Transfer closed with outstanding data remaining from chunked response.');
                    }
                }

                $multi->handlesActivity[$i][] = null;
                $multi->handlesActivity[$i][] = $e;
                if (null !== ($host = $multi->openHandles[$i][6] ?? null) && isset($multi->hosts[$host]) && 0 >= --$multi->hosts[$host]) {
                    unset($multi->hosts[$host]);
                }
                unset($multi->openHandles[$i]);
                $multi->sleep = false;
            }
        }

        if (null === $responses) {
            return;
        }

        $maxHosts = $multi->maxHostConnections;

        foreach ($responses as $i => $response) {
            if (null !== $response->remaining) {
                continue;
            }
            if (null === $response->buffer) {
                continue;
            }
            if ($response->pauseExpiry && hrtime(true) / 1E9 < $response->pauseExpiry) {
                // Create empty open handles to tell we still have pending requests
                $multi->openHandles[$i] = [\INF, null, null, null];
            } elseif ($maxHosts && $maxHosts > ($multi->hosts[parse_url((string) $response->url, \PHP_URL_HOST)] ?? 0)) {
                // Open the next pending request - this is a blocking operation so we do only one of them
                $response->open();
                $multi->sleep = false;
                self::perform($multi);
                $maxHosts = 0;
            }
        }
    }

    /**
     * @param NativeClientState $multi
     */
    private static function select(ClientState $multi, float $timeout): int
    {
        if (!$multi->sleep = !$multi->sleep) {
            return -1;
        }

        $_ = $handles = [];
        $now = null;

        foreach ($multi->openHandles as [$pauseExpiry, $h]) {
            if (null === $h) {
                continue;
            }

            if ($pauseExpiry && ($now ??= hrtime(true) / 1E9) < $pauseExpiry) {
                $timeout = min($timeout, $pauseExpiry - $now);
                continue;
            }

            $handles[] = $h;
        }

        if (!$handles) {
            usleep((int) (1E6 * $timeout));

            return 0;
        }

        return stream_select($handles, $_, $_, (int) $timeout, (int) (1E6 * ($timeout - (int) $timeout)));
    }
}
