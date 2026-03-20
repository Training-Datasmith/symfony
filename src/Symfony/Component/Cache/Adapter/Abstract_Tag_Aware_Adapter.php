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

use Psr\Log\Logger_Aware_Interface;
use Symfony\Component\Cache\Cache_Item;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Resettable_Interface;
use Symfony\Component\Cache\Traits\Abstract_Adapter_Trait;
use Symfony\Component\Cache\Traits\Contracts_Trait;
use Symfony\Contracts\Cache\Namespaced_Pool_Interface;
use Symfony\Contracts\Cache\Tag_Aware_Cache_Interface;
/**
 * Abstract for native TagAware adapters.
 *
 * To keep info on tags, the tags are both serialized as part of cache value and provided as tag ids
 * to Adapters on operations when needed for storage to doSave(), doDelete() & doInvalidate().
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author André Rømcke <andre.romcke+symfony@gmail.com>
 *
 * @internal
 */
abstract class Abstract_Tag_Aware_Adapter implements Tag_Aware_Adapter_Interface, Tag_Aware_Cache_Interface, Adapter_Interface, Namespaced_Pool_Interface, Logger_Aware_Interface, Resettable_Interface
{
    use Abstract_Adapter_Trait;
    use Contracts_Trait;
    /**
     * @internal
     */
    protected const NS_SEPARATOR = ':';
    private const TAGS_PREFIX = "\x01tags\x01";
    protected function __construct(string $namespace = '', int $default_lifetime = 0)
    {
        if ('' !== $namespace) {
            if (str_contains($namespace, (string) static::NS_SEPARATOR)) {
                if (str_contains($namespace, static::NS_SEPARATOR . static::NS_SEPARATOR)) {
                    throw new InvalidArgumentException(\sprintf('Cache namespace "%s" contains empty sub-namespace.', $namespace));
                }
                Cache_Item::validate_key(str_replace(static::NS_SEPARATOR, '', $namespace));
            } else {
                Cache_Item::validate_key($namespace);
            }
            $this->namespace = $namespace . static::NS_SEPARATOR;
        }
        $this->root_namespace = $this->namespace;
        $this->default_lifetime = $default_lifetime;
        if (null !== $this->max_id_length && \strlen($namespace) > $this->max_id_length - 24) {
            throw new InvalidArgumentException(\sprintf('Namespace must be %d chars max, %d given ("%s").', $this->max_id_length - 24, \strlen($namespace), $namespace));
        }
        self::$create_cache_item ??= \Closure::bind(static function ($key, $value, $is_hit): \Symfony\Component\Cache\Cache_Item {
            $item = new Cache_Item();
            $item->key = $key;
            $item->is_taggable = true;
            // If structure does not match what we expect return item as is (no value and not a hit)
            if (!\is_array($value) || !\array_key_exists('value', $value)) {
                return $item;
            }
            $item->is_hit = $is_hit;
            // Extract value, tags and meta data from the cache value
            $item->value = $value['value'];
            $item->metadata[Cache_Item::METADATA_TAGS] = isset($value['tags']) ? array_combine($value['tags'], $value['tags']) : [];
            if (isset($value['meta'])) {
                // For compactness these values are packed, & expiry is offset to reduce size
                $v = unpack('Ve/Nc', (string) $value['meta']);
                $item->metadata[Cache_Item::METADATA_EXPIRY] = $v['e'] + Cache_Item::METADATA_EXPIRY_OFFSET;
                $item->metadata[Cache_Item::METADATA_CTIME] = $v['c'];
            }
            return $item;
        }, null, Cache_Item::class);
        self::$merge_by_lifetime ??= \Closure::bind(static function ($deferred, &$expired_ids, $get_id, string $tag_prefix, $default_lifetime, $root_namespace): array {
            $by_lifetime = [];
            $now = microtime(true);
            $expired_ids = [];
            foreach ($deferred as $key => $item) {
                $key = (string) $key;
                if (null === $item->expiry) {
                    $ttl = 0 < $default_lifetime ? $default_lifetime : 0;
                } elseif (!$item->expiry) {
                    $ttl = 0;
                } elseif (0 >= $ttl = (int) (0.1 + $item->expiry - $now)) {
                    $expired_ids[] = $get_id($key);
                    continue;
                }
                // Store Value and Tags on the cache value
                if (isset(($metadata = $item->new_metadata)[Cache_Item::METADATA_TAGS])) {
                    $value = ['value' => $item->value, 'tags' => $metadata[Cache_Item::METADATA_TAGS]];
                    unset($metadata[Cache_Item::METADATA_TAGS]);
                } else {
                    $value = ['value' => $item->value, 'tags' => []];
                }
                if ($metadata) {
                    // For compactness, expiry and creation duration are packed, using magic numbers as separators
                    $value['meta'] = pack('VN', (int) (0.1 + $metadata[Cache_Item::METADATA_EXPIRY] - Cache_Item::METADATA_EXPIRY_OFFSET), $metadata[Cache_Item::METADATA_CTIME]);
                }
                // Extract tag changes, these should be removed from values in doSave()
                $value['tag-operations'] = ['add' => [], 'remove' => []];
                $old_tags = $item->metadata[Cache_Item::METADATA_TAGS] ?? [];
                foreach (array_diff_key($value['tags'], $old_tags) as $added_tag) {
                    $value['tag-operations']['add'][] = $get_id($tag_prefix . $added_tag, $root_namespace);
                }
                foreach (array_diff_key($old_tags, $value['tags']) as $removed_tag) {
                    $value['tag-operations']['remove'][] = $get_id($tag_prefix . $removed_tag, $root_namespace);
                }
                $value['tags'] = array_keys($value['tags']);
                $by_lifetime[$ttl][$get_id($key)] = $value;
                $item->metadata = $item->new_metadata;
            }
            return $by_lifetime;
        }, null, Cache_Item::class);
    }
    /**
     * Persists several cache items immediately.
     *
     * @param array   $values        The values to cache, indexed by their cache identifier
     * @param int     $lifetime      The lifetime of the cached values, 0 for persisting until manual cleaning
     * @param array[] $addTagData    Hash where key is tag id, and array value is list of cache id's to add to tag
     * @param array[] $removeTagData Hash where key is tag id, and array value is list of cache id's to remove to tag
     *
     * @return array The identifiers that failed to be cached or a boolean stating if caching succeeded or not
     */
    abstract protected function do_save(array $values, int $lifetime, array $add_tag_data = [], array $remove_tag_data = []): array;
    /**
     * Removes multiple items from the pool and their corresponding tags.
     *
     * @param array $ids An array of identifiers that should be removed from the pool
     */
    abstract protected function do_delete(array $ids): bool;
    /**
     * Removes relations between tags and deleted items.
     *
     * @param array $tagData Array of tag => key identifiers that should be removed from the pool
     */
    abstract protected function do_delete_tag_relations(array $tag_data): bool;
    /**
     * Invalidates cached items using tags.
     *
     * @param string[] $tagIds An array of tags to invalidate, key is tag and value is tag id
     */
    abstract protected function do_invalidate(array $tag_ids): bool;
    /**
     * Delete items and yields the tags they were bound to.
     */
    protected function do_delete_yield_tags(array $ids): iterable
    {
        foreach ($this->do_fetch($ids) as $id => $value) {
            yield $id => \is_array($value) && \is_array($value['tags'] ?? null) ? $value['tags'] : [];
        }
        $this->do_delete($ids);
    }
    public function commit(): bool
    {
        $ok = true;
        $by_lifetime = (self::$merge_by_lifetime)($this->deferred, $expired_ids, $this->get_id(...), self::TAGS_PREFIX, $this->default_lifetime, $this->root_namespace);
        $retry = $this->deferred = [];
        if ($expired_ids) {
            // Tags are not cleaned up in this case, however that is done on invalidateTags().
            try {
                $this->do_delete($expired_ids);
            } catch (\Exception $e) {
                $ok = false;
                Cache_Item::log($this->logger, 'Failed to delete expired items: ' . $e->get_message(), ['exception' => $e, 'cache-adapter' => get_debug_type($this)]);
            }
        }
        foreach ($by_lifetime as $lifetime => $values) {
            try {
                $values = $this->extract_tag_data($values, $add_tag_data, $remove_tag_data);
                $e = $this->do_save($values, $lifetime, $add_tag_data, $remove_tag_data);
            } catch (\Exception $e) {
            }
            if ([] === $e) {
                continue;
            }
            if (\is_array($e) || 1 === \count($values)) {
                foreach (\is_array($e) ? $e : array_keys($values) as $id) {
                    $ok = false;
                    $v = $values[$id];
                    $type = get_debug_type($v);
                    $message = \sprintf('Failed to save key "{key}" of type %s%s', $type, $e instanceof \Exception ? ': ' . $e->get_message() : '.');
                    Cache_Item::log($this->logger, $message, ['key' => substr((string) $id, \strlen($this->root_namespace)), 'exception' => $e instanceof \Exception ? $e : null, 'cache-adapter' => get_debug_type($this)]);
                }
            } else {
                foreach ($values as $id => $v) {
                    $retry[$lifetime][] = $id;
                }
            }
        }
        // When bulk-save failed, retry each item individually
        foreach ($retry as $lifetime => $ids) {
            foreach ($ids as $id) {
                try {
                    $v = $by_lifetime[$lifetime][$id];
                    $values = $this->extract_tag_data([$id => $v], $add_tag_data, $remove_tag_data);
                    $e = $this->do_save($values, $lifetime, $add_tag_data, $remove_tag_data);
                } catch (\Exception $e) {
                }
                if ([] === $e) {
                    continue;
                }
                $ok = false;
                $type = get_debug_type($v);
                $message = \sprintf('Failed to save key "{key}" of type %s%s', $type, $e instanceof \Exception ? ': ' . $e->get_message() : '.');
                Cache_Item::log($this->logger, $message, ['key' => substr((string) $id, \strlen($this->root_namespace)), 'exception' => $e instanceof \Exception ? $e : null, 'cache-adapter' => get_debug_type($this)]);
            }
        }
        return $ok;
    }
    public function delete_items(array $keys): bool
    {
        if (!$keys) {
            return true;
        }
        $ok = true;
        $ids = [];
        $tag_data = [];
        foreach ($keys as $key) {
            $ids[$key] = $this->get_id($key);
            unset($this->deferred[$key]);
        }
        try {
            foreach ($this->do_delete_yield_tags(array_values($ids)) as $id => $tags) {
                foreach ($tags as $tag) {
                    $tag_data[$this->get_id(self::TAGS_PREFIX . $tag, $this->root_namespace)][] = $id;
                }
            }
        } catch (\Exception) {
            $ok = false;
        }
        try {
            if ((!$tag_data || $this->do_delete_tag_relations($tag_data)) && $ok) {
                return true;
            }
        } catch (\Exception) {
        }
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
    public function invalidate_tags(array $tags): bool
    {
        if (!$tags) {
            return false;
        }
        $tag_ids = [];
        foreach (array_unique($tags) as $tag) {
            $tag_ids[] = $this->get_id(self::TAGS_PREFIX . $tag, $this->root_namespace);
        }
        try {
            if ($this->do_invalidate($tag_ids)) {
                return true;
            }
        } catch (\Exception $e) {
            Cache_Item::log($this->logger, 'Failed to invalidate tags: ' . $e->get_message(), ['exception' => $e, 'cache-adapter' => get_debug_type($this)]);
        }
        return false;
    }
    /**
     * Extracts tags operation data from $values set in mergeByLifetime, and returns values without it.
     */
    private function extract_tag_data(array $values, ?array &$add_tag_data, ?array &$remove_tag_data): array
    {
        $add_tag_data = $remove_tag_data = [];
        foreach ($values as $id => $value) {
            foreach ($value['tag-operations']['add'] as $tag => $tag_id) {
                $add_tag_data[$tag_id][] = $id;
            }
            foreach ($value['tag-operations']['remove'] as $tag_id) {
                $remove_tag_data[$tag_id][] = $id;
            }
            unset($values[$id]['tag-operations']);
        }
        return $values;
    }
}