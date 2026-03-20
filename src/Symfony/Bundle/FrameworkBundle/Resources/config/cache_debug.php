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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Symfony\Bundle\Framework_Bundle\Cache_Warmer\Cache_Pool_Clearer_Cache_Warmer;
use Symfony\Component\Cache\Data_Collector\Cache_Data_Collector;
return static function (Container_Configurator $container): void {
    $container->services()->set('data_collector.cache', Cache_Data_Collector::class)->public()->tag('data_collector', ['template' => '@WebProfiler/Collector/cache.html.twig', 'id' => 'cache', 'priority' => 275])->set('cache_pool_clearer.cache_warmer', Cache_Pool_Clearer_Cache_Warmer::class)->args([service('cache.system_clearer'), ['cache.validator', 'cache.serializer']])->tag('kernel.cache_warmer', ['priority' => 64]);
};