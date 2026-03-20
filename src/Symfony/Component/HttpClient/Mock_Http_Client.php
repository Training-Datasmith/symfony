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

use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Component\Http_Client\Response\Mock_Response;
use Symfony\Component\Http_Client\Response\Response_Stream;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Http_Client\Response_Stream_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * A test-friendly HttpClient that doesn't make actual HTTP requests.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Mock_Http_Client implements Http_Client_Interface, Reset_Interface
{
    use Http_Client_Trait;
    private Response_Interface|\Closure|iterable|null $response_factory;
    private int $requests_count = 0;
    private array $default_options = [];
    /**
     * @param callable|callable[]|ResponseInterface|ResponseInterface[]|iterable|null $responseFactory
     */
    public function __construct(callable|iterable|Response_Interface|null $response_factory = null, ?string $base_uri = 'https://example.com')
    {
        $this->set_response_factory($response_factory);
        $this->default_options['base_uri'] = $base_uri;
    }
    /**
     * @param callable|callable[]|ResponseInterface|ResponseInterface[]|iterable|null $responseFactory
     */
    public function set_response_factory($response_factory): void
    {
        if ($response_factory instanceof Response_Interface) {
            $response_factory = [$response_factory];
        }
        if (!$response_factory instanceof \Iterator && null !== $response_factory && !\is_callable($response_factory)) {
            $response_factory = (static function () use ($response_factory) {
                yield from $response_factory;
            })();
        }
        $this->response_factory = !\is_callable($response_factory) ? $response_factory : $response_factory(...);
    }
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        [$url, $options] = $this->prepare_request($method, $url, $options, $this->default_options, true);
        $url = implode('', $url);
        if (null === $this->response_factory) {
            $response = new Mock_Response();
        } elseif (\is_callable($this->response_factory)) {
            $response = ($this->response_factory)($method, $url, $options);
        } elseif (!$this->response_factory->valid()) {
            throw new Transport_Exception($this->requests_count ? 'No more response left in the response factory iterator passed to MockHttpClient: the number of requests exceeds the number of responses.' : 'The response factory iterator passed to MockHttpClient is empty.');
        } else {
            $response_factory = $this->response_factory->current();
            $response = \is_callable($response_factory) ? $response_factory($method, $url, $options) : $response_factory;
            $this->response_factory->next();
        }
        ++$this->requests_count;
        if (!$response instanceof Response_Interface) {
            throw new Transport_Exception(\sprintf('The response factory passed to MockHttpClient must return/yield an instance of ResponseInterface, "%s" given.', get_debug_type($response)));
        }
        return Mock_Response::from_request($method, $url, $options, $response);
    }
    public function stream(Response_Interface|iterable $responses, ?float $timeout = null): Response_Stream_Interface
    {
        if ($responses instanceof Response_Interface) {
            $responses = [$responses];
        }
        return new Response_Stream(Mock_Response::stream($responses, $timeout));
    }
    public function get_requests_count(): int
    {
        return $this->requests_count;
    }
    public function with_options(array $options): static
    {
        $clone = clone $this;
        $clone->default_options = self::merge_default_options($options, $this->default_options, true);
        return $clone;
    }
    public function reset(): void
    {
        $this->requests_count = 0;
    }
}