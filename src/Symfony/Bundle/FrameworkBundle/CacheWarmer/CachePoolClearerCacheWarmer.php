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
namespace Symfony\Bundle\Framework_Bundle\Cache_Warmer;

use Symfony\Component\Http_Kernel\Cache_Clearer\Psr6cache_Clearer;
use Symfony\Component\Http_Kernel\Cache_Warmer\Cache_Warmer_Interface;
/**
 * Clears the cache pools when warming up the cache.
 *
 * Do not use in production!
 *
 * @author Teoh Han Hui <teohhanhui@gmail.com>
 *
 * @internal
 */
final readonly class Cache_Pool_Clearer_Cache_Warmer implements Cache_Warmer_Interface
{
    /**
     * @param string[] $pools
     */
    public function __construct(private Psr6cache_Clearer $pool_clearer, private array $pools = [])
    {
    }
    public function warm_up(string $cache_dir, ?string $build_dir = null): array
    {
        foreach ($this->pools as $pool) {
            if ($this->pool_clearer->has_pool($pool)) {
                $this->pool_clearer->clear_pool($pool);
            }
        }
        return [];
    }
    public function is_optional(): bool
    {
        // optional cache warmers are not run when handling the request
        return false;
    }
}