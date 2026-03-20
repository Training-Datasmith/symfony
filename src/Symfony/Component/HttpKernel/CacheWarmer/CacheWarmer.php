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
 * Abstract cache warmer that knows how to write a file to the cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Cache_Warmer implements Cache_Warmer_Interface
{
    protected function write_cache_file(string $file, $content): void
    {
        $tmp_file = @tempnam(\dirname($file), basename($file));
        if (false !== @file_put_contents($tmp_file, $content) && @rename($tmp_file, $file)) {
            @chmod($file, 0666 & ~umask());
            return;
        }
        throw new \RuntimeException(\sprintf('Failed to write cache file "%s".', $file));
    }
}