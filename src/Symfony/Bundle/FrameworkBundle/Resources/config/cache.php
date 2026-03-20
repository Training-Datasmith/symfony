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

use Psr\Cache\Cache_Item_Pool_Interface;
use Symfony\Component\Cache\Adapter\Abstract_Adapter;
use Symfony\Component\Cache\Adapter\Adapter_Interface;
use Symfony\Component\Cache\Adapter\Apcu_Adapter;
use Symfony\Component\Cache\Adapter\Array_Adapter;
use Symfony\Component\Cache\Adapter\Doctrine_Dbal_Adapter;
use Symfony\Component\Cache\Adapter\Filesystem_Adapter;
use Symfony\Component\Cache\Adapter\Memcached_Adapter;
use Symfony\Component\Cache\Adapter\Pdo_Adapter;
use Symfony\Component\Cache\Adapter\Proxy_Adapter;
use Symfony\Component\Cache\Adapter\Redis_Adapter;
use Symfony\Component\Cache\Adapter\Redis_Tag_Aware_Adapter;
use Symfony\Component\Cache\Adapter\Tag_Aware_Adapter;
use Symfony\Component\Cache\Marshaller\Default_Marshaller;
use Symfony\Component\Cache\Messenger\Early_Expiration_Handler;
use Symfony\Component\Http_Kernel\Cache_Clearer\Psr6cache_Clearer;
use Symfony\Contracts\Cache\Cache_Interface;
use Symfony\Contracts\Cache\Namespaced_Pool_Interface;
use Symfony\Contracts\Cache\Tag_Aware_Cache_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('cache.app')->parent('cache.adapter.filesystem')->public()->tag('cache.pool', ['clearer' => 'cache.app_clearer'])->set('cache.app.taggable', Tag_Aware_Adapter::class)->args([service('cache.app')])->tag('cache.taggable', ['pool' => 'cache.app'])->set('cache.system')->parent('cache.adapter.system')->public()->tag('cache.pool')->set('cache.validator')->parent('cache.system')->private()->tag('cache.pool')->set('cache.serializer')->parent('cache.system')->private()->tag('cache.pool')->set('cache.property_info')->parent('cache.system')->private()->tag('cache.pool')->set('cache.asset_mapper')->parent('cache.system')->private()->tag('cache.pool')->set('cache.messenger.restart_workers_signal')->parent('cache.app')->private()->tag('cache.pool')->set('cache.scheduler')->parent('cache.app')->private()->tag('cache.pool')->set('cache.adapter.system', Adapter_Interface::class)->abstract()->factory([Abstract_Adapter::class, 'createSystemCache'])->args([
        '',
        // namespace
        0,
        // default lifetime
        abstract_arg('version'),
        \sprintf('%s/pools/system', param('kernel.cache_dir')),
        service('logger')->ignore_on_invalid(),
    ])->tag('cache.pool', ['clearer' => 'cache.system_clearer', 'reset' => 'reset'])->tag('monolog.logger', ['channel' => 'cache'])->set('cache.adapter.apcu', Apcu_Adapter::class)->abstract()->args([
        '',
        // namespace
        0,
        // default lifetime
        abstract_arg('version'),
        service('cache.default_marshaller')->ignore_on_invalid(),
    ])->call('setLogger', [service('logger')->ignore_on_invalid()])->tag('cache.pool', ['clearer' => 'cache.default_clearer', 'reset' => 'reset'])->tag('monolog.logger', ['channel' => 'cache'])->set('cache.adapter.filesystem', Filesystem_Adapter::class)->abstract()->args([
        '',
        // namespace
        0,
        // default lifetime
        \sprintf('%s/pools/app', param('kernel.cache_dir')),
        service('cache.default_marshaller')->ignore_on_invalid(),
    ])->call('setLogger', [service('logger')->ignore_on_invalid()])->tag('cache.pool', ['clearer' => 'cache.default_clearer', 'reset' => 'reset'])->tag('monolog.logger', ['channel' => 'cache'])->set('cache.adapter.psr6', Proxy_Adapter::class)->abstract()->args([
        abstract_arg('PSR-6 provider service'),
        '',
        // namespace
        0,
    ])->tag('cache.pool', ['provider' => 'cache.default_psr6_provider', 'clearer' => 'cache.default_clearer', 'reset' => 'reset'])->set('cache.adapter.redis', Redis_Adapter::class)->abstract()->args([
        abstract_arg('Redis connection service'),
        '',
        // namespace
        0,
        // default lifetime
        service('cache.default_marshaller')->ignore_on_invalid(),
    ])->call('setLogger', [service('logger')->ignore_on_invalid()])->tag('cache.pool', ['provider' => 'cache.default_redis_provider', 'clearer' => 'cache.default_clearer', 'reset' => 'reset'])->tag('monolog.logger', ['channel' => 'cache'])->alias('cache.adapter.valkey', 'cache.adapter.redis')->set('cache.adapter.redis_tag_aware', Redis_Tag_Aware_Adapter::class)->abstract()->args([
        abstract_arg('Redis connection service'),
        '',
        // namespace
        0,
        // default lifetime
        service('cache.default_marshaller')->ignore_on_invalid(),
    ])->call('setLogger', [service('logger')->ignore_on_invalid()])->tag('cache.pool', ['provider' => 'cache.default_redis_provider', 'clearer' => 'cache.default_clearer', 'reset' => 'reset'])->tag('monolog.logger', ['channel' => 'cache'])->alias('cache.adapter.valkey_tag_aware', 'cache.adapter.redis_tag_aware')->set('cache.adapter.memcached', Memcached_Adapter::class)->abstract()->args([
        abstract_arg('Memcached connection service'),
        '',
        // namespace
        0,
        // default lifetime
        service('cache.default_marshaller')->ignore_on_invalid(),
    ])->call('setLogger', [service('logger')->ignore_on_invalid()])->tag('cache.pool', ['provider' => 'cache.default_memcached_provider', 'clearer' => 'cache.default_clearer', 'reset' => 'reset'])->tag('monolog.logger', ['channel' => 'cache'])->set('cache.adapter.doctrine_dbal', Doctrine_Dbal_Adapter::class)->abstract()->args([
        abstract_arg('DBAL connection service'),
        '',
        // namespace
        0,
        // default lifetime
        [],
        // table options
        service('cache.default_marshaller')->ignore_on_invalid(),
    ])->call('setLogger', [service('logger')->ignore_on_invalid()])->tag('cache.pool', ['provider' => 'cache.default_doctrine_dbal_provider', 'clearer' => 'cache.default_clearer', 'reset' => 'reset'])->tag('monolog.logger', ['channel' => 'cache'])->set('cache.adapter.pdo', Pdo_Adapter::class)->abstract()->args([
        abstract_arg('PDO connection service'),
        '',
        // namespace
        0,
        // default lifetime
        [],
        // table options
        service('cache.default_marshaller')->ignore_on_invalid(),
    ])->call('setLogger', [service('logger')->ignore_on_invalid()])->tag('cache.pool', ['provider' => 'cache.default_pdo_provider', 'clearer' => 'cache.default_clearer', 'reset' => 'reset'])->tag('monolog.logger', ['channel' => 'cache'])->set('cache.adapter.array', Array_Adapter::class)->abstract()->args([0])->call('setLogger', [service('logger')->ignore_on_invalid()])->tag('cache.pool', ['clearer' => 'cache.default_clearer', 'reset' => 'reset'])->tag('monolog.logger', ['channel' => 'cache'])->set('cache.default_marshaller', Default_Marshaller::class)->args([
        null,
        // use igbinary_serialize() when available
        '%kernel.debug%',
    ])->set('cache.early_expiration_handler', Early_Expiration_Handler::class)->args([service('reverse_container')])->tag('messenger.message_handler')->set('cache.default_clearer', Psr6cache_Clearer::class)->args([[]])->set('cache.system_clearer')->parent('cache.default_clearer')->public()->set('cache.global_clearer')->parent('cache.default_clearer')->public()->alias('cache.app_clearer', 'cache.default_clearer')->public()->alias(Cache_Item_Pool_Interface::class, 'cache.app')->alias(Cache_Interface::class, 'cache.app')->alias(Namespaced_Pool_Interface::class, 'cache.app')->alias(Tag_Aware_Cache_Interface::class, 'cache.app.taggable');
};