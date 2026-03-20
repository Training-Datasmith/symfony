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
namespace Symfony\Component\Config;

/**
 * A ConfigCacheFactory implementation that validates the
 * cache with an arbitrary set of ResourceCheckers.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class Resource_Checker_Config_Cache_Factory implements Config_Cache_Factory_Interface
{
    /**
     * @param iterable<int, ResourceCheckerInterface> $resourceCheckers
     */
    public function __construct(private readonly iterable $resource_checkers = [])
    {
    }
    public function cache(string $file, callable $callable): Config_Cache_Interface
    {
        $cache = new Resource_Checker_Config_Cache($file, $this->resource_checkers);
        if (!$cache->is_fresh()) {
            $callable($cache);
        }
        return $cache;
    }
}