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

use Symfony\Bundle\Framework_Bundle\Kernel_Browser;
use Symfony\Bundle\Framework_Bundle\Test\Test_Container;
use Symfony\Component\Browser_Kit\Cookie_Jar;
use Symfony\Component\Browser_Kit\History;
use Symfony\Component\Dependency_Injection\Service_Locator;
use Symfony\Component\Http_Kernel\Event_Listener\Session_Listener;
return static function (Container_Configurator $container): void {
    $container->parameters()->set('test.client.parameters', []);
    $container->services()->set('test.client', Kernel_Browser::class)->args([service('kernel'), param('test.client.parameters'), service('test.client.history'), service('test.client.cookiejar')])->share(false)->public()->set('test.client.history', History::class)->share(false)->set('test.client.cookiejar', Cookie_Jar::class)->share(false)->set('test.session.listener', Session_Listener::class)->args([service_locator(['session_factory' => service('session.factory')->ignore_on_invalid()]), param('kernel.debug'), param('session.storage.options')])->tag('kernel.event_subscriber')->set('test.service_container', Test_Container::class)->args([service('kernel'), 'test.private_services_locator'])->public()->set('test.private_services_locator', Service_Locator::class)->args([abstract_arg('callable collection')])->public();
};