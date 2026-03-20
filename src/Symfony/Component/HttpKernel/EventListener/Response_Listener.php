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
use Symfony\Component\Http_Kernel\Event\Response_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * ResponseListener fixes the Response headers based on the Request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Response_Listener implements Event_Subscriber_Interface
{
    public function __construct(private readonly string $charset, private readonly bool $add_content_language_header = false)
    {
    }
    /**
     * Filters the Response.
     */
    public function on_kernel_response(Response_Event $event): void
    {
        if (!$event->is_main_request()) {
            return;
        }
        $response = $event->get_response();
        if (null === $response->get_charset()) {
            $response->set_charset($this->charset);
        }
        if ($this->add_content_language_header && !$response->is_informational() && !$response->is_empty() && !$response->headers->has('Content-Language')) {
            $response->headers->set('Content-Language', $event->get_request()->get_locale());
        }
        if ($event->get_request()->attributes->get('_vary_by_language')) {
            $response->set_vary('Accept-Language', false);
        }
        $response->prepare($event->get_request());
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::RESPONSE => 'onKernelResponse'];
    }
}