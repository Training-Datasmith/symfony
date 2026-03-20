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
namespace Symfony\Component\Http_Kernel\Cache_Warmer;

/**
 * Interface for classes that support warming their cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Warmable_Interface
{
    /**
     * Warms up the cache.
     *
     * @param string      $cacheDir Where warm-up artifacts should be stored
     * @param string|null $buildDir Where read-only artifacts should go; null when called after compile-time
     *
     * @return string[] A list of classes or files to preload
     */
    public function warm_up(string $cache_dir, ?string $build_dir = null): array;
}