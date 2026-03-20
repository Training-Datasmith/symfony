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
namespace Symfony\Component\Cache\Traits;

use Psr\Cache\Cache_Item_Interface;
use Psr\Log\Logger_Aware_Trait;
use Symfony\Component\Cache\Cache_Item;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait Abstract_Adapter_Trait
{
    use Logger_Aware_Trait;
    /**
     * needs to be set by class, signature is function(string <key>, mixed <value>, bool <isHit>).
     */
    private static \Closure $create_cache_item;
    /**
     * needs to be set by class, signature is function(array <deferred>, string <namespace>, array <&expiredIds>).
     */
    private static \Closure $merge_by_lifetime;
    private readonly string $root_namespace;
    private string $namespace = '';
    private int $default_lifetime;
    private string $namespace_version = '';
    private bool $versioning_is_enabled = false;
    private array $deferred = [];
    private array $ids = [];
    /**
     * The maximum length to enforce for identifiers or null when no limit applies.
     */
    protected ?int $max_id_length = null;
    /**
     * Fetches several cache items.
     *
     * @param array $ids The cache identifiers to fetch
     */
    abstract protected function do_fetch(array $ids): iterable;
    /**
     * Confirms if the cache contains specified cache item.
     *
     * @param string $id The identifier for which to check existence
     */
    abstract protected function do_have(string $id): bool;
    /**
     * Deletes all items in the pool.
     *
     * @param string $namespace The prefix used for all identifiers managed by this pool
     */
    abstract protected function do_clear(string $namespace): bool;
    /**
     * Removes multiple items from the pool.
     *
     * @param array $ids An array of identifiers that should be removed from the pool
     */
    abstract protected function do_delete(array $ids): bool;
    /**
     * Persists several cache items immediately.
     *
     * @param array $values   The values to cache, indexed by their cache identifier
     * @param int   $lifetime The lifetime of the cached values, 0 for persisting until manual cleaning
     *
     * @return array|bool The identifiers that failed to be cached or a boolean stating if caching succeeded or not
     */
    abstract protected function do_save(array $values, int $lifetime): array|bool;
    public function has_item(mixed $key): bool
    {
        $id = $this->get_id($key);
        if (isset($this->deferred[$key])) {
            $this->commit();
        }
        try {
            return $this->do_have($id);
        } catch (\Exception $e) {
            Cache_Item::log($this->logger, 'Failed to check if key "{key}" is cached: ' . $e->get_message(), ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
            return false;
        }
    }
    public function clear(string $prefix = ''): bool
    {
        $this->deferred = [];
        if ($cleared = $this->versioning_is_enabled) {
            $root_namespace = $this->root_namespace ??= $this->namespace;
            if ('' === $namespace_version_to_clear = $this->namespace_version) {
                foreach ($this->do_fetch([static::NS_SEPARATOR . $root_namespace]) as $v) {
                    $namespace_version_to_clear = $v;
                }
            }
            $namespace_to_clear = $root_namespace . $namespace_version_to_clear;
            $namespace_version = self::format_namespace_version(mt_rand());
            try {
                $e = $this->do_save([static::NS_SEPARATOR . $root_namespace => $namespace_version], 0);
            } catch (\Exception $e) {
            }
            if (true !== $e && [] !== $e) {
                $cleared = false;
                $message = 'Failed to save the new namespace' . ($e instanceof \Exception ? ': ' . $e->get_message() : '.');
                Cache_Item::log($this->logger, $message, ['exception' => $e instanceof \Exception ? $e : null, 'cache-adapter' => get_debug_type($this)]);
            } else {
                $this->namespace_version = $namespace_version;
                $this->ids = [];
            }
        } else {
            $namespace_to_clear = $this->namespace . $prefix;
        }
        try {
            if ($this->do_clear($namespace_to_clear)) {
                return true;
            }
            return (bool) $cleared;
        } catch (\Exception $e) {
            Cache_Item::log($this->logger, 'Failed to clear the cache: ' . $e->get_message(), ['exception' => $e, 'cache-adapter' => get_debug_type($this)]);
            return false;
        }
    }
    public function delete_item(mixed $key): bool
    {
        return $this->delete_items([$key]);
    }
    public function delete_items(array $keys): bool
    {
        $ids = [];
        foreach ($keys as $key) {
            $ids[$key] = $this->get_id($key);
            unset($this->deferred[$key]);
        }
        try {
            if ($this->do_delete($ids)) {
                return true;
            }
        } catch (\Exception) {
        }
        $ok = true;
        // When bulk-delete failed, retry each item individually
        foreach ($ids as $key => $id) {
            try {
                $e = null;
                if ($this->do_delete([$id])) {
                    continue;
                }
            } catch (\Exception $e) {
            }
            $message = 'Failed to delete key "{key}"' . ($e instanceof \Exception ? ': ' . $e->get_message() : '.');
            Cache_Item::log($this->logger, $message, ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
            $ok = false;
        }
        return $ok;
    }
    public function get_item(mixed $key): Cache_Item
    {
        $id = $this->get_id($key);
        if (isset($this->deferred[$key])) {
            $this->commit();
        }
        $is_hit = false;
        $value = null;
        try {
            foreach ($this->do_fetch([$id]) as $value) {
                $is_hit = true;
            }
            return (self::$create_cache_item)($key, $value, $is_hit);
        } catch (\Exception $e) {
            Cache_Item::log($this->logger, 'Failed to fetch key "{key}": ' . $e->get_message(), ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
        }
        return (self::$create_cache_item)($key, null, false);
    }
    public function get_items(array $keys = []): iterable
    {
        $ids = [];
        $commit = false;
        foreach ($keys as $key) {
            $ids[] = $this->get_id($key);
            $commit = $commit || isset($this->deferred[$key]);
        }
        if ($commit) {
            $this->commit();
        }
        try {
            $items = $this->do_fetch($ids);
        } catch (\Exception $e) {
            Cache_Item::log($this->logger, 'Failed to fetch items: ' . $e->get_message(), ['keys' => $keys, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
            $items = [];
        }
        $ids = array_combine($ids, $keys);
        return $this->generate_items($items, $ids);
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
    public function with_sub_namespace(string $namespace): static
    {
        $this->root_namespace ??= $this->namespace;
        $clone = clone $this;
        $clone->namespace .= Cache_Item::validate_key($namespace) . static::NS_SEPARATOR;
        return $clone;
    }
    /**
     * Enables/disables versioning of items.
     *
     * When versioning is enabled, clearing the cache is atomic and doesn't require listing existing keys to proceed,
     * but old keys may need garbage collection and extra round-trips to the back-end are required.
     *
     * Calling this method also clears the memoized namespace version and thus forces a resynchronization of it.
     *
     * @return bool the previous state of versioning
     */
    public function enable_versioning(bool $enable = true): bool
    {
        $was_enabled = $this->versioning_is_enabled;
        $this->versioning_is_enabled = $enable;
        $this->namespace_version = '';
        $this->ids = [];
        return $was_enabled;
    }
    public function reset(): void
    {
        if ($this->deferred) {
            $this->commit();
        }
        $this->namespace_version = '';
        $this->ids = [];
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
        if ($this->deferred) {
            $this->commit();
        }
    }
    private function generate_items(iterable $items, array &$keys): \Generator
    {
        $f = self::$create_cache_item;
        try {
            foreach ($items as $id => $value) {
                if (!isset($keys[$id])) {
                    throw new InvalidArgumentException(\sprintf('Could not match value id "%s" to keys "%s".', $id, implode('", "', $keys)));
                }
                $key = $keys[$id];
                unset($keys[$id]);
                yield $key => $f($key, $value, true);
            }
        } catch (\Exception $e) {
            Cache_Item::log($this->logger, 'Failed to fetch items: ' . $e->get_message(), ['keys' => array_values($keys), 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
        }
        foreach ($keys as $key) {
            yield $key => $f($key, null, false);
        }
    }
    /**
     * @internal
     */
    protected function get_id(mixed $key, ?string $namespace = null): string
    {
        $namespace ??= $this->namespace;
        if ('' !== $this->namespace_version) {
            $namespace .= $this->namespace_version;
        } elseif ($this->versioning_is_enabled) {
            $root_namespace = $this->root_namespace ??= $this->namespace;
            $this->ids = [];
            $this->namespace_version = '1' . static::NS_SEPARATOR;
            try {
                foreach ($this->do_fetch([static::NS_SEPARATOR . $root_namespace]) as $v) {
                    $this->namespace_version = $v;
                }
                $e = true;
                if ('1' . static::NS_SEPARATOR === $this->namespace_version) {
                    $this->namespace_version = self::format_namespace_version(time());
                    $e = $this->do_save([static::NS_SEPARATOR . $root_namespace => $this->namespace_version], 0);
                }
            } catch (\Exception $e) {
            }
            if (true !== $e && [] !== $e) {
                $message = 'Failed to save the new namespace' . ($e instanceof \Exception ? ': ' . $e->get_message() : '.');
                Cache_Item::log($this->logger, $message, ['exception' => $e instanceof \Exception ? $e : null, 'cache-adapter' => get_debug_type($this)]);
            }
            $namespace .= $this->namespace_version;
        }
        if (\is_string($key) && isset($this->ids[$key])) {
            $id = $this->ids[$key];
        } else {
            \assert('' !== Cache_Item::validate_key($key));
            $this->ids[$key] = $key;
            if (\count($this->ids) > 1000) {
                $this->ids = \array_slice($this->ids, 500, null, true);
                // stop memory leak if there are many keys
            }
            if (null === $this->max_id_length) {
                return $namespace . $key;
            }
            if (\strlen($id = $namespace . $key) <= $this->max_id_length) {
                return $id;
            }
            // Use xxh128 to favor speed over security, which is not an issue here
            $this->ids[$key] = $id = substr_replace(base64_encode(hash('xxh128', (string) $key, true)), static::NS_SEPARATOR, -(\strlen($this->namespace_version) + 2));
        }
        $id = $namespace . $id;
        if (null !== $this->max_id_length && \strlen($id) > $this->max_id_length) {
            return base64_encode(hash('xxh128', $id, true));
        }
        return $id;
    }
    /**
     * @internal
     */
    public static function handle_unserialize_callback(string $class): never
    {
        throw new \DomainException('Class not found: ' . $class);
    }
    private static function format_namespace_version(int $value): string
    {
        return strtr(substr_replace(base64_encode(pack('V', $value)), static::NS_SEPARATOR, 5), '/', '_');
    }
}