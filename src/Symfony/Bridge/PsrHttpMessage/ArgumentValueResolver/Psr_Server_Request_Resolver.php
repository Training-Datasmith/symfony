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
namespace Symfony\Bridge\Psr_Http_Message\Argument_Value_Resolver;

use Psr\Http\Message\Message_Interface;
use Psr\Http\Message\Request_Interface;
use Psr\Http\Message\Server_Request_Interface;
use Symfony\Bridge\Psr_Http_Message\Http_Message_Factory_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
/**
 * Injects the RequestInterface, MessageInterface or ServerRequestInterface when requested.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
final readonly class Psr_Server_Request_Resolver implements Value_Resolver_Interface
{
    private const SUPPORTED_TYPES = [Server_Request_Interface::class => true, Request_Interface::class => true, Message_Interface::class => true];
    public function __construct(private Http_Message_Factory_Interface $http_message_factory)
    {
    }
    public function resolve(Request $request, Argument_Metadata $argument): \Traversable
    {
        if (!isset(self::SUPPORTED_TYPES[$argument->get_type()])) {
            return;
        }
        yield $this->http_message_factory->create_request($request);
    }
}