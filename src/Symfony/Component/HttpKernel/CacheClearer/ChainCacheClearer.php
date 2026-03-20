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

/**
 * ChainCacheClearer.
 *
 * @author Dustin Dobervich <ddobervich@gmail.com>
 *
 * @final
 */
class Chain_Cache_Clearer implements Cache_Clearer_Interface
{
    /**
     * @param iterable<mixed, CacheClearerInterface> $clearers
     */
    public function __construct(private readonly iterable $clearers = [])
    {
    }
    public function clear(string $cache_dir): void
    {
        foreach ($this->clearers as $clearer) {
            $clearer->clear($cache_dir);
        }
    }
}