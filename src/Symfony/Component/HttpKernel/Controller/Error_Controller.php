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
namespace Symfony\Component\Http_Kernel\Controller;

use Symfony\Component\Error_Handler\Error_Renderer\Error_Renderer_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Exception\Http_Exception;
use Symfony\Component\Http_Kernel\Http_Kernel_Interface;
/**
 * Renders error or exception pages from a given FlattenException.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class Error_Controller
{
    public function __construct(private readonly Http_Kernel_Interface $kernel, private readonly string|object|array|null $controller, private readonly Error_Renderer_Interface $error_renderer)
    {
    }
    public function __invoke(\Throwable $exception): Response
    {
        $exception = $this->error_renderer->render($exception);
        return new Response($exception->get_as_string(), $exception->get_status_code(), $exception->get_headers());
    }
    public function preview(Request $request, int $code): Response
    {
        /*
         * This Request mimics the parameters set by
         * \Symfony\Component\HttpKernel\EventListener\ErrorListener::duplicateRequest, with
         * the additional "showException" flag.
         */
        $sub_request = $request->duplicate(null, null, ['_controller' => $this->controller, 'exception' => new Http_Exception($code, 'This is a sample exception.'), 'logger' => null, 'showException' => false]);
        return $this->kernel->handle($sub_request, Http_Kernel_Interface::SUB_REQUEST);
    }
}