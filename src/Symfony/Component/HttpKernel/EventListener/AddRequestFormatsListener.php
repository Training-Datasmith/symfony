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
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * Adds configured formats to each request.
 *
 * @author Gildas Quemener <gildas.quemener@gmail.com>
 *
 * @final
 */
class Add_Request_Formats_Listener implements Event_Subscriber_Interface
{
    public function __construct(private readonly array $formats)
    {
    }
    /**
     * Adds request formats.
     */
    public function on_kernel_request(Request_Event $event): void
    {
        $request = $event->get_request();
        foreach ($this->formats as $format => $mime_types) {
            $request->set_format($format, $mime_types);
        }
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::REQUEST => ['onKernelRequest', 100]];
    }
}