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
namespace Symfony\Bridge\Doctrine;

use Doctrine\Common\Event_Args;
use Doctrine\Common\Event_Manager;
use Doctrine\Common\Event_Subscriber;
use Psr\Container\Container_Interface;
/**
 * Allows lazy loading of listener and subscriber services.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Container_Aware_Event_Manager extends Event_Manager
{
    private array $initialized = [];
    private bool $initialized_subscribers = false;
    private array $initialized_hash_mapping = [];
    private array $methods = [];
    /**
     * @param list<array{string[], string|object}> $listeners List of [events, listener] tuples
     */
    public function __construct(private readonly Container_Interface $container, private array $listeners = [])
    {
    }
    public function dispatch_event(string $event_name, ?Event_Args $event_args = null): void
    {
        if (!$this->initialized_subscribers) {
            $this->initialize_subscribers();
        }
        if (!isset($this->listeners[$event_name])) {
            return;
        }
        $event_args ??= Event_Args::get_empty_instance();
        if (!isset($this->initialized[$event_name])) {
            $this->initialize_listeners($event_name);
        }
        foreach ($this->listeners[$event_name] as $hash => $listener) {
            $listener->{$this->methods[$event_name][$hash]}($event_args);
        }
    }
    public function get_listeners(string $event): array
    {
        if (!$this->initialized_subscribers) {
            $this->initialize_subscribers();
        }
        if (!isset($this->initialized[$event])) {
            $this->initialize_listeners($event);
        }
        return $this->listeners[$event];
    }
    public function get_all_listeners(): array
    {
        if (!$this->initialized_subscribers) {
            $this->initialize_subscribers();
        }
        foreach ($this->listeners as $event => $listeners) {
            if (!isset($this->initialized[$event])) {
                $this->initialize_listeners($event);
            }
        }
        return $this->listeners;
    }
    public function has_listeners(string $event): bool
    {
        if (!$this->initialized_subscribers) {
            $this->initialize_subscribers();
        }
        return isset($this->listeners[$event]) && $this->listeners[$event];
    }
    public function add_event_listener(string|array $events, object|string $listener): void
    {
        if (!$this->initialized_subscribers) {
            $this->initialize_subscribers();
        }
        $hash = $this->get_hash($listener);
        foreach ((array) $events as $event) {
            // Overrides listener if a previous one was associated already
            // Prevents duplicate listeners on same event (same instance only)
            $this->listeners[$event][$hash] = $listener;
            if (\is_string($listener)) {
                unset($this->initialized[$event]);
                unset($this->initialized_hash_mapping[$event][$hash]);
            } else {
                $this->methods[$event][$hash] = $this->get_method($listener, $event);
            }
        }
    }
    public function remove_event_listener(string|array $events, object|string $listener): void
    {
        if (!$this->initialized_subscribers) {
            $this->initialize_subscribers();
        }
        $hash = $this->get_hash($listener);
        foreach ((array) $events as $event) {
            if (isset($this->initialized_hash_mapping[$event][$hash])) {
                $hash = $this->initialized_hash_mapping[$event][$hash];
                unset($this->initialized_hash_mapping[$event][$hash]);
            }
            // Check if we actually have this listener associated
            if (isset($this->listeners[$event][$hash])) {
                unset($this->listeners[$event][$hash]);
            }
            if (isset($this->methods[$event][$hash])) {
                unset($this->methods[$event][$hash]);
            }
        }
    }
    public function add_event_subscriber(Event_Subscriber $subscriber): void
    {
        if (!$this->initialized_subscribers) {
            $this->initialize_subscribers();
        }
        parent::add_event_subscriber($subscriber);
    }
    public function remove_event_subscriber(Event_Subscriber $subscriber): void
    {
        if (!$this->initialized_subscribers) {
            $this->initialize_subscribers();
        }
        parent::remove_event_subscriber($subscriber);
    }
    private function initialize_listeners(string $event_name): void
    {
        $this->initialized[$event_name] = true;
        // We'll refill the whole array in order to keep the same order
        $listeners = [];
        foreach ($this->listeners[$event_name] as $hash => $listener) {
            if (\is_string($listener)) {
                $listener = $this->container->get($listener);
                $new_hash = $this->get_hash($listener);
                $this->initialized_hash_mapping[$event_name][$hash] = $new_hash;
                $listeners[$new_hash] = $listener;
                $this->methods[$event_name][$new_hash] = $this->get_method($listener, $event_name);
            } else {
                $listeners[$hash] = $listener;
            }
        }
        $this->listeners[$event_name] = $listeners;
    }
    private function initialize_subscribers(): void
    {
        $this->initialized_subscribers = true;
        $listeners = $this->listeners;
        $this->listeners = [];
        foreach ($listeners as $listener) {
            if (\is_array($listener)) {
                $this->add_event_listener(...$listener);
                continue;
            }
            throw new \InvalidArgumentException(\sprintf('Using Doctrine subscriber "%s" is not allowed. Register it as a listener instead, using e.g. the #[AsDoctrineListener] or #[AsDocumentListener] attribute.', \is_object($listener) ? get_debug_type($listener) : $listener));
        }
    }
    private function get_hash(string|object $listener): string
    {
        if (\is_string($listener)) {
            return '_service_' . $listener;
        }
        return spl_object_hash($listener);
    }
    private function get_method(object $listener, string $event): string
    {
        if (!method_exists($listener, $event) && method_exists($listener, '__invoke')) {
            return '__invoke';
        }
        return $event;
    }
}