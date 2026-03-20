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

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Kernel_Interface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\Stopwatch_Event;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Time_Data_Collector extends Data_Collector implements Late_Data_Collector_Interface
{
    public function __construct(private readonly ?Kernel_Interface $kernel = null, private readonly ?Stopwatch $stopwatch = null)
    {
        $this->data = ['events' => [], 'stopwatch_installed' => false, 'start_time' => 0];
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        if (null !== $this->kernel) {
            $start_time = $this->kernel->get_start_time();
        } else {
            $start_time = $request->server->get('REQUEST_TIME_FLOAT');
        }
        $this->data = ['token' => $request->attributes->get('_stopwatch_token'), 'start_time' => $start_time * 1000, 'events' => [], 'stopwatch_installed' => class_exists(Stopwatch::class, false)];
    }
    public function reset(): void
    {
        $this->data = ['events' => [], 'stopwatch_installed' => false, 'start_time' => 0];
        $this->stopwatch?->reset();
    }
    public function late_collect(): void
    {
        if (null !== $this->stopwatch && isset($this->data['token'])) {
            $this->set_events($this->stopwatch->get_section_events($this->data['token']));
        }
        unset($this->data['token']);
    }
    /**
     * @param StopwatchEvent[] $events The request events
     */
    public function set_events(array $events): void
    {
        foreach ($events as $event) {
            $event->ensure_stopped();
        }
        $this->data['events'] = $events;
    }
    /**
     * @return StopwatchEvent[]
     */
    public function get_events(): array
    {
        return $this->data['events'];
    }
    /**
     * Gets the request elapsed time.
     */
    public function get_duration(): float
    {
        if (!isset($this->data['events']['__section__'])) {
            return 0;
        }
        $last_event = $this->data['events']['__section__'];
        return $last_event->get_origin() + $last_event->get_duration() - $this->get_start_time();
    }
    /**
     * Gets the initialization time.
     *
     * This is the time spent until the beginning of the request handling.
     */
    public function get_init_time(): float
    {
        if (!isset($this->data['events']['__section__'])) {
            return 0;
        }
        return $this->data['events']['__section__']->get_origin() - $this->get_start_time();
    }
    public function get_start_time(): float
    {
        return $this->data['start_time'];
    }
    public function is_stopwatch_installed(): bool
    {
        return $this->data['stopwatch_installed'];
    }
    public function get_name(): string
    {
        return 'time';
    }
}