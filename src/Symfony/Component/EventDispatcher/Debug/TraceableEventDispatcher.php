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
namespace Symfony\Component\Event_Dispatcher\Debug;

use Psr\Event_Dispatcher\Stoppable_Event_Interface;
use Psr\Log\Logger_Interface;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Collects some data about event listeners.
 *
 * This event dispatcher delegates the dispatching to another one.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Traceable_Event_Dispatcher implements Event_Dispatcher_Interface, Reset_Interface
{
    /**
     * @var \SplObjectStorage<WrappedListener, array{string, string}>|null
     */
    private ?\Spl_Object_Storage $call_stack = null;
    private array $wrapped_listeners = [];
    private array $orphaned_events = [];
    private string $current_request_hash = '';
    public function __construct(private readonly Event_Dispatcher_Interface $dispatcher, protected Stopwatch $stopwatch, protected ?Logger_Interface $logger = null, private readonly ?Request_Stack $request_stack = null, protected readonly ?\Closure $disabled = null)
    {
    }
    public function add_listener(string $event_name, callable|array $listener, int $priority = 0): void
    {
        $this->dispatcher->add_listener($event_name, $listener, $priority);
    }
    public function add_subscriber(Event_Subscriber_Interface $subscriber): void
    {
        $this->dispatcher->add_subscriber($subscriber);
    }
    public function remove_listener(string $event_name, callable|array $listener): void
    {
        if (isset($this->wrapped_listeners[$event_name])) {
            foreach ($this->wrapped_listeners[$event_name] as $index => $wrapped_listener) {
                if ($wrapped_listener->get_wrapped_listener() === $listener || $listener instanceof \Closure && $wrapped_listener->get_wrapped_listener() == $listener) {
                    $listener = $wrapped_listener;
                    unset($this->wrapped_listeners[$event_name][$index]);
                    break;
                }
            }
        }
        $this->dispatcher->remove_listener($event_name, $listener);
    }
    public function remove_subscriber(Event_Subscriber_Interface $subscriber): void
    {
        $this->dispatcher->remove_subscriber($subscriber);
    }
    public function get_listeners(?string $event_name = null): array
    {
        return $this->dispatcher->get_listeners($event_name);
    }
    public function get_listener_priority(string $event_name, callable|array $listener): ?int
    {
        // we might have wrapped listeners for the event (if called while dispatching)
        // in that case get the priority by wrapper
        if (isset($this->wrapped_listeners[$event_name])) {
            foreach ($this->wrapped_listeners[$event_name] as $wrapped_listener) {
                if ($wrapped_listener->get_wrapped_listener() === $listener || $listener instanceof \Closure && $wrapped_listener->get_wrapped_listener() == $listener) {
                    return $this->dispatcher->get_listener_priority($event_name, $wrapped_listener);
                }
            }
        }
        return $this->dispatcher->get_listener_priority($event_name, $listener);
    }
    public function has_listeners(?string $event_name = null): bool
    {
        return $this->dispatcher->has_listeners($event_name);
    }
    public function dispatch(object $event, ?string $event_name = null): object
    {
        if ($this->disabled?->__invoke()) {
            return $this->dispatcher->dispatch($event, $event_name);
        }
        $event_name ??= $event::class;
        $this->call_stack ??= new \Spl_Object_Storage();
        $current_request_hash = $this->current_request_hash = $this->request_stack && ($request = $this->request_stack->get_current_request()) ? spl_object_hash($request) : '';
        if (null !== $this->logger && $event instanceof Stoppable_Event_Interface && $event->is_propagation_stopped()) {
            $this->logger->debug(\sprintf('The "%s" event is already stopped. No listeners have been called.', $event_name));
        }
        $this->pre_process($event_name);
        try {
            $this->before_dispatch($event_name, $event);
            try {
                $e = $this->stopwatch->start($event_name, 'section');
                try {
                    $this->dispatcher->dispatch($event, $event_name);
                } finally {
                    if ($e->is_started()) {
                        $e->stop();
                    }
                }
            } finally {
                $this->after_dispatch($event_name, $event);
            }
        } finally {
            $this->current_request_hash = $current_request_hash;
            $this->post_process($event_name);
        }
        return $event;
    }
    public function get_called_listeners(?Request $request = null): array
    {
        if (null === $this->call_stack) {
            return [];
        }
        $hash = $request ? spl_object_hash($request) : null;
        $called = [];
        foreach ($this->call_stack as $listener) {
            [$event_name, $request_hash] = $this->call_stack->get_info();
            if (null === $hash || $hash === $request_hash) {
                $called[] = $listener->get_info($event_name);
            }
        }
        return $called;
    }
    public function get_not_called_listeners(?Request $request = null): array
    {
        try {
            $all_listeners = $this->dispatcher instanceof Event_Dispatcher ? $this->get_listeners_with_priority() : $this->get_listeners_without_priority();
        } catch (\Exception $e) {
            $this->logger?->info('An exception was thrown while getting the uncalled listeners.', ['exception' => $e]);
            // unable to retrieve the uncalled listeners
            return [];
        }
        $hash = $request ? spl_object_hash($request) : null;
        $called_listeners = [];
        if (null !== $this->call_stack) {
            foreach ($this->call_stack as $called_listener) {
                [, $request_hash] = $this->call_stack->get_info();
                if (null === $hash || $hash === $request_hash) {
                    $called_listeners[] = $called_listener->get_wrapped_listener();
                }
            }
        }
        $not_called = [];
        foreach ($all_listeners as $event_name => $listeners) {
            foreach ($listeners as [$listener, $priority]) {
                if (!\in_array($listener, $called_listeners, true)) {
                    if (!$listener instanceof Wrapped_Listener) {
                        $listener = new Wrapped_Listener($listener, null, $this->stopwatch, $this, $priority);
                    }
                    $not_called[] = $listener->get_info($event_name);
                }
            }
        }
        uasort($not_called, $this->sort_not_called_listeners(...));
        return $not_called;
    }
    public function get_orphaned_events(?Request $request = null): array
    {
        if ($request) {
            return $this->orphaned_events[spl_object_hash($request)] ?? [];
        }
        if (!$this->orphaned_events) {
            return [];
        }
        return array_merge(...array_values($this->orphaned_events));
    }
    public function reset(): void
    {
        $this->call_stack = null;
        $this->orphaned_events = [];
        $this->current_request_hash = '';
    }
    /**
     * Proxies all method calls to the original event dispatcher.
     *
     * @param string $method    The method name
     * @param array  $arguments The method arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->dispatcher->{$method}(...$arguments);
    }
    /**
     * Called before dispatching the event.
     */
    protected function before_dispatch(string $event_name, object $event): void
    {
    }
    /**
     * Called after dispatching the event.
     */
    protected function after_dispatch(string $event_name, object $event): void
    {
    }
    private function pre_process(string $event_name): void
    {
        if (!$this->dispatcher->has_listeners($event_name)) {
            $this->orphaned_events[$this->current_request_hash][] = $event_name;
            return;
        }
        foreach ($this->dispatcher->get_listeners($event_name) as $listener) {
            $priority = $this->get_listener_priority($event_name, $listener);
            $wrapped_listener = new Wrapped_Listener($listener instanceof Wrapped_Listener ? $listener->get_wrapped_listener() : $listener, null, $this->stopwatch, $this);
            $this->wrapped_listeners[$event_name][] = $wrapped_listener;
            $this->dispatcher->remove_listener($event_name, $listener);
            $this->dispatcher->add_listener($event_name, $wrapped_listener, $priority);
            $this->call_stack[$wrapped_listener] = [$event_name, $this->current_request_hash];
        }
    }
    private function post_process(string $event_name): void
    {
        unset($this->wrapped_listeners[$event_name]);
        $skipped = false;
        foreach ($this->dispatcher->get_listeners($event_name) as $listener) {
            if (!$listener instanceof Wrapped_Listener) {
                // #12845: a new listener was added during dispatch.
                continue;
            }
            // Unwrap listener
            $priority = $this->get_listener_priority($event_name, $listener);
            $this->dispatcher->remove_listener($event_name, $listener);
            $this->dispatcher->add_listener($event_name, $listener->get_wrapped_listener(), $priority);
            if (null !== $this->logger) {
                $context = ['event' => $event_name, 'listener' => $listener->get_pretty()];
            }
            if ($listener->was_called()) {
                $this->logger?->debug('Notified event "{event}" to listener "{listener}".', $context);
            } else {
                unset($this->call_stack[$listener]);
            }
            if (null !== $this->logger && $skipped) {
                $this->logger->debug('Listener "{listener}" was not called for event "{event}".', $context);
            }
            if ($listener->stopped_propagation()) {
                $this->logger?->debug('Listener "{listener}" stopped propagation of the event "{event}".', $context);
                $skipped = true;
            }
        }
    }
    private function sort_not_called_listeners(array $a, array $b): int
    {
        if (0 !== $cmp = strcmp((string) $a['event'], (string) $b['event'])) {
            return $cmp;
        }
        if (\is_int($a['priority']) && !\is_int($b['priority'])) {
            return 1;
        }
        if (!\is_int($a['priority']) && \is_int($b['priority'])) {
            return -1;
        }
        if ($a['priority'] === $b['priority']) {
            return 0;
        }
        if ($a['priority'] > $b['priority']) {
            return -1;
        }
        return 1;
    }
    private function get_listeners_with_priority(): array
    {
        $result = [];
        $all_listeners = new \ReflectionProperty(Event_Dispatcher::class, 'listeners');
        foreach ($all_listeners->get_value($this->dispatcher) as $event_name => $listeners_by_priority) {
            foreach ($listeners_by_priority as $priority => $listeners) {
                foreach ($listeners as $listener) {
                    $result[$event_name][] = [$listener, $priority];
                }
            }
        }
        return $result;
    }
    private function get_listeners_without_priority(): array
    {
        $result = [];
        foreach ($this->get_listeners() as $event_name => $listeners) {
            foreach ($listeners as $listener) {
                $result[$event_name][] = [$listener, null];
            }
        }
        return $result;
    }
}