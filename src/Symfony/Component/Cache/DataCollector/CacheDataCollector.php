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
namespace Symfony\Component\Cache\Data_Collector;

use Symfony\Component\Cache\Adapter\Traceable_Adapter;
use Symfony\Component\Cache\Adapter\Traceable_Adapter_Event;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Data_Collector\Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Late_Data_Collector_Interface;
/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @final
 */
class Cache_Data_Collector extends Data_Collector implements Late_Data_Collector_Interface
{
    /**
     * @var TraceableAdapter[]
     */
    private array $instances = [];
    public function add_instance(string $name, Traceable_Adapter $instance): void
    {
        $this->instances[$name] = $instance;
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->late_collect();
    }
    public function reset(): void
    {
        $this->data = [];
        foreach ($this->instances as $instance) {
            $instance->clear_calls();
        }
    }
    public function late_collect(): void
    {
        $empty = ['calls' => [], 'adapters' => [], 'config' => [], 'options' => [], 'statistics' => []];
        $this->data = ['instances' => $empty, 'total' => $empty];
        foreach ($this->instances as $name => $instance) {
            $this->data['instances']['calls'][$name] = $instance->get_calls();
            $this->data['instances']['adapters'][$name] = get_debug_type($instance->get_pool());
        }
        $this->data['instances']['statistics'] = $this->calculate_statistics();
        $this->data['total']['statistics'] = $this->calculate_total_statistics();
        $this->data['instances']['calls'] = $this->clone_var($this->data['instances']['calls']);
    }
    public function get_name(): string
    {
        return 'cache';
    }
    /**
     * Method returns amount of logged Cache reads: "get" calls.
     */
    public function get_statistics(): array
    {
        return $this->data['instances']['statistics'];
    }
    /**
     * Method returns the statistic totals.
     */
    public function get_totals(): array
    {
        return $this->data['total']['statistics'];
    }
    /**
     * Method returns all logged Cache call objects.
     */
    public function get_calls(): mixed
    {
        return $this->data['instances']['calls'];
    }
    /**
     * Method returns all logged Cache adapter classes.
     */
    public function get_adapters(): array
    {
        return $this->data['instances']['adapters'];
    }
    private function calculate_statistics(): array
    {
        $statistics = [];
        foreach ($this->data['instances']['calls'] as $name => $calls) {
            $statistics[$name] = ['calls' => 0, 'time' => 0, 'reads' => 0, 'writes' => 0, 'deletes' => 0, 'hits' => 0, 'misses' => 0];
            /** @var TraceableAdapterEvent $call */
            foreach ($calls as $call) {
                ++$statistics[$name]['calls'];
                $statistics[$name]['time'] += ($call->end ?? microtime(true)) - $call->start;
                if ('get' === $call->name) {
                    ++$statistics[$name]['reads'];
                    if ($call->hits) {
                        ++$statistics[$name]['hits'];
                    } else {
                        ++$statistics[$name]['misses'];
                        ++$statistics[$name]['writes'];
                    }
                } elseif ('getItem' === $call->name) {
                    ++$statistics[$name]['reads'];
                    if ($call->hits) {
                        ++$statistics[$name]['hits'];
                    } else {
                        ++$statistics[$name]['misses'];
                    }
                } elseif ('getItems' === $call->name) {
                    $statistics[$name]['reads'] += $call->hits + $call->misses;
                    $statistics[$name]['hits'] += $call->hits;
                    $statistics[$name]['misses'] += $call->misses;
                } elseif ('hasItem' === $call->name) {
                    ++$statistics[$name]['reads'];
                    foreach ($call->result ?? [] as $result) {
                        ++$statistics[$name][$result ? 'hits' : 'misses'];
                    }
                } elseif ('save' === $call->name) {
                    ++$statistics[$name]['writes'];
                } elseif ('saveDeferred' === $call->name) {
                    ++$statistics[$name]['writes'];
                } elseif ('deleteItem' === $call->name) {
                    ++$statistics[$name]['deletes'];
                }
            }
            if ($statistics[$name]['reads']) {
                $statistics[$name]['hit_read_ratio'] = round(100 * $statistics[$name]['hits'] / $statistics[$name]['reads'], 2);
            } else {
                $statistics[$name]['hit_read_ratio'] = null;
            }
        }
        return $statistics;
    }
    private function calculate_total_statistics(): array
    {
        $statistics = $this->get_statistics();
        $totals = ['calls' => 0, 'time' => 0, 'reads' => 0, 'writes' => 0, 'deletes' => 0, 'hits' => 0, 'misses' => 0];
        foreach ($statistics as $name => $values) {
            foreach ($totals as $key => $value) {
                $totals[$key] += $statistics[$name][$key];
            }
        }
        if ($totals['reads']) {
            $totals['hit_read_ratio'] = round(100 * $totals['hits'] / $totals['reads'], 2);
        } else {
            $totals['hit_read_ratio'] = null;
        }
        return $totals;
    }
}