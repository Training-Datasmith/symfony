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
use Psr\Cache\Cache_Item_Pool_Interface;
use Symfony\Component\Cache\Cache_Item;
use Symfony\Component\Cache\Pruneable_Interface;
use Symfony\Component\Cache\Resettable_Interface;
use Symfony\Component\Cache\Traits\Contracts_Trait;
use Symfony\Component\Cache\Traits\Proxy_Trait;
use Symfony\Contracts\Cache\Cache_Interface;
use Symfony\Contracts\Cache\Namespaced_Pool_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Proxy_Adapter implements Adapter_Interface, Namespaced_Pool_Interface, Cache_Interface, Pruneable_Interface, Resettable_Interface
{
    use Contracts_Trait;
    use Proxy_Trait;
    private string $namespace = '';
    private int $namespace_len;
    private string $pool_hash;
    private static \Closure $create_cache_item;
    private static \Closure $set_inner_item;
    public function __construct(Cache_Item_Pool_Interface $pool, string $namespace = '', private int $default_lifetime = 0)
    {
        if ('' !== $namespace) {
            if ($pool instanceof Namespaced_Pool_Interface) {
                $pool = $pool->with_sub_namespace($namespace);
                $this->namespace = $namespace = '';
            } else {
                \assert('' !== Cache_Item::validate_key($namespace));
                $this->namespace = $namespace;
            }
        }
        $this->pool = $pool;
        $this->pool_hash = spl_object_hash($pool);
        $this->namespace_len = \strlen($namespace);
        self::$create_cache_item ??= \Closure::bind(static function ($key, $inner_item, $pool_hash): \Symfony\Component\Cache\Cache_Item {
            $item = new Cache_Item();
            $item->key = $key;
            if (null === $inner_item) {
                return $item;
            }
            $item->value = $inner_item->get();
            $item->is_hit = $inner_item->is_hit();
            $item->inner_item = $inner_item;
            $item->pool_hash = $pool_hash;
            if (!$item->unpack() && $inner_item instanceof Cache_Item) {
                $item->metadata = $inner_item->metadata;
            }
            $inner_item->set(null);
            return $item;
        }, null, Cache_Item::class);
        self::$set_inner_item ??= \Closure::bind(static function (Cache_Item_Interface $inner_item, Cache_Item $item, $expiry = null): void {
            $inner_item->set($item->pack());
            $inner_item->expires_at($expiry ?? $item->expiry ? \DateTimeImmutable::create_from_format('U.u', \sprintf('%.6F', $expiry ?? $item->expiry)) : null);
        }, null, Cache_Item::class);
    }
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        if (!$this->pool instanceof Cache_Interface) {
            return $this->do_get($this, $key, $callback, $beta, $metadata);
        }
        return $this->pool->get($this->get_id($key), function ($inner_item, bool &$save) use ($key, $callback) {
            $item = (self::$create_cache_item)($key, $inner_item, $this->pool_hash);
            $item->set($value = $callback($item, $save));
            (self::$set_inner_item)($inner_item, $item);
            return $value;
        }, $beta, $metadata);
    }
    public function get_item(mixed $key): Cache_Item
    {
        $item = $this->pool->get_item($this->get_id($key));
        return (self::$create_cache_item)($key, $item, $this->pool_hash);
    }
    public function get_items(array $keys = []): iterable
    {
        if ($this->namespace_len) {
            foreach ($keys as $i => $key) {
                $keys[$i] = $this->get_id($key);
            }
        }
        return $this->generate_items($this->pool->get_items($keys));
    }
    public function has_item(mixed $key): bool
    {
        return $this->pool->has_item($this->get_id($key));
    }
    public function clear(string $prefix = ''): bool
    {
        if ($this->pool instanceof Adapter_Interface) {
            return $this->pool->clear($this->namespace . $prefix);
        }
        return $this->pool->clear();
    }
    public function delete_item(mixed $key): bool
    {
        return $this->pool->delete_item($this->get_id($key));
    }
    public function delete_items(array $keys): bool
    {
        if ($this->namespace_len) {
            foreach ($keys as $i => $key) {
                $keys[$i] = $this->get_id($key);
            }
        }
        return $this->pool->delete_items($keys);
    }
    public function save(Cache_Item_Interface $item): bool
    {
        return $this->do_save($item, __FUNCTION__);
    }
    public function save_deferred(Cache_Item_Interface $item): bool
    {
        return $this->do_save($item, __FUNCTION__);
    }
    public function commit(): bool
    {
        return $this->pool->commit();
    }
    public function with_sub_namespace(string $namespace): static
    {
        $clone = clone $this;
        if ($clone->pool instanceof Namespaced_Pool_Interface) {
            $clone->pool = $clone->pool->with_sub_namespace($namespace);
        } else {
            $clone->namespace .= Cache_Item::validate_key($namespace);
            $clone->namespace_len = \strlen($clone->namespace);
        }
        return $clone;
    }
    private function do_save(Cache_Item_Interface $item, string $method): bool
    {
        if (!$item instanceof Cache_Item) {
            return false;
        }
        $cast_item = (array) $item;
        if (null === $cast_item["\x00*\x00expiry"] && 0 < $this->default_lifetime) {
            $cast_item["\x00*\x00expiry"] = microtime(true) + $this->default_lifetime;
        }
        if ($cast_item["\x00*\x00poolHash"] === $this->pool_hash && $cast_item["\x00*\x00innerItem"]) {
            $inner_item = $cast_item["\x00*\x00innerItem"];
        } elseif ($this->pool instanceof Adapter_Interface) {
            // this is an optimization specific for AdapterInterface implementations
            // so we can save a round-trip to the backend by just creating a new item
            $inner_item = (self::$create_cache_item)($this->namespace . $cast_item["\x00*\x00key"], null, $this->pool_hash);
        } else {
            $inner_item = $this->pool->get_item($this->namespace . $cast_item["\x00*\x00key"]);
        }
        (self::$set_inner_item)($inner_item, $item, $cast_item["\x00*\x00expiry"]);
        return $this->pool->{$method}($inner_item);
    }
    private function generate_items(iterable $items): \Generator
    {
        $f = self::$create_cache_item;
        foreach ($items as $key => $item) {
            if ($this->namespace_len) {
                $key = substr((string) $key, $this->namespace_len);
            }
            yield $key => $f($key, $item, $this->pool_hash);
        }
    }
    private function get_id(mixed $key): string
    {
        \assert('' !== Cache_Item::validate_key($key));
        return $this->namespace . $key;
    }
}