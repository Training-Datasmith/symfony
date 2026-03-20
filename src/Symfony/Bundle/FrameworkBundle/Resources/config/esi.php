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

use Symfony\Component\Http_Kernel\Event_Listener\Surrogate_Listener;
use Symfony\Component\Http_Kernel\Http_Cache\Esi;
return static function (Container_Configurator $container): void {
    $container->services()->set('esi', Esi::class)->set('esi_listener', Surrogate_Listener::class)->args([service('esi')->ignore_on_invalid()])->tag('kernel.event_subscriber');
};