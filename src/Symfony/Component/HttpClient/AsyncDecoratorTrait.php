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

use Symfony\Component\Http_Client\Response\Async_Response;
use Symfony\Component\Http_Client\Response\Response_Stream;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Http_Client\Response_Stream_Interface;
/**
 * Eases with processing responses while streaming them.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait Async_Decorator_Trait
{
    use Decorator_Trait;
    /**
     * @return AsyncResponse
     */
    abstract public function request(string $method, string $url, array $options = []): Response_Interface;
    public function stream(Response_Interface|iterable $responses, ?float $timeout = null): Response_Stream_Interface
    {
        if ($responses instanceof Async_Response) {
            $responses = [$responses];
        }
        return new Response_Stream(Async_Response::stream($responses, $timeout, static::class));
    }
}