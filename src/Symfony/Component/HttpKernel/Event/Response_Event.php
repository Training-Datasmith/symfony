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
 * Allows to filter a Response object.
 *
 * You can call getResponse() to retrieve the current response. With
 * setResponse() you can set a new response that will be returned to the
 * browser.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class Response_Event extends Kernel_Event
{
    public function __construct(Http_Kernel_Interface $kernel, Request $request, int $request_type, private Response $response, public readonly ?Controller_Arguments_Metadata $controller_metadata = null)
    {
        parent::__construct($kernel, $request, $request_type);
    }
    public function get_response(): Response
    {
        return $this->response;
    }
    public function set_response(Response $response): void
    {
        $this->response = $response;
    }
}