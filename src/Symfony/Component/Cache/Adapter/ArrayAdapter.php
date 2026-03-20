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
use Psr\Clock\Clock_Interface;
use Psr\Log\Logger_Aware_Interface;
use Psr\Log\Logger_Aware_Trait;
use Symfony\Component\Cache\Cache_Item;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Resettable_Interface;
use Symfony\Contracts\Cache\Cache_Interface;
use Symfony\Contracts\Cache\Namespaced_Pool_Interface;
/**
 * An in-memory cache storage.
 *
 * Acts as a least-recently-used (LRU) storage when configured with a maximum number of items.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Array_Adapter implements Adapter_Interface, Cache_Interface, Namespaced_Pool_Interface, Logger_Aware_Interface, Resettable_Interface
{
    use Logger_Aware_Trait;
    private array $values = [];
    private array $tags = [];
    private array $expiries = [];
    private array $sub_pools = [];
    private static \Closure $create_cache_item;
    /**
     * @param bool $storeSerialized Disabling serialization can lead to cache corruptions when storing mutable values but increases performance otherwise
     */
    public function __construct(private int $default_lifetime = 0, private bool $store_serialized = true, private float $max_lifetime = 0, private int $max_items = 0, private ?Clock_Interface $clock = null)
    {
        if (0 > $max_lifetime) {
            throw new InvalidArgumentException(\sprintf('Argument $maxLifetime must be positive, %F passed.', $max_lifetime));
        }
        if (0 > $max_items) {
            throw new InvalidArgumentException(\sprintf('Argument $maxItems must be a positive integer, %d passed.', $max_items));
        }
        self::$create_cache_item ??= \Closure::bind(static function ($key, $value, $is_hit, $tags): \Symfony\Component\Cache\Cache_Item {
            $item = new Cache_Item();
            $item->key = $key;
            $item->value = $value;
            $item->is_hit = $is_hit;
            if (null !== $tags) {
                $item->metadata[Cache_Item::METADATA_TAGS] = $tags;
            }
            return $item;
        }, null, Cache_Item::class);
    }
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        $item = $this->get_item($key);
        $metadata = $item->get_metadata();
        // ArrayAdapter works in memory, we don't care about stampede protection
        if (\INF === $beta || !$item->is_hit()) {
            $save = true;
            $item->set($callback($item, $save));
            $this->save($item);
        }
        return $item->get();
    }
    public function delete(string $key): bool
    {
        return $this->delete_item($key);
    }
    public function has_item(mixed $key): bool
    {
        if (\is_string($key) && isset($this->expiries[$key]) && $this->expiries[$key] > $this->get_current_time()) {
            if ($this->max_items) {
                // Move the item last in the storage
                $value = $this->values[$key];
                unset($this->values[$key]);
                $this->values[$key] = $value;
            }
            return true;
        }
        \assert('' !== Cache_Item::validate_key($key));
        return isset($this->expiries[$key]) && !$this->delete_item($key);
    }
    public function get_item(mixed $key): Cache_Item
    {
        if (!$is_hit = $this->has_item($key)) {
            $value = null;
            if (!$this->max_items) {
                // Track misses in non-LRU mode only
                $this->values[$key] = null;
            }
        } else {
            $value = $this->store_serialized ? $this->unfreeze($key, $is_hit) : $this->values[$key];
        }
        return (self::$create_cache_item)($key, $value, $is_hit, $this->tags[$key] ?? null);
    }
    public function get_items(array $keys = []): iterable
    {
        \assert(self::validate_keys($keys));
        return $this->generate_items($keys, $this->get_current_time(), self::$create_cache_item);
    }
    public function delete_item(mixed $key): bool
    {
        \assert('' !== Cache_Item::validate_key($key));
        unset($this->values[$key], $this->tags[$key], $this->expiries[$key]);
        return true;
    }
    public function delete_items(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete_item($key);
        }
        return true;
    }
    public function save(Cache_Item_Interface $item): bool
    {
        if (!$item instanceof Cache_Item) {
            return false;
        }
        $item = (array) $item;
        $key = $item["\x00*\x00key"];
        $value = $item["\x00*\x00value"];
        $expiry = $item["\x00*\x00expiry"];
        $now = $this->get_current_time();
        if (null !== $expiry) {
            if (!$expiry) {
                $expiry = \PHP_INT_MAX;
            } elseif ($expiry <= $now) {
                $this->delete_item($key);
                return true;
            }
        }
        if ($this->store_serialized && null === $value = $this->freeze($value, $key)) {
            return false;
        }
        if (null === $expiry && 0 < $this->default_lifetime) {
            $expiry = $this->default_lifetime;
            $expiry = $now + ($expiry > ($this->max_lifetime ?: $expiry) ? $this->max_lifetime : $expiry);
        } elseif ($this->max_lifetime && (null === $expiry || $expiry > $now + $this->max_lifetime)) {
            $expiry = $now + $this->max_lifetime;
        }
        if ($this->max_items) {
            unset($this->values[$key], $this->tags[$key]);
            // Iterate items and vacuum expired ones while we are at it
            foreach ($this->values as $k => $v) {
                if ($this->expiries[$k] > $now && \count($this->values) < $this->max_items) {
                    break;
                }
                unset($this->values[$k], $this->tags[$k], $this->expiries[$k]);
            }
        }
        $this->values[$key] = $value;
        $this->expiries[$key] = $expiry ?? \PHP_INT_MAX;
        if (null === $this->tags[$key] = $item["\x00*\x00newMetadata"][Cache_Item::METADATA_TAGS] ?? null) {
            unset($this->tags[$key]);
        }
        return true;
    }
    public function save_deferred(Cache_Item_Interface $item): bool
    {
        return $this->save($item);
    }
    public function commit(): bool
    {
        return true;
    }
    public function clear(string $prefix = ''): bool
    {
        if ('' !== $prefix) {
            $now = $this->get_current_time();
            foreach ($this->values as $key => $value) {
                if (!isset($this->expiries[$key]) || $this->expiries[$key] <= $now || str_starts_with((string) $key, $prefix)) {
                    unset($this->values[$key], $this->tags[$key], $this->expiries[$key]);
                }
            }
            return true;
        }
        foreach ($this->sub_pools as $pool) {
            $pool->clear();
        }
        $this->sub_pools = $this->values = $this->tags = $this->expiries = [];
        return true;
    }
    public function with_sub_namespace(string $namespace): static
    {
        Cache_Item::validate_key($namespace);
        $sub_pools = $this->sub_pools;
        if (isset($sub_pools[$namespace])) {
            return $sub_pools[$namespace];
        }
        $this->sub_pools = [];
        $clone = clone $this;
        $clone->clear();
        $sub_pools[$namespace] = $clone;
        $this->sub_pools = $sub_pools;
        return $clone;
    }
    /**
     * Returns all cached values, with cache miss as null.
     */
    public function get_values(): array
    {
        if (!$this->store_serialized) {
            return $this->values;
        }
        $values = $this->values;
        foreach ($values as $k => $v) {
            if (null === $v) {
                continue;
            }
            if ('N;' === $v) {
                continue;
            }
            if (!\is_string($v) || !isset($v[2]) || ':' !== $v[1]) {
                $values[$k] = serialize($v);
            }
        }
        return $values;
    }
    public function reset(): void
    {
        $this->clear();
    }
    public function __clone()
    {
        foreach ($this->sub_pools as $i => $pool) {
            $this->sub_pools[$i] = clone $pool;
        }
    }
    private function generate_items(array $keys, float $now, \Closure $f): \Generator
    {
        foreach ($keys as $i => $key) {
            if (!$is_hit = isset($this->expiries[$key]) && ($this->expiries[$key] > $now || !$this->delete_item($key))) {
                $value = null;
                if (!$this->max_items) {
                    // Track misses in non-LRU mode only
                    $this->values[$key] = null;
                }
            } else {
                if ($this->max_items) {
                    // Move the item last in the storage
                    $value = $this->values[$key];
                    unset($this->values[$key]);
                    $this->values[$key] = $value;
                }
                $value = $this->store_serialized ? $this->unfreeze($key, $is_hit) : $this->values[$key];
            }
            unset($keys[$i]);
            yield $key => $f($key, $value, $is_hit, $this->tags[$key] ?? null);
        }
        foreach ($keys as $key) {
            yield $key => $f($key, null, false);
        }
    }
    private function freeze($value, string $key): string|int|float|bool|array|\Unit_Enum|null
    {
        if (null === $value) {
            return 'N;';
        }
        if (\is_string($value)) {
            // Serialize strings if they could be confused with serialized objects or arrays
            if ('N;' === $value || isset($value[2]) && ':' === $value[1]) {
                return serialize($value);
            }
        } elseif (!\is_scalar($value)) {
            try {
                $serialized = serialize($value);
            } catch (\Exception $e) {
                if (!isset($this->expiries[$key])) {
                    unset($this->values[$key]);
                }
                $type = get_debug_type($value);
                $message = \sprintf('Failed to save key "{key}" of type %s: %s', $type, $e->get_message());
                Cache_Item::log($this->logger, $message, ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
                return null;
            }
            // Keep value serialized if it contains any objects or any internal references
            if ('C' === $serialized[0] || 'O' === $serialized[0] || preg_match('/;[OCRr]:[1-9]/', $serialized)) {
                return $serialized;
            }
        }
        return $value;
    }
    private function unfreeze(string $key, bool &$is_hit): mixed
    {
        if ('N;' === $value = $this->values[$key]) {
            return null;
        }
        if (\is_string($value) && isset($value[2]) && ':' === $value[1]) {
            try {
                $value = unserialize($value);
            } catch (\Exception $e) {
                Cache_Item::log($this->logger, 'Failed to unserialize key "{key}": ' . $e->get_message(), ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
                $value = false;
            }
            if (false === $value) {
                $value = null;
                $is_hit = false;
                if (!$this->max_items) {
                    $this->values[$key] = null;
                }
            }
        }
        return $value;
    }
    private function validate_keys(array $keys): bool
    {
        foreach ($keys as $key) {
            if (!\is_string($key) || !isset($this->expiries[$key])) {
                Cache_Item::validate_key($key);
            }
        }
        return true;
    }
    private function get_current_time(): float
    {
        return $this->clock?->now()->format('U.u') ?? microtime(true);
    }
}