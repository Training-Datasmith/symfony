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

use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Http_Client\Response_Stream_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Eases with writing decorators.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait Decorator_Trait
{
    private Http_Client_Interface $client;
    public function __construct(?Http_Client_Interface $client = null)
    {
        $this->client = $client ?? Http_Client::create();
    }
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        return $this->client->request($method, $url, $options);
    }
    public function stream(Response_Interface|iterable $responses, ?float $timeout = null): Response_Stream_Interface
    {
        return $this->client->stream($responses, $timeout);
    }
    public function with_options(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->with_options($options);
        return $clone;
    }
    public function reset(): void
    {
        if ($this->client instanceof Reset_Interface) {
            $this->client->reset();
        }
    }
}