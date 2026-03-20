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

use Symfony\Contracts\Http_Client\Exception\Client_Exception_Interface;
use Symfony\Contracts\Http_Client\Exception\Redirection_Exception_Interface;
use Symfony\Contracts\Http_Client\Exception\Server_Exception_Interface;
use Symfony\Contracts\Http_Client\Exception\Transport_Exception_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface Streamable_Interface
{
    /**
     * Casts the response to a PHP stream resource.
     *
     * @return resource
     *
     * @throws TransportExceptionInterface   When a network error occurs
     * @throws RedirectionExceptionInterface On a 3xx when $throw is true and the "max_redirects" option has been reached
     * @throws ClientExceptionInterface      On a 4xx when $throw is true
     * @throws ServerExceptionInterface      On a 5xx when $throw is true
     */
    public function to_stream(bool $throw = true);
}