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
namespace Symfony\Bridge\Twig\Error_Renderer;

use Symfony\Component\Error_Handler\Error_Renderer\Error_Renderer_Interface;
use Symfony\Component\Error_Handler\Error_Renderer\Html_Error_Renderer;
use Symfony\Component\Error_Handler\Exception\Flatten_Exception;
use Symfony\Component\Http_Foundation\Request_Stack;
use Twig\Environment;
/**
 * Provides the ability to render custom Twig-based HTML error pages
 * in non-debug mode, otherwise falls back to HtmlErrorRenderer.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class Twig_Error_Renderer implements Error_Renderer_Interface
{
    private readonly \Closure|bool $debug;
    /**
     * @param bool|callable $debug The debugging mode as a boolean or a callable that should return it
     */
    public function __construct(private readonly Environment $twig, private readonly ?Html_Error_Renderer $fallback_error_renderer = new Html_Error_Renderer(), bool|callable $debug = false)
    {
        $this->debug = \is_bool($debug) ? $debug : $debug(...);
    }
    public function render(\Throwable $exception): Flatten_Exception
    {
        $flatten_exception = Flatten_Exception::create_from_throwable($exception);
        $debug = \is_bool($this->debug) ? $this->debug : ($this->debug)($flatten_exception);
        if ($debug || !$template = $this->find_template($flatten_exception->get_status_code())) {
            return $this->fallback_error_renderer->render($exception);
        }
        return $flatten_exception->set_as_string($this->twig->render($template, ['exception' => $flatten_exception, 'status_code' => $flatten_exception->get_status_code(), 'status_text' => $flatten_exception->get_status_text()]));
    }
    public static function is_debug(Request_Stack $request_stack, bool $debug): \Closure
    {
        return static function () use ($request_stack, $debug): bool {
            if (!$request = $request_stack->get_current_request()) {
                return $debug;
            }
            return $debug && $request->attributes->get_boolean('showException', true);
        };
    }
    private function find_template(int $status_code): ?string
    {
        $template = \sprintf('@Twig/Exception/error%s.html.twig', $status_code);
        if ($this->twig->get_loader()->exists($template)) {
            return $template;
        }
        $template = '@Twig/Exception/error.html.twig';
        if ($this->twig->get_loader()->exists($template)) {
            return $template;
        }
        return null;
    }
}