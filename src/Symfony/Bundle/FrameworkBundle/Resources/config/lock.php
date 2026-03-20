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

use Symfony\Component\Lock\Lock_Factory;
use Symfony\Component\Lock\Serializer\Lock_Key_Normalizer;
use Symfony\Component\Lock\Store\Combined_Store;
use Symfony\Component\Lock\Store\Flock_Store;
use Symfony\Component\Lock\Store\Semaphore_Store;
use Symfony\Component\Lock\Strategy\Consensus_Strategy;
return static function (Container_Configurator $container): void {
    $container->services()->set('lock.store.combined.abstract', Combined_Store::class)->abstract()->args([abstract_arg('List of stores'), service('lock.strategy.majority')])->set('lock.strategy.majority', Consensus_Strategy::class)->set('lock.factory.abstract', Lock_Factory::class)->abstract()->args([abstract_arg('Store')])->call('setLogger', [service('logger')->ignore_on_invalid()])->tag('monolog.logger', ['channel' => 'lock'])->set('serializer.normalizer.lock_key', Lock_Key_Normalizer::class)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -880])->set('.lock.flock.store', Flock_Store::class)->args([inline_service('string')->factory('implode')->args(['/', [inline_service('string')->factory('sys_get_temp_dir'), 'symfony-lock', inline_service('string')->factory('hash')->args(['xxh64', '%kernel.project_dir%'])]])])->set('.lock.semaphore.store', Semaphore_Store::class)->args(['%kernel.project_dir%']);
};