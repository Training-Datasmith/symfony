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
use Symfony\Component\Event_Dispatcher\Event_Dispatcher_Interface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Var_Dumper\Caster\Class_Stub;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Wrapped_Listener
{
    private readonly string|array|object $listener;
    private readonly ?\Closure $optimized_listener;
    private string $name;
    private bool $called = false;
    private bool $stopped_propagation = false;
    private string $pretty;
    private string $callable_ref;
    private Class_Stub|string $stub;
    private static bool $has_class_stub;
    public function __construct(callable|array $listener, ?string $name, private readonly Stopwatch $stopwatch, private readonly ?Event_Dispatcher_Interface $dispatcher = null, private ?int $priority = null)
    {
        $this->listener = $listener;
        $this->optimized_listener = $listener instanceof \Closure ? $listener : (\is_callable($listener) ? $listener(...) : null);
        if (\is_array($listener)) {
            [$this->name, $this->callable_ref] = $this->parse_listener($listener);
            $this->pretty = $this->name . '::' . $listener[1];
            $this->callable_ref .= '::' . $listener[1];
        } elseif ($listener instanceof \Closure) {
            $r = new \ReflectionFunction($listener);
            if ($r->is_anonymous()) {
                $this->pretty = $this->name = 'closure';
            } elseif ($class = $r->get_closure_called_class()) {
                $this->name = $class->name;
                $this->pretty = $this->name . '::' . $r->name;
            } else {
                $this->pretty = $this->name = $r->name;
            }
        } elseif (\is_string($listener)) {
            $this->pretty = $this->name = $listener;
        } else {
            $this->name = get_debug_type($listener);
            $this->pretty = $this->name . '::__invoke';
            $this->callable_ref = $listener::class . '::__invoke';
        }
        if (null !== $name) {
            $this->name = $name;
        }
        self::$has_class_stub ??= class_exists(Class_Stub::class);
    }
    public function get_wrapped_listener(): callable|array
    {
        return $this->listener;
    }
    public function was_called(): bool
    {
        return $this->called;
    }
    public function stopped_propagation(): bool
    {
        return $this->stopped_propagation;
    }
    public function get_pretty(): string
    {
        return $this->pretty;
    }
    public function get_info(string $event_name): array
    {
        $this->stub ??= self::$has_class_stub ? new Class_Stub($this->pretty . '()', $this->callable_ref ?? $this->listener) : $this->pretty . '()';
        return ['event' => $event_name, 'priority' => $this->priority ??= $this->dispatcher?->get_listener_priority($event_name, $this->listener), 'pretty' => $this->pretty, 'stub' => $this->stub];
    }
    public function __invoke(object $event, string $event_name, Event_Dispatcher_Interface $dispatcher): void
    {
        $dispatcher = $this->dispatcher ?: $dispatcher;
        $this->called = true;
        $this->priority ??= $dispatcher->get_listener_priority($event_name, $this->listener);
        $e = $this->stopwatch->start($this->name, 'event_listener');
        try {
            ($this->optimized_listener ?? $this->listener)($event, $event_name, $dispatcher);
        } finally {
            if ($e->is_started()) {
                $e->stop();
            }
        }
        if ($event instanceof Stoppable_Event_Interface && $event->is_propagation_stopped()) {
            $this->stopped_propagation = true;
        }
    }
    private function parse_listener(array $listener): array
    {
        if ($listener[0] instanceof \Closure) {
            foreach ((new \ReflectionFunction($listener[0]))->get_attributes(\Closure::class) as $attribute) {
                if ($name = $attribute->get_arguments()['name'] ?? false) {
                    return [$name, $attribute->get_arguments()['class'] ?? $name];
                }
            }
        }
        if (\is_object($listener[0])) {
            return [get_debug_type($listener[0]), $listener[0]::class];
        }
        return [$listener[0], $listener[0]];
    }
}