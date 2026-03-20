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
namespace Symfony\Component\Http_Kernel\Event_Listener;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Uri_Signer;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Exception\Access_Denied_Http_Exception;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * Handles content fragments represented by special URIs.
 *
 * All URL paths starting with /_fragment are handled as
 * content fragments by this listener.
 *
 * Throws an AccessDeniedHttpException exception if the request
 * is not signed or if it is not an internal sub-request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Fragment_Listener implements Event_Subscriber_Interface
{
    /**
     * @param string $fragmentPath The path that triggers this listener
     */
    public function __construct(private readonly Uri_Signer $signer, private readonly string $fragment_path = '/_fragment')
    {
    }
    /**
     * Fixes request attributes when the path is '/_fragment'.
     *
     * @throws AccessDeniedHttpException if the request does not come from a trusted IP
     */
    public function on_kernel_request(Request_Event $event): void
    {
        $request = $event->get_request();
        if ($this->fragment_path !== rawurldecode($request->get_path_info())) {
            return;
        }
        if ($request->attributes->has('_controller')) {
            // Is a sub-request: no need to parse _path but it should still be removed from query parameters as below.
            $request->query->remove('_path');
            return;
        }
        if ($event->is_main_request()) {
            $this->validate_request($request);
        }
        parse_str($request->query->get('_path', ''), $attributes);
        $attributes['_check_controller_is_allowed'] = true;
        $request->attributes->add($attributes);
        $request->attributes->set('_route_params', array_replace($request->attributes->get('_route_params', []), $attributes));
        $request->query->remove('_path');
    }
    protected function validate_request(Request $request): void
    {
        // is the Request safe?
        if (!$request->is_method_safe()) {
            throw new Access_Denied_Http_Exception();
        }
        // is the Request signed?
        if ($this->signer->check_request($request)) {
            return;
        }
        throw new Access_Denied_Http_Exception();
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::REQUEST => [['onKernelRequest', 48]]];
    }
}