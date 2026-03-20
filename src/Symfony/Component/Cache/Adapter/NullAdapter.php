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
use Symfony\Contracts\Cache\Cache_Interface;
use Symfony\Contracts\Cache\Namespaced_Pool_Interface;
/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class Null_Adapter implements Adapter_Interface, Cache_Interface, Namespaced_Pool_Interface, Tag_Aware_Adapter_Interface
{
    private static \Closure $create_cache_item;
    public function __construct()
    {
        self::$create_cache_item ??= \Closure::bind(static function ($key): \Symfony\Component\Cache\Cache_Item {
            $item = new Cache_Item();
            $item->is_taggable = true;
            $item->key = $key;
            $item->is_hit = false;
            return $item;
        }, null, Cache_Item::class);
    }
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        $save = true;
        return $callback((self::$create_cache_item)($key), $save);
    }
    public function get_item(mixed $key): Cache_Item
    {
        return (self::$create_cache_item)($key);
    }
    public function get_items(array $keys = []): iterable
    {
        return $this->generate_items($keys);
    }
    public function has_item(mixed $key): bool
    {
        return false;
    }
    public function clear(string $prefix = ''): bool
    {
        return true;
    }
    public function delete_item(mixed $key): bool
    {
        return true;
    }
    public function delete_items(array $keys): bool
    {
        return true;
    }
    public function save(Cache_Item_Interface $item): bool
    {
        return true;
    }
    public function save_deferred(Cache_Item_Interface $item): bool
    {
        return true;
    }
    public function commit(): bool
    {
        return true;
    }
    public function delete(string $key): bool
    {
        return $this->delete_item($key);
    }
    public function with_sub_namespace(string $namespace): static
    {
        return clone $this;
    }
    private function generate_items(array $keys): \Generator
    {
        $f = self::$create_cache_item;
        foreach ($keys as $key) {
            yield $key => $f($key);
        }
    }
    public function invalidate_tags(array $tags): bool
    {
        return true;
    }
}