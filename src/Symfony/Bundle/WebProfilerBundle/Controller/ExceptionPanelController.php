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
namespace Symfony\Bundle\Web_Profiler_Bundle\Controller;

use Symfony\Component\Error_Handler\Error_Renderer\Html_Error_Renderer;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
use Symfony\Component\Http_Kernel\Profiler\Profiler;
/**
 * Renders the exception panel.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
class Exception_Panel_Controller
{
    public function __construct(private readonly Html_Error_Renderer $error_renderer, private readonly ?Profiler $profiler = null)
    {
    }
    /**
     * Renders the exception panel stacktrace for the given token.
     */
    public function body(string $token): Response
    {
        if (null === $this->profiler) {
            throw new Not_Found_Http_Exception('The profiler must be enabled.');
        }
        $exception = $this->profiler->load_profile($token)->get_collector('exception')->get_exception();
        return new Response($this->error_renderer->get_body($exception), 200, ['Content-Type' => 'text/html']);
    }
    /**
     * Renders the exception panel stylesheet.
     */
    public function stylesheet(): Response
    {
        return new Response($this->error_renderer->get_stylesheet(), 200, ['Content-Type' => 'text/css']);
    }
}