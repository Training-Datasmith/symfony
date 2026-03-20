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
namespace Symfony\Bundle\Security_Bundle\Event_Listener;

use Symfony\Bundle\Security_Bundle\Security\Firewall_Map;
use Symfony\Component\Http_Kernel\Event\Finish_Request_Event;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\Firewall_Map_Interface;
use Symfony\Component\Security\Http\Logout\Logout_Url_Generator;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class Firewall_Listener extends Firewall
{
    public function __construct(private readonly Firewall_Map_Interface $map, Event_Dispatcher_Interface $dispatcher, private readonly Logout_Url_Generator $logout_url_generator)
    {
        parent::__construct($map, $dispatcher);
    }
    public function configure_logout_url_generator(Request_Event $event): void
    {
        if (!$event->is_main_request()) {
            return;
        }
        if ($this->map instanceof Firewall_Map && $config = $this->map->get_firewall_config($event->get_request())) {
            $this->logout_url_generator->set_current_firewall($config->get_name(), $config->get_context());
        }
    }
    public function on_kernel_finish_request(Finish_Request_Event $event): void
    {
        if ($event->is_main_request()) {
            $this->logout_url_generator->set_current_firewall(null);
        }
        parent::on_kernel_finish_request($event);
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::REQUEST => [['configureLogoutUrlGenerator', 8], ['onKernelRequest', 8]], Kernel_Events::FINISH_REQUEST => 'onKernelFinishRequest'];
    }
}