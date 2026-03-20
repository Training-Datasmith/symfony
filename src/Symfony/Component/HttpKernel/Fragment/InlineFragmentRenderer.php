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
namespace Symfony\Component\Http_Kernel\Fragment;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Controller\Controller_Reference;
use Symfony\Component\Http_Kernel\Event\Exception_Event;
use Symfony\Component\Http_Kernel\Http_Cache\Sub_Request_Handler;
use Symfony\Component\Http_Kernel\Http_Kernel_Interface;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
/**
 * Implements the inline rendering strategy where the Request is rendered by the current HTTP kernel.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Inline_Fragment_Renderer extends Routable_Fragment_Renderer
{
    public function __construct(private readonly Http_Kernel_Interface $kernel, private readonly ?Event_Dispatcher_Interface $dispatcher = null)
    {
    }
    /**
     * Additional available options:
     *
     *  * alt: an alternative URI to render in case of an error
     */
    public function render(string|Controller_Reference $uri, Request $request, array $options = []): Response
    {
        $reference = null;
        if ($uri instanceof Controller_Reference) {
            $reference = $uri;
            // Remove attributes from the generated URI because if not, the Symfony
            // routing system will use them to populate the Request attributes. We don't
            // want that as we want to preserve objects (so we manually set Request attributes
            // below instead)
            $attributes = $reference->attributes;
            $reference->attributes = [];
            // The request format and locale might have been overridden by the user
            foreach (['_format', '_locale'] as $key) {
                if (isset($attributes[$key])) {
                    $reference->attributes[$key] = $attributes[$key];
                }
            }
            $uri = $this->generate_fragment_uri($uri, $request, false, false);
            $reference->attributes = array_merge($attributes, $reference->attributes);
        }
        $sub_request = $this->create_sub_request($uri, $request);
        // override Request attributes as they can be objects (which are not supported by the generated URI)
        if (null !== $reference) {
            $sub_request->attributes->add($reference->attributes);
        }
        $level = ob_get_level();
        try {
            return Sub_Request_Handler::handle($this->kernel, $sub_request, Http_Kernel_Interface::SUB_REQUEST, false);
        } catch (\Exception $e) {
            // we dispatch the exception event to trigger the logging
            // the response that comes back is ignored
            if (isset($options['ignore_errors']) && $options['ignore_errors'] && $this->dispatcher) {
                $event = new Exception_Event($this->kernel, $request, Http_Kernel_Interface::SUB_REQUEST, $e);
                $this->dispatcher->dispatch($event, Kernel_Events::EXCEPTION);
            }
            // let's clean up the output buffers that were created by the sub-request
            Response::close_output_buffers($level, false);
            if (isset($options['alt'])) {
                $alt = $options['alt'];
                unset($options['alt']);
                return $this->render($alt, $request, $options);
            }
            if (!isset($options['ignore_errors']) || !$options['ignore_errors']) {
                throw $e;
            }
            return new Response();
        }
    }
    protected function create_sub_request(string $uri, Request $request): Request
    {
        $cookies = $request->cookies->all();
        $server = $request->server->all();
        unset($server['HTTP_IF_MODIFIED_SINCE']);
        unset($server['HTTP_IF_NONE_MATCH']);
        $sub_request = Request::create($uri, 'get', [], $cookies, [], $server);
        if ($request->headers->has('Surrogate-Capability')) {
            $sub_request->headers->set('Surrogate-Capability', $request->headers->get('Surrogate-Capability'));
        }
        static $set_session;
        $set_session ??= \Closure::bind(static function ($sub_request, $request): void {
            $sub_request->session = $request->session;
        }, null, Request::class);
        $set_session($sub_request, $request);
        if ($request->attributes->has('_format')) {
            $sub_request->attributes->set('_format', $request->attributes->get('_format'));
        }
        if ($request->get_default_locale() !== $request->get_locale()) {
            $sub_request->set_locale($request->get_locale());
        }
        if ($request->attributes->has('_stateless')) {
            $sub_request->attributes->set('_stateless', $request->attributes->get('_stateless'));
        }
        if ($request->attributes->has('_check_controller_is_allowed')) {
            $sub_request->attributes->set('_check_controller_is_allowed', $request->attributes->get('_check_controller_is_allowed'));
        }
        return $sub_request;
    }
    public function get_name(): string
    {
        return 'inline';
    }
}