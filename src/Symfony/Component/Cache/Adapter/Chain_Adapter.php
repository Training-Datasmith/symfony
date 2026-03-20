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
use Symfony\Component\Cache\Exception\BadMethodCallException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Pruneable_Interface;
use Symfony\Component\Cache\Resettable_Interface;
use Symfony\Component\Cache\Traits\Contracts_Trait;
use Symfony\Contracts\Cache\Cache_Interface;
use Symfony\Contracts\Cache\Namespaced_Pool_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Chains several adapters together.
 *
 * Cached items are fetched from the first adapter having them in its data store.
 * They are saved and deleted in all adapters at once.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Chain_Adapter implements Adapter_Interface, Cache_Interface, Namespaced_Pool_Interface, Pruneable_Interface, Resettable_Interface
{
    use Contracts_Trait;
    private array $adapters = [];
    private int $adapter_count;
    private static \Closure $sync_item;
    /**
     * @param CacheItemPoolInterface[] $adapters        The ordered list of adapters used to fetch cached items
     * @param int                      $defaultLifetime The default lifetime of items propagated from lower adapters to upper ones
     */
    public function __construct(array $adapters, private int $default_lifetime = 0)
    {
        if (!$adapters) {
            throw new InvalidArgumentException('At least one adapter must be specified.');
        }
        foreach ($adapters as $adapter) {
            if (!$adapter instanceof Cache_Item_Pool_Interface) {
                throw new InvalidArgumentException(\sprintf('The class "%s" does not implement the "%s" interface.', get_debug_type($adapter), Cache_Item_Pool_Interface::class));
            }
            if ('cli' === \PHP_SAPI && $adapter instanceof Apcu_Adapter && !filter_var(\ini_get('apc.enable_cli'), \FILTER_VALIDATE_BOOL)) {
                continue;
                // skip putting APCu in the chain when the backend is disabled
            }
            if ($adapter instanceof Adapter_Interface) {
                $this->adapters[] = $adapter;
            } else {
                $this->adapters[] = new Proxy_Adapter($adapter);
            }
        }
        $this->adapter_count = \count($this->adapters);
        self::$sync_item ??= \Closure::bind(static function ($source_item, $item, $default_lifetime, $source_metadata = null) {
            $source_item->is_taggable = false;
            $source_metadata ??= $source_item->metadata;
            $item->value = $source_item->value;
            $item->is_hit = $source_item->is_hit;
            $item->metadata = $item->new_metadata = $source_item->metadata = $source_metadata;
            if (isset($item->metadata[Cache_Item::METADATA_EXPIRY])) {
                $item->expires_at(\DateTimeImmutable::create_from_format('U.u', \sprintf('%.6F', $item->metadata[Cache_Item::METADATA_EXPIRY])));
            } elseif (0 < $default_lifetime) {
                $item->expires_after($default_lifetime);
            }
            return $item;
        }, null, Cache_Item::class);
    }
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        $do_save = true;
        $callback = static function (Cache_Item $item, bool &$save) use ($callback, &$do_save) {
            $value = $callback($item, $save);
            $do_save = $save;
            return $value;
        };
        $wrap = function (?Cache_Item $item = null, bool &$save = true) use ($key, $callback, $beta, &$wrap, &$do_save, &$metadata) {
            static $last_item;
            static $i = 0;
            $adapter = $this->adapters[$i];
            if (isset($this->adapters[++$i])) {
                $callback = $wrap;
                $beta = \INF === $beta ? \INF : 0;
            }
            if ($adapter instanceof Cache_Interface && $i !== $this->adapter_count) {
                $value = $adapter->get($key, $callback, $beta, $metadata);
            } else {
                $value = $this->do_get($adapter, $key, $callback, $beta, $metadata);
            }
            if (null !== $item) {
                (self::$sync_item)($last_item ??= $item, $item, $this->default_lifetime, $metadata);
            }
            $save = $do_save;
            return $value;
        };
        return $wrap();
    }
    public function get_item(mixed $key): Cache_Item
    {
        $sync_item = self::$sync_item;
        $misses = [];
        foreach ($this->adapters as $i => $adapter) {
            $item = $adapter->get_item($key);
            if ($item->is_hit()) {
                while (0 <= --$i) {
                    $this->adapters[$i]->save($sync_item($item, $misses[$i], $this->default_lifetime));
                }
                return $item;
            }
            $misses[$i] = $item;
        }
        return $item;
    }
    public function get_items(array $keys = []): iterable
    {
        return $this->generate_items($this->adapters[0]->get_items($keys), 0);
    }
    private function generate_items(iterable $items, int $adapter_index): \Generator
    {
        $missing = [];
        $misses = [];
        $next_adapter_index = $adapter_index + 1;
        $next_adapter = $this->adapters[$next_adapter_index] ?? null;
        foreach ($items as $k => $item) {
            if (!$next_adapter || $item->is_hit()) {
                yield $k => $item;
            } else {
                $missing[] = $k;
                $misses[$k] = $item;
            }
        }
        if ($missing) {
            $sync_item = self::$sync_item;
            $adapter = $this->adapters[$adapter_index];
            $items = $this->generate_items($next_adapter->get_items($missing), $next_adapter_index);
            foreach ($items as $k => $item) {
                if ($item->is_hit()) {
                    $adapter->save($sync_item($item, $misses[$k], $this->default_lifetime));
                }
                yield $k => $item;
            }
        }
    }
    public function has_item(mixed $key): bool
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->has_item($key)) {
                return true;
            }
        }
        return false;
    }
    public function clear(string $prefix = ''): bool
    {
        $cleared = true;
        $i = $this->adapter_count;
        while ($i--) {
            if ($this->adapters[$i] instanceof Adapter_Interface) {
                $cleared = $this->adapters[$i]->clear($prefix) && $cleared;
            } else {
                $cleared = $this->adapters[$i]->clear() && $cleared;
            }
        }
        return $cleared;
    }
    public function delete_item(mixed $key): bool
    {
        $deleted = true;
        $i = $this->adapter_count;
        while ($i--) {
            $deleted = $this->adapters[$i]->delete_item($key) && $deleted;
        }
        return $deleted;
    }
    public function delete_items(array $keys): bool
    {
        $deleted = true;
        $i = $this->adapter_count;
        while ($i--) {
            $deleted = $this->adapters[$i]->delete_items($keys) && $deleted;
        }
        return $deleted;
    }
    public function save(Cache_Item_Interface $item): bool
    {
        $saved = true;
        $i = $this->adapter_count;
        while ($i--) {
            $saved = $this->adapters[$i]->save($item) && $saved;
        }
        return $saved;
    }
    public function save_deferred(Cache_Item_Interface $item): bool
    {
        $saved = true;
        $i = $this->adapter_count;
        while ($i--) {
            $saved = $this->adapters[$i]->save_deferred($item) && $saved;
        }
        return $saved;
    }
    public function commit(): bool
    {
        $committed = true;
        $i = $this->adapter_count;
        while ($i--) {
            $committed = $this->adapters[$i]->commit() && $committed;
        }
        return $committed;
    }
    public function prune(): bool
    {
        $pruned = true;
        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof Pruneable_Interface) {
                $pruned = $adapter->prune() && $pruned;
            }
        }
        return $pruned;
    }
    public function with_sub_namespace(string $namespace): static
    {
        $clone = clone $this;
        $adapters = [];
        foreach ($this->adapters as $adapter) {
            if (!$adapter instanceof Namespaced_Pool_Interface) {
                throw new BadMethodCallException('All adapters must implement NamespacedPoolInterface to support namespaces.');
            }
            $adapters[] = $adapter->with_sub_namespace($namespace);
        }
        $clone->adapters = $adapters;
        return $clone;
    }
    public function reset(): void
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof Reset_Interface) {
                $adapter->reset();
            }
        }
    }
}