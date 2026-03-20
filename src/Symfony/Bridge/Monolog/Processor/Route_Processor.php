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

use Monolog\Log_Record;
use Monolog\Resettable_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Kernel\Event\Finish_Request_Event;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Adds the current route information to the log entry.
 *
 * @author Piotr Stankowski <git@trakos.pl>
 *
 * @final
 */
class Route_Processor implements Event_Subscriber_Interface, Reset_Interface, Resettable_Interface
{
    private array $route_data = [];
    public function __construct(private readonly bool $include_params = true)
    {
        $this->reset();
    }
    public function __invoke(Log_Record $record): Log_Record
    {
        if ($this->route_data && !isset($record->extra['requests'])) {
            $record->extra['requests'] = array_values($this->route_data);
        }
        return $record;
    }
    public function reset(): void
    {
        $this->route_data = [];
    }
    public function add_route_data(Request_Event $event): void
    {
        if ($event->is_main_request()) {
            $this->reset();
        }
        $request = $event->get_request();
        if (!$request->attributes->has('_controller')) {
            return;
        }
        $current_request_data = ['controller' => $request->attributes->get('_controller'), 'route' => $request->attributes->get('_route')];
        if ($this->include_params) {
            $current_request_data['route_params'] = $request->attributes->get('_route_params');
        }
        $this->route_data[spl_object_id($request)] = $current_request_data;
    }
    public function remove_route_data(Finish_Request_Event $event): void
    {
        $request_id = spl_object_id($event->get_request());
        unset($this->route_data[$request_id]);
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::REQUEST => ['addRouteData', 1], Kernel_Events::FINISH_REQUEST => ['removeRouteData', 1]];
    }
}