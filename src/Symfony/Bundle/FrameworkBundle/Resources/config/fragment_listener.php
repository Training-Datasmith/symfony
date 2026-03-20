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

use Symfony\Component\Http_Kernel\Event_Listener\Fragment_Listener;
return static function (Container_Configurator $container): void {
    $container->services()->set('fragment.listener', Fragment_Listener::class)->args([service('uri_signer'), param('fragment.path')])->tag('kernel.event_subscriber');
};