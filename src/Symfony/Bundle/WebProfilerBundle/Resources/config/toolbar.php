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

use Symfony\Bundle\Web_Profiler_Bundle\Event_Listener\Web_Debug_Toolbar_Listener;
return static function (Container_Configurator $container): void {
    $container->services()->set('web_profiler.debug_toolbar', Web_Debug_Toolbar_Listener::class)->args([service('twig'), param('web_profiler.debug_toolbar.intercept_redirects'), param('web_profiler.debug_toolbar.mode'), service('router')->ignore_on_invalid(), abstract_arg('paths that should be excluded from the AJAX requests shown in the toolbar'), service('web_profiler.csp.handler'), service('data_collector.dump')->ignore_on_invalid(), abstract_arg('whether to replace toolbar on AJAX requests or not')])->tag('kernel.event_subscriber');
};