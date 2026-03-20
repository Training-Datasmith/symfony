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
use Symfony\Component\Http_Kernel\Http_Cache\Http_Cache;
use Symfony\Component\Http_Kernel\Http_Cache\Surrogate_Interface;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * SurrogateListener adds a Surrogate-Control HTTP header when the Response needs to be parsed for Surrogates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Surrogate_Listener implements Event_Subscriber_Interface
{
    public function __construct(private readonly ?Surrogate_Interface $surrogate = null)
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
        $kernel = $event->get_kernel();
        $surrogate = $this->surrogate;
        if ($kernel instanceof Http_Cache) {
            $surrogate = $kernel->get_surrogate();
            if (null !== $this->surrogate && $this->surrogate->get_name() !== $surrogate->get_name()) {
                $surrogate = $this->surrogate;
            }
        }
        if (null === $surrogate) {
            return;
        }
        $surrogate->add_surrogate_control($event->get_response());
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::RESPONSE => 'onKernelResponse'];
    }
}