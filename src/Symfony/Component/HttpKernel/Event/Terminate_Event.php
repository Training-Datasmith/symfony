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
namespace Symfony\Component\Http_Kernel\Event;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Http_Kernel_Interface;
/**
 * Allows to execute logic after a response was sent.
 *
 * Since it's only triggered on main requests, the `getRequestType()` method
 * will always return the value of `HttpKernelInterface::MAIN_REQUEST`.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
final class Terminate_Event extends Kernel_Event
{
    public function __construct(Http_Kernel_Interface $kernel, Request $request, private readonly Response $response)
    {
        parent::__construct($kernel, $request, Http_Kernel_Interface::MAIN_REQUEST);
    }
    public function get_response(): Response
    {
        return $this->response;
    }
}