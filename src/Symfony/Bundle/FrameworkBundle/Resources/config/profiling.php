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

use Symfony\Bundle\Framework_Bundle\Event_Listener\Console_Profiler_Listener;
use Symfony\Bundle\Framework_Bundle\Framework_Bundle;
use Symfony\Component\Http_Kernel\Debug\Virtual_Request_Stack;
use Symfony\Component\Http_Kernel\Event_Listener\Profiler_Listener;
use Symfony\Component\Http_Kernel\Profiler\File_Profiler_Storage;
use Symfony\Component\Http_Kernel\Profiler\Profiler;
use Symfony\Component\Http_Kernel\Profiler\Profiler_State_Checker;
return static function (Container_Configurator $container): void {
    $container->services()->set('profiler', Profiler::class)->public()->args([service('profiler.storage'), service('logger')->null_on_invalid()])->tag('monolog.logger', ['channel' => 'profiler'])->tag('container.private', ['package' => 'symfony/framework-bundle', 'version' => '5.4'])->set('profiler.storage', File_Profiler_Storage::class)->args([param('profiler.storage.dsn')])->set('profiler_listener', Profiler_Listener::class)->args([service('profiler'), service('request_stack'), null, param('profiler_listener.only_exceptions'), param('profiler_listener.only_main_requests')])->tag('kernel.event_subscriber')->tag('kernel.reset', ['method' => '?reset'])->set('console_profiler_listener', Console_Profiler_Listener::class)->args([service('.lazy_profiler'), service('.virtual_request_stack'), service('debug.stopwatch'), param('kernel.runtime_mode.cli'), service('router')->null_on_invalid()])->tag('kernel.event_subscriber')->set('.lazy_profiler', Profiler::class)->factory('current')->args([[service('profiler')]])->lazy()->set('.virtual_request_stack', Virtual_Request_Stack::class)->args([service('request_stack')])->public()->set('profiler.state_checker', Profiler_State_Checker::class)->args([service_locator(['profiler' => service('profiler')->ignore_on_uninitialized()]), inline_service('bool')->factory([Framework_Bundle::class, 'considerProfilerEnabled'])])->set('profiler.is_disabled_state_checker', 'Closure')->factory(['Closure', 'fromCallable'])->args([[service('profiler.state_checker'), 'isProfilerDisabled']]);
};