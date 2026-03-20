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
namespace Symfony\Component\Http_Kernel\Data_Collector;

use Symfony\Component\Event_Dispatcher\Debug\Traceable_Event_Dispatcher;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Var_Dumper\Cloner\Data;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @see TraceableEventDispatcher
 *
 * @final
 */
class Event_Data_Collector extends Data_Collector implements Late_Data_Collector_Interface
{
    /** @var iterable<EventDispatcherInterface> */
    private readonly iterable $dispatchers;
    private ?Request $current_request = null;
    /**
     * @param iterable<EventDispatcherInterface>|EventDispatcherInterface|null $dispatchers
     */
    public function __construct(iterable|Event_Dispatcher_Interface|null $dispatchers = null, private readonly ?Request_Stack $request_stack = null, private readonly string $default_dispatcher = 'event_dispatcher')
    {
        if ($dispatchers instanceof Event_Dispatcher_Interface) {
            $dispatchers = [$this->default_dispatcher => $dispatchers];
        }
        $this->dispatchers = $dispatchers ?? [];
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->current_request = $this->request_stack && $this->request_stack->get_main_request() !== $request ? $request : null;
        $this->data = [];
    }
    public function reset(): void
    {
        parent::reset();
        foreach ($this->dispatchers as $dispatcher) {
            if ($dispatcher instanceof Reset_Interface) {
                $dispatcher->reset();
            }
        }
    }
    public function late_collect(): void
    {
        foreach ($this->dispatchers as $name => $dispatcher) {
            if (!$dispatcher instanceof Traceable_Event_Dispatcher) {
                continue;
            }
            $this->set_called_listeners($dispatcher->get_called_listeners($this->current_request), $name);
            $this->set_not_called_listeners($dispatcher->get_not_called_listeners($this->current_request), $name);
            $this->set_orphaned_events($dispatcher->get_orphaned_events($this->current_request), $name);
        }
        $this->data = $this->clone_var($this->data);
    }
    public function get_data(): array|Data
    {
        return $this->data;
    }
    /**
     * @see TraceableEventDispatcher
     */
    public function set_called_listeners(array $listeners, ?string $dispatcher = null): void
    {
        $this->data[$dispatcher ?? $this->default_dispatcher]['called_listeners'] = $listeners;
    }
    /**
     * @see TraceableEventDispatcher
     */
    public function get_called_listeners(?string $dispatcher = null): array|Data
    {
        return $this->data[$dispatcher ?? $this->default_dispatcher]['called_listeners'] ?? [];
    }
    /**
     * @see TraceableEventDispatcher
     */
    public function set_not_called_listeners(array $listeners, ?string $dispatcher = null): void
    {
        $this->data[$dispatcher ?? $this->default_dispatcher]['not_called_listeners'] = $listeners;
    }
    /**
     * @see TraceableEventDispatcher
     */
    public function get_not_called_listeners(?string $dispatcher = null): array|Data
    {
        return $this->data[$dispatcher ?? $this->default_dispatcher]['not_called_listeners'] ?? [];
    }
    /**
     * @param array $events An array of orphaned events
     *
     * @see TraceableEventDispatcher
     */
    public function set_orphaned_events(array $events, ?string $dispatcher = null): void
    {
        $this->data[$dispatcher ?? $this->default_dispatcher]['orphaned_events'] = $events;
    }
    /**
     * @see TraceableEventDispatcher
     */
    public function get_orphaned_events(?string $dispatcher = null): array|Data
    {
        return $this->data[$dispatcher ?? $this->default_dispatcher]['orphaned_events'] ?? [];
    }
    public function get_name(): string
    {
        return 'events';
    }
}