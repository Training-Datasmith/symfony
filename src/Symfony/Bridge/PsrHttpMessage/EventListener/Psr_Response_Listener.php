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
namespace Symfony\Bridge\Psr_Http_Message\Event_Listener;

use Psr\Http\Message\Response_Interface;
use Symfony\Bridge\Psr_Http_Message\Factory\Http_Foundation_Factory;
use Symfony\Bridge\Psr_Http_Message\Http_Foundation_Factory_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Kernel\Event\View_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * Converts PSR-7 Response to HttpFoundation Response using the bridge.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
final readonly class Psr_Response_Listener implements Event_Subscriber_Interface
{
    public function __construct(private ?Http_Foundation_Factory_Interface $http_foundation_factory = new Http_Foundation_Factory())
    {
    }
    /**
     * Do the conversion if applicable and update the response of the event.
     */
    public function on_kernel_view(View_Event $event): void
    {
        $controller_result = $event->get_controller_result();
        if (!$controller_result instanceof Response_Interface) {
            return;
        }
        $event->set_response($this->http_foundation_factory->create_response($controller_result));
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::VIEW => 'onKernelView'];
    }
}