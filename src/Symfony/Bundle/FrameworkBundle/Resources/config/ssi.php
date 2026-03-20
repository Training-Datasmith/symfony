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
use Symfony\Component\Http_Kernel\Http_Cache\Ssi;
return static function (Container_Configurator $container): void {
    $container->services()->set('ssi', Ssi::class)->set('ssi_listener', Surrogate_Listener::class)->args([service('ssi')->ignore_on_invalid()])->tag('kernel.event_subscriber');
};