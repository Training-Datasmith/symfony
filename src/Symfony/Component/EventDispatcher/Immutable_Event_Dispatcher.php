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
namespace Symfony\Component\Event_Dispatcher;

/**
 * A read-only proxy for an event dispatcher.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Immutable_Event_Dispatcher implements Event_Dispatcher_Interface
{
    public function __construct(private readonly Event_Dispatcher_Interface $dispatcher)
    {
    }
    public function dispatch(object $event, ?string $event_name = null): object
    {
        return $this->dispatcher->dispatch($event, $event_name);
    }
    public function add_listener(string $event_name, callable|array $listener, int $priority = 0): never
    {
        throw new \BadMethodCallException('Unmodifiable event dispatchers must not be modified.');
    }
    public function add_subscriber(Event_Subscriber_Interface $subscriber): never
    {
        throw new \BadMethodCallException('Unmodifiable event dispatchers must not be modified.');
    }
    public function remove_listener(string $event_name, callable|array $listener): never
    {
        throw new \BadMethodCallException('Unmodifiable event dispatchers must not be modified.');
    }
    public function remove_subscriber(Event_Subscriber_Interface $subscriber): never
    {
        throw new \BadMethodCallException('Unmodifiable event dispatchers must not be modified.');
    }
    public function get_listeners(?string $event_name = null): array
    {
        return $this->dispatcher->get_listeners($event_name);
    }
    public function get_listener_priority(string $event_name, callable|array $listener): ?int
    {
        return $this->dispatcher->get_listener_priority($event_name, $listener);
    }
    public function has_listeners(?string $event_name = null): bool
    {
        return $this->dispatcher->has_listeners($event_name);
    }
}