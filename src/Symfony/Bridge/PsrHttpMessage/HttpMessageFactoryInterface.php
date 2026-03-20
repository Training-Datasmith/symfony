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
namespace Symfony\Bridge\Psr_Http_Message;

use Psr\Http\Message\Response_Interface;
use Psr\Http\Message\Server_Request_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
/**
 * Creates PSR HTTP Request and Response instances from Symfony ones.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface Http_Message_Factory_Interface
{
    /**
     * Creates a PSR-7 Request instance from a Symfony one.
     */
    public function create_request(Request $symfony_request): Server_Request_Interface;
    /**
     * Creates a PSR-7 Response instance from a Symfony one.
     */
    public function create_response(Response $symfony_response): Response_Interface;
}