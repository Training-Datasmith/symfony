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

use Psr\Event_Dispatcher\Stoppable_Event_Interface;
use Symfony\Component\Event_Dispatcher\Debug\Wrapped_Listener;
/**
 * The EventDispatcherInterface is the central point of Symfony's event listener system.
 *
 * Listeners are registered on the manager and events are dispatched through the
 * manager.
 *
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Event_Dispatcher implements Event_Dispatcher_Interface
{
    private array $listeners = [];
    private array $sorted = [];
    private array $optimized;
    public function __construct()
    {
        if (self::class === static::class) {
            $this->optimized = [];
        }
    }
    public function dispatch(object $event, ?string $event_name = null): object
    {
        $event_name ??= $event::class;
        if (isset($this->optimized)) {
            $listeners = $this->optimized[$event_name] ?? (empty($this->listeners[$event_name]) ? [] : $this->optimize_listeners($event_name));
        } else {
            $listeners = $this->get_listeners($event_name);
        }
        if ($listeners) {
            $this->call_listeners($listeners, $event_name, $event);
        }
        return $event;
    }
    public function get_listeners(?string $event_name = null): array
    {
        if (null !== $event_name) {
            if (empty($this->listeners[$event_name])) {
                return [];
            }
            if (!isset($this->sorted[$event_name])) {
                $this->sort_listeners($event_name);
            }
            return $this->sorted[$event_name];
        }
        foreach ($this->listeners as $event_name => $event_listeners) {
            if (!isset($this->sorted[$event_name])) {
                $this->sort_listeners($event_name);
            }
        }
        return array_filter($this->sorted);
    }
    public function get_listener_priority(string $event_name, callable|array $listener): ?int
    {
        if (empty($this->listeners[$event_name])) {
            return null;
        }
        if (\is_array($listener) && isset($listener[0]) && $listener[0] instanceof \Closure && 2 >= \count($listener)) {
            $listener[0] = $listener[0]();
            $listener[1] ??= '__invoke';
        }
        foreach ($this->listeners[$event_name] as $priority => &$listeners) {
            foreach ($listeners as &$v) {
                if ($v !== $listener && \is_array($v) && isset($v[0]) && $v[0] instanceof \Closure && 2 >= \count($v)) {
                    $v[0] = $v[0]();
                    $v[1] ??= '__invoke';
                }
                if ($v === $listener || $listener instanceof \Closure && $v == $listener) {
                    return $priority;
                }
            }
        }
        return null;
    }
    public function has_listeners(?string $event_name = null): bool
    {
        if (null !== $event_name) {
            return !empty($this->listeners[$event_name]);
        }
        foreach ($this->listeners as $event_listeners) {
            if ($event_listeners) {
                return true;
            }
        }
        return false;
    }
    public function add_listener(string $event_name, callable|array $listener, int $priority = 0): void
    {
        $this->listeners[$event_name][$priority][] = $listener;
        unset($this->sorted[$event_name], $this->optimized[$event_name]);
    }
    public function remove_listener(string $event_name, callable|array $listener): void
    {
        if (empty($this->listeners[$event_name])) {
            return;
        }
        if (\is_array($listener) && isset($listener[0]) && $listener[0] instanceof \Closure && 2 >= \count($listener)) {
            $listener[0] = $listener[0]();
            $listener[1] ??= '__invoke';
        }
        foreach ($this->listeners[$event_name] as $priority => &$listeners) {
            foreach ($listeners as $k => &$v) {
                if ($v !== $listener && \is_array($v) && isset($v[0]) && $v[0] instanceof \Closure && 2 >= \count($v)) {
                    $v[0] = $v[0]();
                    $v[1] ??= '__invoke';
                }
                if ($v === $listener || $listener instanceof \Closure && $v == $listener) {
                    unset($listeners[$k], $this->sorted[$event_name], $this->optimized[$event_name]);
                }
            }
            if (!$listeners) {
                unset($this->listeners[$event_name][$priority]);
            }
        }
    }
    public function add_subscriber(Event_Subscriber_Interface $subscriber): void
    {
        foreach ($subscriber->get_subscribed_events() as $event_name => $params) {
            if (\is_string($params)) {
                $this->add_listener($event_name, [$subscriber, $params]);
            } elseif (\is_string($params[0])) {
                $this->add_listener($event_name, [$subscriber, $params[0]], $params[1] ?? 0);
            } else {
                foreach ($params as $listener) {
                    $this->add_listener($event_name, [$subscriber, $listener[0]], $listener[1] ?? 0);
                }
            }
        }
    }
    public function remove_subscriber(Event_Subscriber_Interface $subscriber): void
    {
        foreach ($subscriber->get_subscribed_events() as $event_name => $params) {
            if (\is_array($params) && \is_array($params[0])) {
                foreach ($params as $listener) {
                    $this->remove_listener($event_name, [$subscriber, $listener[0]]);
                }
            } else {
                $this->remove_listener($event_name, [$subscriber, \is_string($params) ? $params : $params[0]]);
            }
        }
    }
    /**
     * Triggers the listeners of an event.
     *
     * This method can be overridden to add functionality that is executed
     * for each listener.
     *
     * @param callable[] $listeners The event listeners
     * @param string     $eventName The name of the event to dispatch
     * @param object     $event     The event object to pass to the event handlers/listeners
     */
    protected function call_listeners(iterable $listeners, string $event_name, object $event): void
    {
        $stoppable = $event instanceof Stoppable_Event_Interface;
        foreach ($listeners as $listener) {
            if ($stoppable && $event->is_propagation_stopped()) {
                break;
            }
            $listener($event, $event_name, $this);
        }
    }
    /**
     * Sorts the internal list of listeners for the given event by priority.
     */
    private function sort_listeners(string $event_name): void
    {
        krsort($this->listeners[$event_name]);
        $this->sorted[$event_name] = [];
        foreach ($this->listeners[$event_name] as &$listeners) {
            foreach ($listeners as &$listener) {
                if (\is_array($listener) && isset($listener[0]) && $listener[0] instanceof \Closure && 2 >= \count($listener)) {
                    $listener[0] = $listener[0]();
                    $listener[1] ??= '__invoke';
                }
                $this->sorted[$event_name][] = $listener;
            }
        }
    }
    /**
     * Optimizes the internal list of listeners for the given event by priority.
     */
    private function optimize_listeners(string $event_name): array
    {
        krsort($this->listeners[$event_name]);
        $this->optimized[$event_name] = [];
        foreach ($this->listeners[$event_name] as &$listeners) {
            foreach ($listeners as &$listener) {
                $closure =& $this->optimized[$event_name][];
                if (\is_array($listener) && isset($listener[0]) && $listener[0] instanceof \Closure && 2 >= \count($listener)) {
                    $closure = static function (...$args) use (&$listener, &$closure): void {
                        if ($listener[0] instanceof \Closure) {
                            $listener[0] = $listener[0]();
                            $listener[1] ??= '__invoke';
                        }
                        ($closure = $listener(...))(...$args);
                    };
                } else {
                    $closure = $listener instanceof Wrapped_Listener ? $listener : $listener(...);
                }
            }
        }
        return $this->optimized[$event_name];
    }
}