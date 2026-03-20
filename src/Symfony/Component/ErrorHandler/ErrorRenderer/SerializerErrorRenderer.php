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
namespace Symfony\Component\Error_Handler\Error_Renderer;

use Symfony\Component\Error_Handler\Exception\Flatten_Exception;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Serializer\Exception\Not_Encodable_Value_Exception;
use Symfony\Component\Serializer\Serializer_Interface;
/**
 * Formats an exception using Serializer for rendering.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Serializer_Error_Renderer implements Error_Renderer_Interface
{
    private readonly string|\Closure $format;
    private readonly bool|\Closure $debug;
    /**
     * @param string|callable(FlattenException): string $format The format as a string or a callable that should return it
     *                                                          formats not supported by Request::getMimeTypes() should be given as mime types
     * @param bool|callable                             $debug  The debugging mode as a boolean or a callable that should return it
     */
    public function __construct(private readonly Serializer_Interface $serializer, string|callable $format, private readonly ?Error_Renderer_Interface $fallback_error_renderer = new Html_Error_Renderer(), bool|callable $debug = false)
    {
        $this->format = \is_string($format) ? $format : $format(...);
        $this->debug = \is_bool($debug) ? $debug : $debug(...);
    }
    public function render(\Throwable $exception): Flatten_Exception
    {
        $headers = ['Vary' => 'Accept'];
        $debug = \is_bool($this->debug) ? $this->debug : ($this->debug)($exception);
        if ($debug) {
            $headers['X-Debug-Exception'] = rawurlencode(substr($exception->get_message(), 0, 2000));
            $headers['X-Debug-Exception-File'] = rawurlencode($exception->get_file()) . ':' . $exception->get_line();
        }
        $flatten_exception = Flatten_Exception::create_from_throwable($exception, null, $headers);
        try {
            $format = \is_string($this->format) ? $this->format : ($this->format)($flatten_exception);
            $headers['Content-Type'] = Request::get_mime_types($format)[0] ?? $format;
            $flatten_exception->set_as_string($this->serializer->serialize($flatten_exception, $format, ['exception' => $exception, 'debug' => $debug]));
        } catch (Not_Encodable_Value_Exception) {
            $flatten_exception = $this->fallback_error_renderer->render($exception);
        }
        return $flatten_exception->set_headers($flatten_exception->get_headers() + $headers);
    }
    public static function get_preferred_format(Request_Stack $request_stack): \Closure
    {
        return static function () use ($request_stack): ?string {
            if (!$request = $request_stack->get_current_request()) {
                throw new Not_Encodable_Value_Exception();
            }
            return $request->get_preferred_format();
        };
    }
}