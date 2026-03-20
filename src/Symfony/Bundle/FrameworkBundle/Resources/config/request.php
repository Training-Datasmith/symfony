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

use Symfony\Component\Http_Kernel\Event_Listener\Add_Request_Formats_Listener;
return static function (Container_Configurator $container): void {
    $container->services()->set('request.add_request_formats_listener', Add_Request_Formats_Listener::class)->args([abstract_arg('formats')])->tag('kernel.event_subscriber');
};