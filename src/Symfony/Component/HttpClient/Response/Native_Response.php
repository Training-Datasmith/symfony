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
use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Component\Http_Client\Internal\Canary;
use Symfony\Component\Http_Client\Internal\Client_State;
use Symfony\Component\Http_Client\Internal\Native_Client_State;
use Symfony\Contracts\Http_Client\Response_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class Native_Response implements Response_Interface, Streamable_Interface
{
    use Common_Response_Trait;
    use Transport_Response_Trait;
    private \Closure $resolver;
    private ?\Closure $on_progress;
    private ?int $remaining = null;
    /**
     * @var resource|null
     */
    private $buffer;
    private float $pause_expiry = 0.0;
    /**
     * @internal
     *
     * @param $context resource
     */
    public function __construct(private Native_Client_State $multi, private $context, private string $url, array $options, array &$info, callable $resolver, ?callable $on_progress, ?Logger_Interface $logger)
    {
        $this->id = $id = (int) $context;
        $this->logger = $logger;
        $this->timeout = $options['timeout'];
        $this->info =& $info;
        $this->resolver = $resolver(...);
        $this->on_progress = $on_progress ? $on_progress(...) : null;
        $this->inflate = !isset($options['normalized_headers']['accept-encoding']);
        $this->should_buffer = $options['buffer'] ?? true;
        // Temporary resource to dechunk the response stream
        $this->buffer = fopen('php://temp', 'w+');
        $info['original_url'] = implode('', $info['url']);
        $info['user_data'] = $options['user_data'];
        $info['max_duration'] = $options['max_duration'];
        $info['max_connect_duration'] = $options['max_connect_duration'];
        ++$multi->response_count;
        $this->initializer = static fn(self $response): bool => null === $response->remaining;
        $pause_expiry =& $this->pause_expiry;
        $info['pause_handler'] = static function (float $duration) use (&$pause_expiry): void {
            $pause_expiry = 0 < $duration ? hrtime(true) / 1000000000.0 + $duration : 0;
        };
        $this->canary = new Canary(static function () use ($multi, $id): void {
            if (null !== ($host = $multi->open_handles[$id][6] ?? null) && isset($multi->hosts[$host]) && 0 >= --$multi->hosts[$host]) {
                unset($multi->hosts[$host]);
            }
            unset($multi->open_handles[$id], $multi->handles_activity[$id]);
        });
    }
    public function get_info(?string $type = null): mixed
    {
        if (!$info = $this->final_info) {
            $info = $this->info;
            $info['url'] = implode('', $info['url']);
            unset($info['size_body'], $info['request_header']);
            if (null === $this->buffer) {
                $this->final_info = $info;
            }
        }
        return null !== $type ? $info[$type] ?? null : $info;
    }
    public function __destruct()
    {
        try {
            $this->do_destruct();
        } finally {
            // Clear the DNS cache when all requests completed
            if (0 >= --$this->multi->response_count) {
                $this->multi->response_count = 0;
                $this->multi->dns_cache = [];
            }
        }
    }
    private function close(): void
    {
        $this->canary->cancel();
        $this->handle = $this->buffer = $this->inflate = $this->on_progress = null;
    }
    private static function schedule(self $response, array &$running_responses): void
    {
        if (!isset($running_responses[$i = $response->multi->id])) {
            $running_responses[$i] = [$response->multi, []];
        }
        $running_responses[$i][1][$response->id] = $response;
        if (null === $response->buffer) {
            // Response already completed
            $response->multi->handles_activity[$response->id][] = null;
            $response->multi->handles_activity[$response->id][] = null !== $response->info['error'] ? new Transport_Exception($response->info['error']) : null;
        }
    }
    /**
     * @param NativeClientState $multi
     */
    private static function perform(Client_State $multi, ?array $responses = null): void
    {
        foreach ($multi->open_handles as $i => [$pause_expiry, $h, $buffer, $on_progress]) {
            if ($pause_expiry) {
                if (hrtime(true) / 1000000000.0 < $pause_expiry) {
                    continue;
                }
                $multi->open_handles[$i][0] = 0;
            }
            $has_activity = false;
            $remaining =& $multi->open_handles[$i][4];
            $info =& $multi->open_handles[$i][5];
            $e = null;
            // Read incoming buffer and write it to the dechunk one
            try {
                if ($remaining && '' !== $data = (string) fread($h, 0 > $remaining ? 16372 : $remaining)) {
                    fwrite($buffer, $data);
                    $has_activity = true;
                    $multi->sleep = false;
                    if (-1 !== $remaining) {
                        $remaining -= \strlen($data);
                    }
                }
            } catch (\Throwable $e) {
                $has_activity = $on_progress = false;
            }
            if (!$has_activity) {
                if ($on_progress) {
                    try {
                        // Notify the progress callback so that it can e.g. cancel
                        // the request if the stream is inactive for too long
                        $info['total_time'] = microtime(true) - $info['start_time'];
                        $on_progress();
                    } catch (\Throwable) {
                        // no-op
                    }
                }
            } elseif ('' !== $data = stream_get_contents($buffer, -1, 0)) {
                rewind($buffer);
                ftruncate($buffer, 0);
                if (null === $e) {
                    $multi->handles_activity[$i][] = $data;
                }
            }
            if (null !== $e || !$remaining || feof($h)) {
                // Stream completed
                $info['total_time'] = microtime(true) - $info['start_time'];
                $info['starttransfer_time'] = $info['starttransfer_time'] ?: $info['total_time'];
                if ($on_progress) {
                    try {
                        $on_progress(-1);
                    } catch (\Throwable) {
                        // no-op
                    }
                }
                if (null === $e) {
                    if (0 < $remaining) {
                        $e = new Transport_Exception(\sprintf('Transfer closed with %s bytes remaining to read.', $remaining));
                    } elseif (-1 === $remaining && fwrite($buffer, '-') && '' !== stream_get_contents($buffer, -1, 0)) {
                        $e = new Transport_Exception('Transfer closed with outstanding data remaining from chunked response.');
                    }
                }
                $multi->handles_activity[$i][] = null;
                $multi->handles_activity[$i][] = $e;
                if (null !== ($host = $multi->open_handles[$i][6] ?? null) && isset($multi->hosts[$host]) && 0 >= --$multi->hosts[$host]) {
                    unset($multi->hosts[$host]);
                }
                unset($multi->open_handles[$i]);
                $multi->sleep = false;
            }
        }
        if (null === $responses) {
            return;
        }
        $max_hosts = $multi->max_host_connections;
        foreach ($responses as $i => $response) {
            if (null !== $response->remaining) {
                continue;
            }
            if (null === $response->buffer) {
                continue;
            }
            if ($response->pause_expiry && hrtime(true) / 1000000000.0 < $response->pause_expiry) {
                // Create empty open handles to tell we still have pending requests
                $multi->open_handles[$i] = [\INF, null, null, null];
            } elseif ($max_hosts && $max_hosts > ($multi->hosts[parse_url((string) $response->url, \PHP_URL_HOST)] ?? 0)) {
                // Open the next pending request - this is a blocking operation so we do only one of them
                $response->open();
                $multi->sleep = false;
                self::perform($multi);
                $max_hosts = 0;
            }
        }
    }
    /**
     * @param NativeClientState $multi
     */
    private static function select(Client_State $multi, float $timeout): int
    {
        if (!$multi->sleep = !$multi->sleep) {
            return -1;
        }
        $_ = $handles = [];
        $now = null;
        foreach ($multi->open_handles as [$pause_expiry, $h]) {
            if (null === $h) {
                continue;
            }
            if ($pause_expiry && ($now ??= hrtime(true) / 1000000000.0) < $pause_expiry) {
                $timeout = min($timeout, $pause_expiry - $now);
                continue;
            }
            $handles[] = $h;
        }
        if (!$handles) {
            usleep((int) (1000000.0 * $timeout));
            return 0;
        }
        return stream_select($handles, $_, $_, (int) $timeout, (int) (1000000.0 * ($timeout - (int) $timeout)));
    }
}