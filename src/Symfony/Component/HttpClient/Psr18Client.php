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

use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr17factory_Discovery;
use Nyholm\Psr7\Factory\Psr17Factory as NyholmPsr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Uri;
use Psr\Http\Client\Client_Interface;
use Psr\Http\Client\Network_Exception_Interface;
use Psr\Http\Client\Request_Exception_Interface;
use Psr\Http\Message\Request_Factory_Interface;
use Psr\Http\Message\Request_Interface;
use Psr\Http\Message\Response_Factory_Interface;
use Psr\Http\Message\Response_Interface;
use Psr\Http\Message\Stream_Factory_Interface;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uri_Factory_Interface;
use Psr\Http\Message\Uri_Interface;
use Symfony\Component\Http_Client\Internal\Httplug_Wait_Loop;
use Symfony\Contracts\Http_Client\Exception\Transport_Exception_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Service\Reset_Interface;
if (!interface_exists(Client_Interface::class)) {
    throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\Psr18Client" as the "psr/http-client" package is not installed. Try running "composer require php-http/discovery psr/http-client-implementation:*".');
}
if (!interface_exists(Request_Factory_Interface::class)) {
    throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\Psr18Client" as the "psr/http-factory" package is not installed. Try running "composer require php-http/discovery psr/http-factory-implementation:*".');
}
/**
 * An adapter to turn a Symfony HttpClientInterface into a PSR-18 ClientInterface.
 *
 * Run "composer require php-http/discovery psr/http-client-implementation:*"
 * to get the required dependencies.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Psr18Client implements Client_Interface, Request_Factory_Interface, Stream_Factory_Interface, Uri_Factory_Interface, Reset_Interface
{
    private Http_Client_Interface $client;
    private Response_Factory_Interface $response_factory;
    private Stream_Factory_Interface $stream_factory;
    private bool $auto_upgrade_http_version = true;
    public function __construct(?Http_Client_Interface $client = null, ?Response_Factory_Interface $response_factory = null, ?Stream_Factory_Interface $stream_factory = null)
    {
        $this->client = $client ?? Http_Client::create();
        $stream_factory ??= $response_factory instanceof Stream_Factory_Interface ? $response_factory : null;
        if (null === $response_factory || null === $stream_factory) {
            if (class_exists(Psr17Factory::class)) {
                $psr17Factory = new Psr17Factory();
            } elseif (class_exists(Nyholm_Psr17factory::class)) {
                $psr17Factory = new Nyholm_Psr17factory();
            } else {
                throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\Psr18Client" as no PSR-17 factories have been provided. Try running "composer require php-http/discovery psr/http-factory-implementation:*".');
            }
            $response_factory ??= $psr17Factory;
            $stream_factory ??= $psr17Factory;
        }
        $this->response_factory = $response_factory;
        $this->stream_factory = $stream_factory;
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
    public function send_request(Request_Interface $request): Response_Interface
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
            $options = ['headers' => $headers, 'body' => $body];
            if (!$this->auto_upgrade_http_version || '1.0' === $request->get_protocol_version()) {
                $options['http_version'] = $request->get_protocol_version();
            }
            $response = $this->client->request($request->get_method(), (string) $request->get_uri(), $options);
            return Httplug_Wait_Loop::create_psr7response($this->response_factory, $this->stream_factory, $this->client, $response, false);
        } catch (Transport_Exception_Interface $e) {
            if ($e instanceof \InvalidArgumentException) {
                throw new Psr18request_Exception($e, $request);
            }
            throw new Psr18network_Exception($e, $request);
        }
    }
    public function create_request(string $method, $uri): Request_Interface
    {
        if ($this->response_factory instanceof Request_Factory_Interface) {
            return $this->response_factory->create_request($method, $uri);
        }
        if (class_exists(Psr17factory_Discovery::class)) {
            return Psr17factory_Discovery::find_request_factory()->create_request($method, $uri);
        }
        if (class_exists(Request::class)) {
            return new Request($method, $uri);
        }
        throw new \LogicException(\sprintf('You cannot use "%s()" as no PSR-17 factories have been found. Try running "composer require php-http/discovery psr/http-factory-implementation:*".', __METHOD__));
    }
    public function create_stream(string $content = ''): Stream_Interface
    {
        $stream = $this->stream_factory->create_stream($content);
        if ($stream->is_seekable()) {
            try {
                $stream->seek(0);
            } catch (\RuntimeException) {
                // ignore
            }
        }
        return $stream;
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
            return Psr17factory_Discovery::find_url_factory()->create_uri($uri);
        }
        if (class_exists(Uri::class)) {
            return new Uri($uri);
        }
        throw new \LogicException(\sprintf('You cannot use "%s()" as no PSR-17 factories have been found. Try running "composer require php-http/discovery psr/http-factory-implementation:*".', __METHOD__));
    }
    public function reset(): void
    {
        if ($this->client instanceof Reset_Interface) {
            $this->client->reset();
        }
    }
}
/**
 * @internal
 */
class Psr18network_Exception extends \RuntimeException implements Network_Exception_Interface
{
    public function __construct(Transport_Exception_Interface $e, private readonly Request_Interface $request)
    {
        parent::__construct($e->get_message(), 0, $e);
    }
    public function get_request(): Request_Interface
    {
        return $this->request;
    }
}
/**
 * @internal
 */
class Psr18request_Exception extends \InvalidArgumentException implements Request_Exception_Interface
{
    public function __construct(Transport_Exception_Interface $e, private readonly Request_Interface $request)
    {
        parent::__construct($e->get_message(), 0, $e);
    }
    public function get_request(): Request_Interface
    {
        return $this->request;
    }
}