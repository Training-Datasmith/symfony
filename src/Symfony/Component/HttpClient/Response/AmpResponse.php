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

use Amp\Byte_Stream\Stream_Exception;
use Amp\Deferred_Cancellation;
use Amp\Deferred_Future;
use function Amp\delay;
use Amp\Future;
use function Amp\Future\Await_First;
use Amp\Http\Client\Http_Exception;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Psr\Log\Logger_Interface;
use Revolt\Event_Loop;
use Symfony\Component\Http_Client\Chunk\First_Chunk;
use Symfony\Component\Http_Client\Chunk\Informational_Chunk;
use Symfony\Component\Http_Client\Exception\InvalidArgumentException;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Component\Http_Client\Http_Client_Trait;
use Symfony\Component\Http_Client\Internal\Amp_Body;
use Symfony\Component\Http_Client\Internal\Amp_Client_State;
use Symfony\Component\Http_Client\Internal\Canary;
use Symfony\Component\Http_Client\Internal\Client_State;
use Symfony\Contracts\Http_Client\Response_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class Amp_Response implements Response_Interface, Streamable_Interface
{
    use Common_Response_Trait;
    use Transport_Response_Trait;
    private static string $next_id = 'a';
    private ?array $options = null;
    private \Closure $on_progress;
    /**
     * @internal
     */
    public function __construct(private Amp_Client_State $multi, Request $request, array $options, ?Logger_Interface $logger)
    {
        $this->options =& $options;
        $this->logger = $logger;
        $this->timeout = $options['timeout'];
        $this->should_buffer = $options['buffer'];
        if ($this->inflate = \extension_loaded('zlib') && !$request->has_header('accept-encoding')) {
            $request->set_header('Accept-Encoding', 'gzip');
        }
        $this->initializer = static fn(self $response): bool => null !== $response->options;
        $info =& $this->info;
        $headers =& $this->headers;
        $canceller = new Deferred_Cancellation();
        $handle =& $this->handle;
        $info['url'] = (string) $request->get_uri();
        $info['http_method'] = $request->get_method();
        $info['start_time'] = null;
        $info['redirect_url'] = null;
        $info['original_url'] = $info['url'];
        $info['redirect_time'] = 0.0;
        $info['redirect_count'] = 0;
        $info['size_upload'] = 0.0;
        $info['size_download'] = 0.0;
        $info['upload_content_length'] = -1.0;
        $info['download_content_length'] = -1.0;
        $info['user_data'] = $options['user_data'];
        $info['max_duration'] = $options['max_duration'];
        $info['max_connect_duration'] = $options['max_connect_duration'];
        $info['debug'] = '';
        $on_progress = $options['on_progress'] ?? static function (): void {
        };
        $on_progress = $this->on_progress = static function () use (&$info, $on_progress): void {
            $info['total_time'] = microtime(true) - $info['start_time'];
            $on_progress((int) $info['size_download'], ((int) (1 + $info['download_content_length']) ?: 1) - 1, $info);
        };
        $pause = 0.0;
        $this->id = $id = self::$next_id;
        self::$next_id = str_increment(self::$next_id);
        $info['pause_handler'] = static function (float $duration) use (&$pause): void {
            $pause = $duration;
        };
        $multi->last_timeout = null;
        $multi->open_handles[$id] = new Deferred_Future();
        ++$multi->response_count;
        $this->canary = new Canary(static function () use ($canceller, $multi, $id): void {
            $canceller->cancel();
            $multi->open_handles[$id]?->is_complete() || $multi->open_handles[$id]?->complete();
            unset($multi->open_handles[$id], $multi->handles_activity[$id]);
        });
        Event_Loop::queue(static function () use ($request, $multi, $id, &$info, &$headers, $canceller, &$options, $on_progress, &$handle, $logger, &$pause): void {
            self::generate_response($request, $multi, $id, $info, $headers, $canceller, $options, $on_progress, $handle, $logger, $pause);
        });
    }
    public function get_info(?string $type = null): mixed
    {
        return null !== $type ? $this->info[$type] ?? null : $this->info;
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
            $this->do_destruct();
        } finally {
            // Clear the DNS cache when all requests completed
            if (0 >= --$this->multi->response_count) {
                $this->multi->response_count = 0;
                $this->multi->dns_cache = [];
            }
        }
    }
    private static function schedule(self $response, array &$running_responses): void
    {
        if (isset($running_responses[0])) {
            $running_responses[0][1][$response->id] = $response;
        } else {
            $running_responses[0] = [$response->multi, [$response->id => $response]];
        }
        if (!isset($response->multi->open_handles[$response->id])) {
            $response->multi->handles_activity[$response->id][] = null;
            $response->multi->handles_activity[$response->id][] = null !== $response->info['error'] ? new Transport_Exception($response->info['error']) : null;
        }
    }
    /**
     * @param AmpClientState $multi
     */
    private static function perform(Client_State $multi, ?array $responses = null): void
    {
        if ($responses) {
            foreach ($responses as $response) {
                try {
                    if ($response->info['start_time']) {
                        $response->info['total_time'] = microtime(true) - $response->info['start_time'];
                        ($response->on_progress)();
                    }
                } catch (\Throwable $e) {
                    $multi->handles_activity[$response->id][] = null;
                    $multi->handles_activity[$response->id][] = $e;
                }
            }
        }
    }
    /**
     * @param AmpClientState $multi
     */
    private static function select(Client_State $multi, float $timeout): int
    {
        $delay = new Deferred_Future();
        $id = Event_Loop::delay($timeout, $delay->complete(...));
        await_first((static function () use ($delay, $multi) {
            yield $delay->get_future();
            foreach ($multi->open_handles as $deferred) {
                yield $deferred->get_future();
            }
        })());
        if ($delay->is_complete()) {
            return 0;
        }
        $delay->complete();
        Event_Loop::cancel($id);
        return 1;
    }
    private static function generate_response(Request $request, Amp_Client_State $multi, string $id, array &$info, array &$headers, Deferred_Cancellation $canceller, array &$options, \Closure $on_progress, &$handle, ?Logger_Interface $logger, float &$pause): void
    {
        $request->set_informational_response_handler(static function (Response $response) use ($multi, $id, &$info, &$headers): void {
            self::add_response_headers($response, $info, $headers);
            $multi->handles_activity[$id][] = new Informational_Chunk($response->get_status(), $response->get_headers());
            $multi->open_handles[$id]->complete();
            $multi->open_handles[$id] = new Deferred_Future();
        });
        try {
            /** @var Response $response */
            if (null === $response = self::get_pushed_response($request, $multi, $info, $headers, $canceller, $options, $logger)) {
                $logger?->info(\sprintf('Request: "%s %s"', $info['http_method'], $info['url']));
                $response = self::follow_redirects($request, $multi, $info, $headers, $canceller, $options, $on_progress, $handle, $logger, $pause);
            }
            $options = null;
            $multi->handles_activity[$id][] = new First_Chunk();
            if ('HEAD' === $response->get_request()->get_method() || \in_array($info['http_code'], [204, 304], true)) {
                $multi->handles_activity[$id][] = null;
                $multi->handles_activity[$id][] = null;
                $multi->open_handles[$id]->complete();
                return;
            }
            if ($response->has_header('content-length')) {
                $info['download_content_length'] = (float) $response->get_header('content-length');
            }
            $body = $response->get_body();
            while (true) {
                if (!isset($multi->open_handles[$id])) {
                    return;
                }
                $multi->open_handles[$id]->complete();
                $multi->open_handles[$id] = new Deferred_Future();
                if (0 < $pause) {
                    delay($pause, true, $canceller->get_cancellation());
                }
                if (null === $data = $body->read()) {
                    break;
                }
                $info['size_download'] += \strlen($data);
                $multi->handles_activity[$id][] = $data;
            }
            $multi->handles_activity[$id][] = null;
            $multi->handles_activity[$id][] = null;
        } catch (\Throwable $e) {
            $multi->handles_activity[$id][] = null;
            $multi->handles_activity[$id][] = $e;
        } finally {
            $info['download_content_length'] = $info['size_download'];
        }
    }
    private static function follow_redirects(Request $origin_request, Amp_Client_State $multi, array &$info, array &$headers, Deferred_Cancellation $canceller, array $options, \Closure $on_progress, &$handle, ?Logger_Interface $logger, float &$pause): \Amp\Http\Client\Response
    {
        if (0 < $pause) {
            delay($pause, true, $canceller->get_cancellation());
        }
        $origin_request->set_body(new Amp_Body($options['body'], $info, $on_progress));
        $response = $multi->request($options, $origin_request, $canceller, $info, $on_progress, $handle);
        $previous_url = null;
        while (true) {
            self::add_response_headers($response, $info, $headers);
            $status = $response->get_status();
            if (!\in_array($status, [301, 302, 303, 307, 308], true) || null === $location = $response->get_header('location')) {
                return $response;
            }
            $url_resolver = new class
            {
                use Http_Client_Trait {
                    parseUrl as public;
                    resolveUrl as public;
                }
            };
            try {
                $previous_url ??= $url_resolver::parse_url($info['url']);
                $location = $url_resolver::parse_url($location);
                $location = $url_resolver::resolve_url($location, $previous_url);
                $info['redirect_url'] = implode('', $location);
            } catch (InvalidArgumentException) {
                return $response;
            }
            if (0 >= $options['max_redirects'] || $info['redirect_count'] >= $options['max_redirects']) {
                return $response;
            }
            $logger?->info(\sprintf('Redirecting: "%s %s"', $status, $info['url']));
            try {
                // Discard body of redirects
                $response->get_body()->close();
            } catch (Http_Exception|Stream_Exception) {
                // Ignore streaming errors on previous responses
            }
            ++$info['redirect_count'];
            $info['url'] = $info['redirect_url'];
            $info['redirect_url'] = null;
            $previous_url = $location;
            $request = new Request($info['url'], $info['http_method']);
            $request->set_protocol_versions($origin_request->get_protocol_versions());
            $request->set_tcp_connect_timeout($origin_request->get_tcp_connect_timeout());
            $request->set_tls_handshake_timeout($origin_request->get_tls_handshake_timeout());
            $request->set_transfer_timeout($origin_request->get_transfer_timeout());
            $request->set_body_size_limit(0);
            if (method_exists($request, 'setInactivityTimeout')) {
                $request->set_inactivity_timeout(0);
            }
            if (\in_array($status, [301, 302, 303], true)) {
                $origin_request->remove_header('transfer-encoding');
                $origin_request->remove_header('content-length');
                $origin_request->remove_header('content-type');
                // Do like curl and browsers: turn POST to GET on 301, 302 and 303
                if ('POST' === $response->get_request()->get_method() || 303 === $status) {
                    $info['http_method'] = 'HEAD' === $response->get_request()->get_method() ? 'HEAD' : 'GET';
                    $request->set_method($info['http_method']);
                }
            } else {
                $request->set_body(Amp_Body::rewind($response->get_request()->get_body()));
            }
            foreach ($origin_request->get_header_pairs() as [$name, $value]) {
                $request->add_header($name, $value);
            }
            if ($request->get_uri()->get_authority() !== $origin_request->get_uri()->get_authority()) {
                $request->remove_header('authorization');
                $request->remove_header('cookie');
                $request->remove_header('host');
            }
            if (0 < $pause) {
                delay($pause, true, $canceller->get_cancellation());
            }
            $response = $multi->request($options, $request, $canceller, $info, $on_progress, $handle);
            $info['redirect_time'] = microtime(true) - $info['start_time'];
        }
    }
    private static function add_response_headers(Response $response, array &$info, array &$headers): void
    {
        $info['http_code'] = $response->get_status();
        if ($headers) {
            $info['debug'] .= "< \r\n";
            $headers = [];
        }
        $h = \sprintf('HTTP/%s %s %s', $response->get_protocol_version(), $response->get_status(), $response->get_reason());
        $info['debug'] .= "< {$h}\r\n";
        $info['response_headers'][] = $h;
        foreach ($response->get_header_pairs() as [$name, $value]) {
            $headers[strtolower((string) $name)][] = $value;
            $h = $name . ': ' . $value;
            $info['debug'] .= "< {$h}\r\n";
            $info['response_headers'][] = $h;
        }
        $info['debug'] .= "< \r\n";
    }
    /**
     * Accepts pushed responses only if their headers related to authentication match the request.
     */
    private static function get_pushed_response(Request $request, Amp_Client_State $multi, array &$info, array &$headers, Deferred_Cancellation $canceller, array $options, ?Logger_Interface $logger): ?Response
    {
        if ('' !== $options['body']) {
            return null;
        }
        $authority = $request->get_uri()->get_authority();
        $cancellation = $canceller->get_cancellation();
        foreach ($multi->pushed_responses[$authority] ?? [] as $i => [$pushed_url, $push_deferred, $pushed_request, $pushed_response, $parent_options]) {
            if ($info['url'] !== $pushed_url) {
                continue;
            }
            if ($info['http_method'] !== $pushed_request->get_method()) {
                continue;
            }
            foreach ($parent_options as $k => $v) {
                if ($options[$k] !== $v) {
                    continue 2;
                }
            }
            /** @var DeferredFuture $pushDeferred */
            $id = $cancellation->subscribe(static fn($e) => $push_deferred->error($e));
            try {
                /** @var Future $pushedResponse */
                $response = $pushed_response->await($cancellation);
            } finally {
                $cancellation->unsubscribe($id);
            }
            foreach (['authorization', 'cookie', 'range', 'proxy-authorization'] as $k) {
                if ($response->get_header_array($k) !== $request->get_header_array($k)) {
                    continue 2;
                }
            }
            foreach ($response->get_header_array('vary') as $vary) {
                foreach (preg_split('/\s*+,\s*+/', (string) $vary) as $v) {
                    if ('*' === $v || $pushed_request->get_header_array($v) !== $request->get_header_array($v) && 'accept-encoding' !== strtolower($v)) {
                        $logger?->debug(\sprintf('Skipping pushed response: "%s"', $info['url']));
                        continue 3;
                    }
                }
            }
            $info += ['connect_time' => 0.0, 'pretransfer_time' => 0.0, 'starttransfer_time' => 0.0, 'total_time' => 0.0, 'namelookup_time' => 0.0, 'primary_ip' => '', 'primary_port' => 0, 'start_time' => microtime(true)];
            $push_deferred->complete();
            $logger?->debug(\sprintf('Accepting pushed response: "%s %s"', $info['http_method'], $info['url']));
            self::add_response_headers($response, $info, $headers);
            unset($multi->pushed_responses[$authority][$i]);
            if (!$multi->pushed_responses[$authority]) {
                unset($multi->pushed_responses[$authority]);
            }
            return $response;
        }
        return null;
    }
}