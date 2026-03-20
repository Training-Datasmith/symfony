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
namespace Symfony\Component\Cache\Adapter;

use Psr\Cache\Cache_Item_Interface;
use Symfony\Component\Cache\Cache_Item;
use Symfony\Component\Cache\Exception\BadMethodCallException;
use Symfony\Component\Cache\Pruneable_Interface;
use Symfony\Component\Cache\Resettable_Interface;
use Symfony\Contracts\Cache\Cache_Interface;
use Symfony\Contracts\Cache\Namespaced_Pool_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * An adapter that collects data about all cache calls.
 *
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Traceable_Adapter implements Adapter_Interface, Cache_Interface, Namespaced_Pool_Interface, Pruneable_Interface, Resettable_Interface
{
    private string $namespace = '';
    private array $calls = [];
    public function __construct(protected Adapter_Interface $pool, protected readonly ?\Closure $disabled = null)
    {
    }
    /**
     * @throws BadMethodCallException When the item pool is not a CacheInterface
     */
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        if (!$this->pool instanceof Cache_Interface) {
            throw new BadMethodCallException(\sprintf('Cannot call "%s::get()": this class doesn\'t implement "%s".', get_debug_type($this->pool), Cache_Interface::class));
        }
        if ($this->disabled?->__invoke()) {
            return $this->pool->get($key, $callback, $beta, $metadata);
        }
        $is_hit = true;
        $callback = static function (Cache_Item $item, bool &$save) use ($callback, &$is_hit) {
            $is_hit = $item->is_hit();
            return $callback($item, $save);
        };
        $event = $this->start(__FUNCTION__);
        try {
            $value = $this->pool->get($key, $callback, $beta, $metadata);
            $event->result[$key] = get_debug_type($value);
        } finally {
            $event->end = microtime(true);
        }
        if ($is_hit) {
            ++$event->hits;
        } else {
            ++$event->misses;
        }
        return $value;
    }
    public function get_item(mixed $key): Cache_Item
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->get_item($key);
        }
        $event = $this->start(__FUNCTION__);
        try {
            $item = $this->pool->get_item($key);
        } finally {
            $event->end = microtime(true);
        }
        if ($event->result[$key] = $item->is_hit()) {
            ++$event->hits;
        } else {
            ++$event->misses;
        }
        return $item;
    }
    public function has_item(mixed $key): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->has_item($key);
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$key] = $this->pool->has_item($key);
        } finally {
            $event->end = microtime(true);
        }
    }
    public function delete_item(mixed $key): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->delete_item($key);
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$key] = $this->pool->delete_item($key);
        } finally {
            $event->end = microtime(true);
        }
    }
    public function save(Cache_Item_Interface $item): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->save($item);
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$item->get_key()] = $this->pool->save($item);
        } finally {
            $event->end = microtime(true);
        }
    }
    public function save_deferred(Cache_Item_Interface $item): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->save_deferred($item);
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$item->get_key()] = $this->pool->save_deferred($item);
        } finally {
            $event->end = microtime(true);
        }
    }
    public function get_items(array $keys = []): iterable
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->get_items($keys);
        }
        $event = $this->start(__FUNCTION__);
        try {
            $result = $this->pool->get_items($keys);
        } finally {
            $event->end = microtime(true);
        }
        $f = static function () use ($result, $event) {
            $event->result = [];
            foreach ($result as $key => $item) {
                if ($event->result[$key] = $item->is_hit()) {
                    ++$event->hits;
                } else {
                    ++$event->misses;
                }
                yield $key => $item;
            }
        };
        return $f();
    }
    public function clear(string $prefix = ''): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->clear($prefix);
        }
        $event = $this->start(__FUNCTION__);
        try {
            if ($this->pool instanceof Adapter_Interface) {
                return $event->result = $this->pool->clear($prefix);
            }
            return $event->result = $this->pool->clear();
        } finally {
            $event->end = microtime(true);
        }
    }
    public function delete_items(array $keys): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->delete_items($keys);
        }
        $event = $this->start(__FUNCTION__);
        $event->result['keys'] = $keys;
        try {
            return $event->result['result'] = $this->pool->delete_items($keys);
        } finally {
            $event->end = microtime(true);
        }
    }
    public function commit(): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->commit();
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result = $this->pool->commit();
        } finally {
            $event->end = microtime(true);
        }
    }
    public function prune(): bool
    {
        if (!$this->pool instanceof Pruneable_Interface) {
            return false;
        }
        if ($this->disabled?->__invoke()) {
            return $this->pool->prune();
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result = $this->pool->prune();
        } finally {
            $event->end = microtime(true);
        }
    }
    public function reset(): void
    {
        if ($this->pool instanceof Reset_Interface) {
            $this->pool->reset();
        }
        $this->clear_calls();
    }
    public function delete(string $key): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->delete_item($key);
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$key] = $this->pool->delete_item($key);
        } finally {
            $event->end = microtime(true);
        }
    }
    public function get_calls(): array
    {
        return $this->calls;
    }
    public function clear_calls(): void
    {
        $this->calls = [];
    }
    public function get_pool(): Adapter_Interface
    {
        return $this->pool;
    }
    /**
     * @throws BadMethodCallException When the item pool is not a NamespacedPoolInterface
     */
    public function with_sub_namespace(string $namespace): static
    {
        if (!$this->pool instanceof Namespaced_Pool_Interface) {
            throw new BadMethodCallException(\sprintf('Cannot call "%s::withSubNamespace()": this class doesn\'t implement "%s".', get_debug_type($this->pool), Namespaced_Pool_Interface::class));
        }
        $calls =& $this->calls;
        // ensures clones share the same array
        $clone = clone $this;
        $clone->namespace .= Cache_Item::validate_key($namespace) . ':';
        $clone->pool = $this->pool->with_sub_namespace($namespace);
        return $clone;
    }
    protected function start(string $name): Traceable_Adapter_Event
    {
        $this->calls[] = $event = new Traceable_Adapter_Event();
        $event->name = $name;
        $event->start = microtime(true);
        $event->namespace = $this->namespace;
        return $event;
    }
}
/**
 * @internal
 */
class Traceable_Adapter_Event
{
    public string $name;
    public float $start;
    public float $end;
    public array|bool $result;
    public int $hits = 0;
    public int $misses = 0;
    public string $namespace;
}