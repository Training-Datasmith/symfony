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
 * Creates Symfony Request and Response instances from PSR-7 ones.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface Http_Foundation_Factory_Interface
{
    /**
     * Creates a Symfony Request instance from a PSR-7 one.
     */
    public function create_request(Server_Request_Interface $psr_request, bool $streamed = false): Request;
    /**
     * Creates a Symfony Response instance from a PSR-7 one.
     */
    public function create_response(Response_Interface $psr_response, bool $streamed = false): Response;
}