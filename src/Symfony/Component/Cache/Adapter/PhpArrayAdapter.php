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
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Pruneable_Interface;
use Symfony\Component\Cache\Resettable_Interface;
use Symfony\Component\Cache\Traits\Cached_Value_Interface;
use Symfony\Component\Cache\Traits\Contracts_Trait;
use Symfony\Component\Cache\Traits\Proxy_Trait;
use Symfony\Component\Var_Exporter\Var_Exporter;
use Symfony\Contracts\Cache\Cache_Interface;
/**
 * Caches items at warm up time using a PHP array that is stored in shared memory by OPCache since PHP 7.0.
 * Warmed up items are read-only and run-time discovered items are cached using a fallback adapter.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Php_Array_Adapter implements Adapter_Interface, Cache_Interface, Pruneable_Interface, Resettable_Interface
{
    use Contracts_Trait;
    use Proxy_Trait;
    private array $keys;
    private array $values;
    private static \Closure $create_cache_item;
    private static array $values_cache = [];
    /**
     * @param string           $file         The PHP file where values are cached
     * @param AdapterInterface $fallbackPool A pool to fallback on when an item is not hit
     */
    public function __construct(private string $file, Adapter_Interface $fallback_pool)
    {
        $this->pool = $fallback_pool;
        self::$create_cache_item ??= \Closure::bind(static function ($key, $value, $is_hit): \Symfony\Component\Cache\Cache_Item {
            $item = new Cache_Item();
            $item->key = $key;
            $item->value = $value;
            $item->is_hit = $is_hit;
            return $item;
        }, null, Cache_Item::class);
    }
    /**
     * This adapter takes advantage of how PHP stores arrays in its latest versions.
     *
     * @param string                 $file         The PHP file were values are cached
     * @param CacheItemPoolInterface $fallbackPool A pool to fallback on when an item is not hit
     */
    public static function create(string $file, Cache_Item_Pool_Interface $fallback_pool): Cache_Item_Pool_Interface
    {
        if (!$fallback_pool instanceof Adapter_Interface) {
            $fallback_pool = new Proxy_Adapter($fallback_pool);
        }
        return new static($file, $fallback_pool);
    }
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        if (!isset($this->values)) {
            $this->initialize();
        }
        if (!isset($this->keys[$key])) {
            get_from_pool:
            if ($this->pool instanceof Cache_Interface) {
                return $this->pool->get($key, $callback, $beta, $metadata);
            }
            return $this->do_get($this->pool, $key, $callback, $beta, $metadata);
        }
        $value = $this->values[$this->keys[$key]];
        if ('N;' === $value) {
            return null;
        }
        if (!$value instanceof Cached_Value_Interface) {
            return $value;
        }
        try {
            return $value->get_value();
        } catch (\Throwable) {
            unset($this->keys[$key]);
            goto get_from_pool;
        }
    }
    public function get_item(mixed $key): Cache_Item
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(\sprintf('Cache key must be string, "%s" given.', get_debug_type($key)));
        }
        if (!isset($this->values)) {
            $this->initialize();
        }
        if (!isset($this->keys[$key])) {
            return $this->pool->get_item($key);
        }
        $value = $this->values[$this->keys[$key]];
        $is_hit = true;
        if ('N;' === $value) {
            $value = null;
        } elseif ($value instanceof Cached_Value_Interface) {
            try {
                $value = $value->get_value();
            } catch (\Throwable) {
                $value = null;
                $is_hit = false;
            }
        }
        return (self::$create_cache_item)($key, $value, $is_hit);
    }
    public function get_items(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException(\sprintf('Cache key must be string, "%s" given.', get_debug_type($key)));
            }
        }
        if (!isset($this->values)) {
            $this->initialize();
        }
        return $this->generate_items($keys);
    }
    public function has_item(mixed $key): bool
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(\sprintf('Cache key must be string, "%s" given.', get_debug_type($key)));
        }
        if (!isset($this->values)) {
            $this->initialize();
        }
        return isset($this->keys[$key]) || $this->pool->has_item($key);
    }
    public function delete_item(mixed $key): bool
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(\sprintf('Cache key must be string, "%s" given.', get_debug_type($key)));
        }
        if (!isset($this->values)) {
            $this->initialize();
        }
        return !isset($this->keys[$key]) && $this->pool->delete_item($key);
    }
    public function delete_items(array $keys): bool
    {
        $deleted = true;
        $fallback_keys = [];
        foreach ($keys as $key) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException(\sprintf('Cache key must be string, "%s" given.', get_debug_type($key)));
            }
            if (isset($this->keys[$key])) {
                $deleted = false;
            } else {
                $fallback_keys[] = $key;
            }
        }
        if (!isset($this->values)) {
            $this->initialize();
        }
        if ($fallback_keys) {
            return $this->pool->delete_items($fallback_keys) && $deleted;
        }
        return $deleted;
    }
    public function save(Cache_Item_Interface $item): bool
    {
        if (!isset($this->values)) {
            $this->initialize();
        }
        return !isset($this->keys[$item->get_key()]) && $this->pool->save($item);
    }
    public function save_deferred(Cache_Item_Interface $item): bool
    {
        if (!isset($this->values)) {
            $this->initialize();
        }
        return !isset($this->keys[$item->get_key()]) && $this->pool->save_deferred($item);
    }
    public function commit(): bool
    {
        return $this->pool->commit();
    }
    public function clear(string $prefix = ''): bool
    {
        $this->keys = $this->values = [];
        $cleared = @unlink($this->file) || !file_exists($this->file);
        unset(self::$values_cache[$this->file]);
        if ($this->pool instanceof Adapter_Interface) {
            return $this->pool->clear($prefix) && $cleared;
        }
        return $this->pool->clear() && $cleared;
    }
    /**
     * Store an array of cached values.
     *
     * @param array $values The cached values
     *
     * @return string[] A list of classes to preload on PHP 7.4+
     */
    public function warm_up(array $values): array
    {
        if (file_exists($this->file)) {
            if (!is_file($this->file)) {
                throw new InvalidArgumentException(\sprintf('Cache path exists and is not a file: "%s".', $this->file));
            }
            if (!is_writable($this->file)) {
                throw new InvalidArgumentException(\sprintf('Cache file is not writable: "%s".', $this->file));
            }
        } else {
            $directory = \dirname($this->file);
            if (!is_dir($directory) && !@mkdir($directory, 0777, true)) {
                throw new InvalidArgumentException(\sprintf('Cache directory does not exist and cannot be created: "%s".', $directory));
            }
            if (!is_writable($directory)) {
                throw new InvalidArgumentException(\sprintf('Cache directory is not writable: "%s".', $directory));
            }
        }
        $preload = [];
        $dumped_values = '';
        $dumped_map = [];
        $dump = <<<'EOF'
        <?php
        
        // This file has been auto-generated by the Symfony Cache Component.
        
        return [[
        
        
        EOF;
        foreach ($values as $key => $value) {
            Cache_Item::validate_key(\is_int($key) ? (string) $key : $key);
            $is_static_value = true;
            if (null === $value) {
                $value = "'N;'";
            } elseif (\is_object($value) || \is_array($value)) {
                try {
                    $value = Var_Exporter::export($value, $is_static_value, $preload);
                } catch (\Exception $e) {
                    throw new InvalidArgumentException(\sprintf('Cache key "%s" has non-serializable "%s" value.', $key, get_debug_type($value)), 0, $e);
                }
            } elseif (\is_string($value)) {
                // Wrap "N;" in a closure to not confuse it with an encoded `null`
                if ('N;' === $value) {
                    $is_static_value = false;
                }
                $value = var_export($value, true);
            } elseif (!\is_scalar($value)) {
                throw new InvalidArgumentException(\sprintf('Cache key "%s" has non-serializable "%s" value.', $key, get_debug_type($value)));
            } else {
                $value = var_export($value, true);
            }
            if (!$is_static_value) {
                $value = 'new class() implements \\' . Cached_Value_Interface::class . " { public function getValue(): mixed { return {$value}; } }";
            }
            $hash = hash('xxh128', $value);
            if (null === $id = $dumped_map[$hash] ?? null) {
                $id = $dumped_map[$hash] = \count($dumped_map);
                $dumped_values .= "{$id} => {$value},\n";
            }
            $dump .= var_export($key, true) . " => {$id},\n";
        }
        $dump .= "\n], [\n\n{$dumped_values}\n]];\n";
        $tmp_file = tempnam(\dirname($this->file), basename($this->file));
        file_put_contents($tmp_file, $dump);
        @chmod($tmp_file, 0666 & ~umask());
        unset($value, $dump);
        @rename($tmp_file, $this->file);
        unset(self::$values_cache[$this->file]);
        $this->initialize();
        return $preload;
    }
    /**
     * Load the cache file.
     */
    private function initialize(): void
    {
        if (isset(self::$values_cache[$this->file])) {
            $values = self::$values_cache[$this->file];
        } elseif (!is_file($this->file)) {
            $this->keys = $this->values = [];
            return;
        } else {
            $values = self::$values_cache[$this->file] = (include $this->file) ?: [[], []];
        }
        if (2 !== \count($values) || !isset($values[0], $values[1])) {
            $this->keys = $this->values = [];
        } else {
            [$this->keys, $this->values] = $values;
        }
    }
    private function generate_items(array $keys): \Generator
    {
        $f = self::$create_cache_item;
        $fallback_keys = [];
        foreach ($keys as $key) {
            if (isset($this->keys[$key])) {
                $value = $this->values[$this->keys[$key]];
                if ('N;' === $value) {
                    yield $key => $f($key, null, true);
                } elseif ($value instanceof Cached_Value_Interface) {
                    try {
                        yield $key => $f($key, $value->get_value(), true);
                    } catch (\Throwable) {
                        yield $key => $f($key, null, false);
                    }
                } else {
                    yield $key => $f($key, $value, true);
                }
            } else {
                $fallback_keys[] = $key;
            }
        }
        if ($fallback_keys) {
            yield from $this->pool->get_items($fallback_keys);
        }
    }
}