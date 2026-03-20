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
use Psr\Cache\InvalidArgumentException;
use Psr\Log\Logger_Aware_Interface;
use Psr\Log\Logger_Aware_Trait;
use Symfony\Component\Cache\Cache_Item;
use Symfony\Component\Cache\Exception\BadMethodCallException;
use Symfony\Component\Cache\Pruneable_Interface;
use Symfony\Component\Cache\Resettable_Interface;
use Symfony\Component\Cache\Traits\Contracts_Trait;
use Symfony\Contracts\Cache\Namespaced_Pool_Interface;
use Symfony\Contracts\Cache\Tag_Aware_Cache_Interface;
/**
 * Implements simple and robust tag-based invalidation suitable for use with volatile caches.
 *
 * This adapter works by storing a version for each tags. When saving an item, it is stored together with its tags and
 * their corresponding versions. When retrieving an item, those tag versions are compared to the current version of
 * each tags. Invalidation is achieved by deleting tags, thereby ensuring that their versions change even when the
 * storage is out of space. When versions of non-existing tags are requested for item commits, this adapter assigns a
 * new random version to them.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Sergey Belyshkin <sbelyshkin@gmail.com>
 */
class Tag_Aware_Adapter implements Tag_Aware_Adapter_Interface, Tag_Aware_Cache_Interface, Namespaced_Pool_Interface, Pruneable_Interface, Resettable_Interface, Logger_Aware_Interface
{
    use Contracts_Trait;
    use Logger_Aware_Trait;
    public const TAGS_PREFIX = "\x01tags\x01";
    private array $deferred = [];
    private Adapter_Interface $tags;
    private array $known_tag_versions = [];
    private static \Closure $set_cache_item_tags;
    private static \Closure $set_tag_versions;
    private static \Closure $get_tags_by_key;
    private static \Closure $save_tags;
    public function __construct(private Adapter_Interface $pool, ?Adapter_Interface $tags_pool = null, private float $known_tag_versions_ttl = 0.15)
    {
        $this->tags = $tags_pool ?? $this->pool;
        self::$set_cache_item_tags ??= \Closure::bind(static function (array $items, array $item_tags): array {
            foreach ($items as $key => $item) {
                $item->is_taggable = true;
                if (isset($item_tags[$key])) {
                    $tags = array_keys($item_tags[$key]);
                    $item->metadata[Cache_Item::METADATA_TAGS] = array_combine($tags, $tags);
                } else {
                    $item->value = null;
                    $item->is_hit = false;
                    $item->metadata = [];
                }
            }
            return $items;
        }, null, Cache_Item::class);
        self::$set_tag_versions ??= \Closure::bind(static function (array $items, array $tag_versions): void {
            foreach ($items as $item) {
                $item->new_metadata[Cache_Item::METADATA_TAGS] = array_intersect_key($tag_versions, $item->new_metadata[Cache_Item::METADATA_TAGS] ?? []);
            }
        }, null, Cache_Item::class);
        self::$get_tags_by_key ??= \Closure::bind(static function ($deferred): array {
            $tags_by_key = [];
            foreach ($deferred as $key => $item) {
                $tags_by_key[$key] = $item->new_metadata[Cache_Item::METADATA_TAGS] ?? [];
                $item->metadata = $item->new_metadata;
            }
            return $tags_by_key;
        }, null, Cache_Item::class);
        self::$save_tags ??= \Closure::bind(static function (Adapter_Interface $tags_adapter, array $tags) {
            ksort($tags);
            foreach ($tags as $v) {
                $v->expiry = 0;
                $tags_adapter->save_deferred($v);
            }
            return $tags_adapter->commit();
        }, null, Cache_Item::class);
    }
    public function invalidate_tags(array $tags): bool
    {
        $ids = [];
        foreach ($tags as $tag) {
            \assert('' !== Cache_Item::validate_key($tag));
            unset($this->known_tag_versions[$tag]);
            $ids[] = $tag . static::TAGS_PREFIX;
        }
        return !$tags || $this->tags->delete_items($ids);
    }
    public function has_item(mixed $key): bool
    {
        return $this->get_item($key)->is_hit();
    }
    public function get_item(mixed $key): Cache_Item
    {
        foreach ($this->get_items([$key]) as $item) {
            return $item;
        }
    }
    public function get_items(array $keys = []): iterable
    {
        $tag_keys = [];
        $commit = false;
        foreach ($keys as $key) {
            if ('' !== $key && \is_string($key)) {
                $commit = $commit || isset($this->deferred[$key]);
            }
        }
        if ($commit) {
            $this->commit();
        }
        try {
            $items = $this->pool->get_items($keys);
        } catch (InvalidArgumentException $e) {
            $this->pool->get_items($keys);
            // Should throw an exception
            throw $e;
        }
        $buffered_items = $item_tags = [];
        foreach ($items as $key => $item) {
            if (null !== $tags = $item->get_metadata()[Cache_Item::METADATA_TAGS] ?? null) {
                $item_tags[$key] = $tags;
            }
            $buffered_items[$key] = $item;
            if (null === $tags) {
                $key = "\x00tags\x00" . $key;
                $tag_keys[$key] = $key;
                // BC with pools populated before v6.1
            }
        }
        if ($tag_keys) {
            foreach ($this->pool->get_items($tag_keys) as $key => $item) {
                if ($item->is_hit()) {
                    $item_tags[substr($key, \strlen("\x00tags\x00"))] = $item->get() ?: [];
                }
            }
        }
        $tag_versions = $this->get_tag_versions($item_tags, false);
        foreach ($item_tags as $key => $tags) {
            foreach ($tags as $tag => $version) {
                if ($tag_versions[$tag] !== $version) {
                    unset($item_tags[$key]);
                    continue 2;
                }
            }
        }
        return (self::$set_cache_item_tags)($buffered_items, $item_tags);
    }
    public function clear(string $prefix = ''): bool
    {
        if ('' !== $prefix) {
            foreach ($this->deferred as $key => $item) {
                if (str_starts_with((string) $key, $prefix)) {
                    unset($this->deferred[$key]);
                }
            }
            return $this->pool->clear($prefix);
        }
        $this->deferred = [];
        return $this->pool->clear();
    }
    public function delete_item(mixed $key): bool
    {
        return $this->delete_items([$key]);
    }
    public function delete_items(array $keys): bool
    {
        foreach ($keys as $key) {
            if ('' !== $key && \is_string($key)) {
                $keys[] = "\x00tags\x00" . $key;
                // BC with pools populated before v6.1
            }
        }
        return $this->pool->delete_items($keys);
    }
    public function save(Cache_Item_Interface $item): bool
    {
        if (!$item instanceof Cache_Item) {
            return false;
        }
        $this->deferred[$item->get_key()] = $item;
        return $this->commit();
    }
    public function save_deferred(Cache_Item_Interface $item): bool
    {
        if (!$item instanceof Cache_Item) {
            return false;
        }
        $this->deferred[$item->get_key()] = $item;
        return true;
    }
    public function commit(): bool
    {
        if (!$items = $this->deferred) {
            return true;
        }
        $tag_versions = $this->get_tag_versions((self::$get_tags_by_key)($items), true);
        (self::$set_tag_versions)($items, $tag_versions);
        $ok = true;
        foreach ($items as $key => $item) {
            if ($this->pool->save_deferred($item)) {
                unset($this->deferred[$key]);
            } else {
                $ok = false;
            }
        }
        $ok = $this->pool->commit() && $ok;
        $tag_versions = array_keys($tag_versions);
        (self::$set_tag_versions)($items, array_combine($tag_versions, $tag_versions));
        return $ok;
    }
    /**
     * @throws BadMethodCallException When the item pool is not a NamespacedPoolInterface
     */
    public function with_sub_namespace(string $namespace): static
    {
        if (!$this->pool instanceof Namespaced_Pool_Interface) {
            throw new BadMethodCallException(\sprintf('Cannot call "%s::withSubNamespace()": this class doesn\'t implement "%s".', get_debug_type($this->pool), Namespaced_Pool_Interface::class));
        }
        $known_tag_versions =& $this->known_tag_versions;
        // ensures clones share the same array
        $clone = clone $this;
        $clone->deferred = [];
        $clone->pool = $this->pool->with_sub_namespace($namespace);
        return $clone;
    }
    public function prune(): bool
    {
        return $this->pool instanceof Pruneable_Interface && $this->pool->prune();
    }
    public function reset(): void
    {
        $this->commit();
        $this->known_tag_versions = [];
        $this->pool instanceof Resettable_Interface && $this->pool->reset();
        $this->tags instanceof Resettable_Interface && $this->tags->reset();
    }
    public function __serialize(): array
    {
        throw new \BadMethodCallException('Cannot serialize ' . self::class);
    }
    public function __unserialize(array $data): void
    {
        throw new \BadMethodCallException('Cannot unserialize ' . self::class);
    }
    public function __destruct()
    {
        $this->commit();
    }
    private function get_tag_versions(array $tags_by_key, bool $persist_tags): array
    {
        $tag_versions = [];
        $fetch_tag_versions = $persist_tags;
        foreach ($tags_by_key as $tags) {
            $tag_versions += $tags;
            if ($fetch_tag_versions) {
                continue;
            }
            foreach ($tags as $tag => $version) {
                if ($tag_versions[$tag] !== $version) {
                    $fetch_tag_versions = true;
                }
            }
        }
        if (!$tag_versions) {
            return [];
        }
        $now = microtime(true);
        $tags = [];
        foreach ($tag_versions as $tag => $version) {
            $tags[$tag . static::TAGS_PREFIX] = $tag;
            $known_tag_version = $this->known_tag_versions[$tag] ?? [0, null];
            if ($fetch_tag_versions || $now > $known_tag_version[0] || $known_tag_version[1] !== $version) {
                // reuse previously fetched tag versions until the expiration
                $fetch_tag_versions = true;
            }
        }
        if (!$fetch_tag_versions) {
            return $tag_versions;
        }
        $new_tags = [];
        $new_version = null;
        $expiration = $now + $this->known_tag_versions_ttl;
        foreach ($this->tags->get_items(array_keys($tags)) as $tag => $version) {
            unset($this->known_tag_versions[$tag = $tags[$tag]]);
            // update FIFO
            if (null !== $tag_versions[$tag] = $version->get()) {
                $this->known_tag_versions[$tag] = [$expiration, $tag_versions[$tag]];
            } elseif ($persist_tags) {
                $new_tags[$tag] = $version->set($new_version ??= random_bytes(6));
                $tag_versions[$tag] = $new_version;
                $this->known_tag_versions[$tag] = [$expiration, $new_version];
            }
        }
        if ($new_tags) {
            (self::$save_tags)($this->tags, $new_tags);
        }
        while ($now > ($this->known_tag_versions[$tag = array_key_first($this->known_tag_versions) ?? ''][0] ?? \INF)) {
            unset($this->known_tag_versions[$tag]);
        }
        return $tag_versions;
    }
}