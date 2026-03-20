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
 * Basic implementation of ConfigCacheFactoryInterface that
 * creates an instance of the default ConfigCache.
 *
 * This factory and/or cache <em>do not</em> support cache validation
 * by means of ResourceChecker instances (that is, service-based).
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class Config_Cache_Factory implements Config_Cache_Factory_Interface
{
    /**
     * @param bool $debug The debug flag to pass to ConfigCache
     */
    public function __construct(private readonly bool $debug)
    {
    }
    public function cache(string $file, callable $callback): Config_Cache_Interface
    {
        $cache = new Config_Cache($file, $this->debug);
        if (!$cache->is_fresh()) {
            $callback($cache);
        }
        return $cache;
    }
}