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
namespace Symfony\Component\Cache;

use Psr\Cache\Cache_Exception as Psr6CacheException;
use Psr\Cache\Cache_Item_Pool_Interface;
use Psr\Simple_Cache\Cache_Interface;
use Symfony\Component\Cache\Adapter\Adapter_Interface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Traits\Proxy_Trait;
/**
 * Turns a PSR-6 cache into a PSR-16 one.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Psr16Cache implements Cache_Interface, Pruneable_Interface, Resettable_Interface
{
    use Proxy_Trait;
    private ?\Closure $create_cache_item = null;
    private ?Cache_Item $cache_item_prototype = null;
    private static \Closure $pack_cache_item;
    public function __construct(Cache_Item_Pool_Interface $pool)
    {
        $this->pool = $pool;
        if (!$pool instanceof Adapter_Interface) {
            return;
        }
        $cache_item_prototype =& $this->cache_item_prototype;
        $create_cache_item = \Closure::bind(static function ($key, $value, $allow_int = false) use (&$cache_item_prototype): \Symfony\Component\Cache\Cache_Item {
            $item = clone $cache_item_prototype;
            $item->pool_hash = $item->inner_item = null;
            if ($allow_int && \is_int($key)) {
                $item->key = (string) $key;
            } else {
                \assert('' !== Cache_Item::validate_key($key));
                $item->key = $key;
            }
            $item->value = $value;
            $item->is_hit = false;
            return $item;
        }, null, Cache_Item::class);
        $this->create_cache_item = function ($key, $value, $allow_int = false) use ($create_cache_item) {
            if (null === $this->cache_item_prototype) {
                $this->get($allow_int && \is_int($key) ? (string) $key : $key);
            }
            $this->create_cache_item = $create_cache_item;
            return $create_cache_item($key, null, $allow_int)->set($value);
        };
        self::$pack_cache_item ??= \Closure::bind(static function (Cache_Item $item) {
            $item->new_metadata = $item->metadata;
            return $item->pack();
        }, null, Cache_Item::class);
    }
    public function get($key, $default = null): mixed
    {
        try {
            $item = $this->pool->get_item($key);
        } catch (Psr6cache_Exception $e) {
            throw new InvalidArgumentException($e->get_message(), $e->get_code(), $e);
        }
        if (null === $this->cache_item_prototype) {
            $this->cache_item_prototype = clone $item;
            $this->cache_item_prototype->set(null);
        }
        return $item->is_hit() ? $item->get() : $default;
    }
    public function set($key, $value, $ttl = null): bool
    {
        try {
            if (null !== $f = $this->create_cache_item) {
                $item = $f($key, $value);
            } else {
                $item = $this->pool->get_item($key)->set($value);
            }
        } catch (Psr6cache_Exception $e) {
            throw new InvalidArgumentException($e->get_message(), $e->get_code(), $e);
        }
        if (null !== $ttl) {
            $item->expires_after($ttl);
        }
        return $this->pool->save($item);
    }
    public function delete($key): bool
    {
        try {
            return $this->pool->delete_item($key);
        } catch (Psr6cache_Exception $e) {
            throw new InvalidArgumentException($e->get_message(), $e->get_code(), $e);
        }
    }
    public function clear(): bool
    {
        return $this->pool->clear();
    }
    public function get_multiple($keys, $default = null): iterable
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!\is_array($keys)) {
            throw new InvalidArgumentException(\sprintf('Cache keys must be array or Traversable, "%s" given.', get_debug_type($keys)));
        }
        try {
            $items = $this->pool->get_items($keys);
        } catch (Psr6cache_Exception $e) {
            throw new InvalidArgumentException($e->get_message(), $e->get_code(), $e);
        }
        $values = [];
        if (!$this->pool instanceof Adapter_Interface) {
            foreach ($items as $key => $item) {
                $values[$key] = $item->is_hit() ? $item->get() : $default;
            }
            return $values;
        }
        foreach ($items as $key => $item) {
            $values[$key] = $item->is_hit() ? (self::$pack_cache_item)($item) : $default;
        }
        return $values;
    }
    public function set_multiple($values, $ttl = null): bool
    {
        $values_is_array = \is_array($values);
        if (!$values_is_array && !$values instanceof \Traversable) {
            throw new InvalidArgumentException(\sprintf('Cache values must be array or Traversable, "%s" given.', get_debug_type($values)));
        }
        $items = [];
        try {
            if (null !== $f = $this->create_cache_item) {
                $values_is_array = false;
                foreach ($values as $key => $value) {
                    $items[$key] = $f($key, $value, true);
                }
            } elseif ($values_is_array) {
                $items = [];
                foreach ($values as $key => $value) {
                    $items[] = (string) $key;
                }
                $items = $this->pool->get_items($items);
            } else {
                foreach ($values as $key => $value) {
                    if (\is_int($key)) {
                        $key = (string) $key;
                    }
                    $items[$key] = $this->pool->get_item($key)->set($value);
                }
            }
        } catch (Psr6cache_Exception $e) {
            throw new InvalidArgumentException($e->get_message(), $e->get_code(), $e);
        }
        $ok = true;
        foreach ($items as $key => $item) {
            if ($values_is_array) {
                $item->set($values[$key]);
            }
            if (null !== $ttl) {
                $item->expires_after($ttl);
            }
            $ok = $this->pool->save_deferred($item) && $ok;
        }
        return $this->pool->commit() && $ok;
    }
    public function delete_multiple($keys): bool
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!\is_array($keys)) {
            throw new InvalidArgumentException(\sprintf('Cache keys must be array or Traversable, "%s" given.', get_debug_type($keys)));
        }
        try {
            return $this->pool->delete_items($keys);
        } catch (Psr6cache_Exception $e) {
            throw new InvalidArgumentException($e->get_message(), $e->get_code(), $e);
        }
    }
    public function has($key): bool
    {
        try {
            return $this->pool->has_item($key);
        } catch (Psr6cache_Exception $e) {
            throw new InvalidArgumentException($e->get_message(), $e->get_code(), $e);
        }
    }
}