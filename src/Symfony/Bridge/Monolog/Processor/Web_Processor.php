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
namespace Symfony\Bridge\Monolog\Processor;

use Monolog\Processor\Web_Processor as BaseWebProcessor;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * WebProcessor override to read from the HttpFoundation's Request.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @final
 */
class Web_Processor extends Base_Web_Processor implements Event_Subscriber_Interface
{
    public function __construct(?array $extra_fields = null)
    {
        // Pass an empty array as the default null value would access $_SERVER
        parent::__construct([], $extra_fields);
    }
    public function on_kernel_request(Request_Event $event): void
    {
        if ($event->is_main_request()) {
            $this->server_data = $event->get_request()->server->all();
            $this->server_data['REMOTE_ADDR'] = $event->get_request()->get_client_ip();
        }
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::REQUEST => ['onKernelRequest', 4096]];
    }
}