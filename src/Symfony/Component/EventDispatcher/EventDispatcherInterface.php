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

use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface as ContractsEventDispatcherInterface;
/**
 * The EventDispatcherInterface is the central point of Symfony's event listener system.
 * Listeners are registered on the manager and events are dispatched through the
 * manager.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Event_Dispatcher_Interface extends Contracts_Event_Dispatcher_Interface
{
    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param int $priority The higher this value, the earlier an event
     *                      listener will be triggered in the chain (defaults to 0)
     */
    public function add_listener(string $event_name, callable $listener, int $priority = 0): void;
    /**
     * Adds an event subscriber.
     *
     * The subscriber is asked for all the events it is
     * interested in and added as a listener for these events.
     */
    public function add_subscriber(Event_Subscriber_Interface $subscriber): void;
    /**
     * Removes an event listener from the specified events.
     */
    public function remove_listener(string $event_name, callable $listener): void;
    public function remove_subscriber(Event_Subscriber_Interface $subscriber): void;
    /**
     * Gets the listeners of a specific event or all listeners sorted by descending priority.
     *
     * @return ($eventName is null ? array<callable[]> : array<callable>)
     */
    public function get_listeners(?string $event_name = null): array;
    /**
     * Gets the listener priority for a specific event.
     *
     * Returns null if the event or the listener does not exist.
     */
    public function get_listener_priority(string $event_name, callable $listener): ?int;
    /**
     * Checks whether an event has any registered listeners.
     */
    public function has_listeners(?string $event_name = null): bool;
}