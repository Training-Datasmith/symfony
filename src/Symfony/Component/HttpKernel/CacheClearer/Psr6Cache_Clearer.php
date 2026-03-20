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
namespace Symfony\Component\Http_Kernel\Cache_Clearer;

use Psr\Cache\Cache_Item_Pool_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Psr6cache_Clearer implements Cache_Clearer_Interface
{
    /**
     * @param array<string, CacheItemPoolInterface> $pools
     */
    public function __construct(private array $pools = [])
    {
    }
    public function has_pool(string $name): bool
    {
        return isset($this->pools[$name]);
    }
    /**
     * @throws \InvalidArgumentException If the cache pool with the given name does not exist
     */
    public function get_pool(string $name): Cache_Item_Pool_Interface
    {
        if (!$this->has_pool($name)) {
            throw new \InvalidArgumentException(\sprintf('Cache pool not found: "%s".', $name));
        }
        return $this->pools[$name];
    }
    /**
     * @throws \InvalidArgumentException If the cache pool with the given name does not exist
     */
    public function clear_pool(string $name): bool
    {
        if (!isset($this->pools[$name])) {
            throw new \InvalidArgumentException(\sprintf('Cache pool not found: "%s".', $name));
        }
        return $this->pools[$name]->clear();
    }
    public function clear(string $cache_dir): void
    {
        foreach ($this->pools as $pool) {
            $pool->clear();
        }
    }
}