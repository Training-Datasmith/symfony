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

use Guzzle_Http\Promise\Promise as GuzzlePromise;
use Guzzle_Http\Promise\Rejected_Promise;
use Guzzle_Http\Promise\Utils;
use Http\Client\Exception\Network_Exception;
use Http\Client\Exception\Request_Exception;
use Http\Client\Http_Async_Client;
use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr17factory_Discovery;
use Nyholm\Psr7\Factory\Psr17Factory as NyholmPsr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Uri;
use Psr\Http\Client\Client_Interface;
use Psr\Http\Message\Request_Factory_Interface;
use Psr\Http\Message\Request_Interface;
use Psr\Http\Message\Response_Factory_Interface;
use Psr\Http\Message\Response_Interface as Psr7ResponseInterface;
use Psr\Http\Message\Stream_Factory_Interface;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uri_Factory_Interface;
use Psr\Http\Message\Uri_Interface;
use Symfony\Component\Http_Client\Internal\Httplug_Wait_Loop;
use Symfony\Component\Http_Client\Response\Httplug_Promise;
use Symfony\Contracts\Http_Client\Exception\Transport_Exception_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Service\Reset_Interface;
if (!interface_exists(Http_Async_Client::class)) {
    throw new \LogicException('You cannot use "Symfony\Component\HttpClient\HttplugClient" as the "php-http/httplug" package is not installed. Try running "composer require php-http/discovery php-http/async-client-implementation:*".');
}
if (!interface_exists(Request_Factory_Interface::class)) {
    throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\HttplugClient" as the "psr/http-factory" package is not installed. Try running "composer require php-http/discovery psr/http-factory-implementation:*".');
}
/**
 * An adapter to turn a Symfony HttpClientInterface into an Httplug client.
 *
 * In comparison to Psr18Client, this client supports asynchronous requests.
 *
 * Run "composer require php-http/discovery php-http/async-client-implementation:*"
 * to get the required dependencies.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Httplug_Client implements Client_Interface, Http_Async_Client, Request_Factory_Interface, Stream_Factory_Interface, Uri_Factory_Interface, Reset_Interface
{
    private Http_Client_Interface $client;
    private Response_Factory_Interface $response_factory;
    private Stream_Factory_Interface $stream_factory;
    private bool $auto_upgrade_http_version = true;
    /**
     * @var \SplObjectStorage<ResponseInterface, array{RequestInterface, Promise}>|null
     */
    private ?\Spl_Object_Storage $promise_pool;
    private Httplug_Wait_Loop $wait_loop;
    public function __construct(?Http_Client_Interface $client = null, ?Response_Factory_Interface $response_factory = null, ?Stream_Factory_Interface $stream_factory = null)
    {
        $this->client = $client ?? Http_Client::create();
        $stream_factory ??= $response_factory instanceof Stream_Factory_Interface ? $response_factory : null;
        $this->promise_pool = class_exists(Utils::class) ? new \Spl_Object_Storage() : null;
        if (null === $response_factory || null === $stream_factory) {
            if (class_exists(Psr17Factory::class)) {
                $psr17Factory = new Psr17Factory();
            } elseif (class_exists(Nyholm_Psr17factory::class)) {
                $psr17Factory = new Nyholm_Psr17factory();
            } else {
                throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\HttplugClient" as no PSR-17 factories have been provided. Try running "composer require php-http/discovery psr/http-factory-implementation:*".');
            }
            $response_factory ??= $psr17Factory;
            $stream_factory ??= $psr17Factory;
        }
        $this->response_factory = $response_factory;
        $this->stream_factory = $stream_factory;
        $this->wait_loop = new Httplug_Wait_Loop($this->client, $this->promise_pool, $this->response_factory, $this->stream_factory);
    }
    public function with_options(array $options): static
    {
        $clone = clone $this;
        if (\array_key_exists('auto_upgrade_http_version', $options)) {
            $clone->auto_upgrade_http_version = $options['auto_upgrade_http_version'];
            unset($options['auto_upgrade_http_version']);
        }
        $clone->client = $clone->client->with_options($options);
        return $clone;
    }
    public function send_request(Request_Interface $request): Psr7response_Interface
    {
        try {
            return Httplug_Wait_Loop::create_psr7response($this->response_factory, $this->stream_factory, $this->client, $this->send_psr7request($request), true);
        } catch (Transport_Exception_Interface $e) {
            throw new Network_Exception($e->get_message(), $request, $e);
        }
    }
    public function send_async_request(Request_Interface $request): Httplug_Promise
    {
        if (!$promise_pool = $this->promise_pool) {
            throw new \LogicException(\sprintf('You cannot use "%s()" as the "guzzlehttp/promises" package is not installed. Try running "composer require guzzlehttp/promises".', __METHOD__));
        }
        try {
            $response = $this->send_psr7request($request, true);
        } catch (Network_Exception $e) {
            return new Httplug_Promise(new Rejected_Promise($e));
        }
        $wait_loop = $this->wait_loop;
        $promise = new Guzzle_Promise(static function () use ($response, $wait_loop): void {
            $wait_loop->wait($response);
        }, static function () use ($response, $promise_pool): void {
            $response->cancel();
            unset($promise_pool[$response]);
        });
        $promise_pool[$response] = [$request, $promise];
        return new Httplug_Promise($promise);
    }
    /**
     * Resolves pending promises that complete before the timeouts are reached.
     *
     * When $maxDuration is null and $idleTimeout is reached, promises are rejected.
     *
     * @return int The number of remaining pending promises
     */
    public function wait(?float $max_duration = null, ?float $idle_timeout = null): int
    {
        return $this->wait_loop->wait(null, $max_duration, $idle_timeout);
    }
    /**
     * @param UriInterface|string $uri
     */
    public function create_request(string $method, $uri = ''): Request_Interface
    {
        if ($this->response_factory instanceof Request_Factory_Interface) {
            $request = $this->response_factory->create_request($method, $uri);
        } elseif (class_exists(Psr17factory_Discovery::class)) {
            $request = Psr17factory_Discovery::find_request_factory()->create_request($method, $uri);
        } elseif (class_exists(Request::class)) {
            $request = new Request($method, $uri);
        } else {
            throw new \LogicException(\sprintf('You cannot use "%s()" as no PSR-17 factories have been found. Try running "composer require php-http/discovery psr/http-factory-implementation:*".', __METHOD__));
        }
        return $request;
    }
    public function create_stream(string $content = ''): Stream_Interface
    {
        return $this->stream_factory->create_stream($content);
    }
    public function create_stream_from_file(string $filename, string $mode = 'r'): Stream_Interface
    {
        return $this->stream_factory->create_stream_from_file($filename, $mode);
    }
    public function create_stream_from_resource($resource): Stream_Interface
    {
        return $this->stream_factory->create_stream_from_resource($resource);
    }
    public function create_uri(string $uri = ''): Uri_Interface
    {
        if ($this->response_factory instanceof Uri_Factory_Interface) {
            return $this->response_factory->create_uri($uri);
        }
        if (class_exists(Psr17factory_Discovery::class)) {
            return Psr17factory_Discovery::find_uri_factory()->create_uri($uri);
        }
        if (class_exists(Uri::class)) {
            return new Uri($uri);
        }
        throw new \LogicException(\sprintf('You cannot use "%s()" as no PSR-17 factories have been found. Try running "composer require php-http/discovery psr/http-factory-implementation:*".', __METHOD__));
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
        $this->wait();
    }
    public function reset(): void
    {
        if ($this->client instanceof Reset_Interface) {
            $this->client->reset();
        }
    }
    private function send_psr7request(Request_Interface $request, ?bool $buffer = null): Response_Interface
    {
        try {
            $body = $request->get_body();
            $headers = $request->get_headers();
            $size = $request->get_header('content-length')[0] ?? -1;
            if (0 > $size && 0 < $size = $body->get_size() ?? -1) {
                $headers['Content-Length'] = [$size];
            }
            if (0 === $size) {
                $body = '';
            } elseif (0 < $size && $size < 1 << 21) {
                if ($body->is_seekable()) {
                    try {
                        $body->seek(0);
                    } catch (\RuntimeException) {
                        // ignore
                    }
                }
                $body = $body->get_contents();
            } else {
                $body = static function (int $size) use ($body) {
                    if ($body->is_seekable()) {
                        try {
                            $body->seek(0);
                        } catch (\RuntimeException) {
                            // ignore
                        }
                    }
                    while (!$body->eof()) {
                        yield $body->read($size);
                    }
                };
            }
            $options = ['headers' => $headers, 'body' => $body, 'buffer' => $buffer];
            if (!$this->auto_upgrade_http_version || '1.0' === $request->get_protocol_version()) {
                $options['http_version'] = $request->get_protocol_version();
            }
            return $this->client->request($request->get_method(), (string) $request->get_uri(), $options);
        } catch (\InvalidArgumentException $e) {
            throw new Request_Exception($e->get_message(), $request, $e);
        } catch (Transport_Exception_Interface $e) {
            throw new Network_Exception($e->get_message(), $request, $e);
        }
    }
}