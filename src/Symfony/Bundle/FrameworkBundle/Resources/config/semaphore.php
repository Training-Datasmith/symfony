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

use Symfony\Component\Semaphore\Semaphore_Factory;
use Symfony\Component\Semaphore\Serializer\Semaphore_Key_Normalizer;
return static function (Container_Configurator $container): void {
    $container->services()->set('semaphore.factory.abstract', Semaphore_Factory::class)->abstract()->args([abstract_arg('Store')])->call('setLogger', [service('logger')->ignore_on_invalid()])->tag('monolog.logger', ['channel' => 'semaphore'])->set('serializer.normalizer.semaphore_key', Semaphore_Key_Normalizer::class)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -880]);
};