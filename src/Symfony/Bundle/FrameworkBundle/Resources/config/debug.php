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

use Symfony\Component\Console\Argument_Resolver\Traceable_Argument_Resolver as TraceableConsoleArgumentResolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Not_Tagged_Controller_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Traceable_Argument_Resolver;
use Symfony\Component\Http_Kernel\Controller\Traceable_Controller_Resolver;
use Symfony\Component\Http_Kernel\Debug\Traceable_Event_Dispatcher;
return static function (Container_Configurator $container): void {
    $container->services()->set('debug.event_dispatcher', Traceable_Event_Dispatcher::class)->decorate('event_dispatcher')->args([service('debug.event_dispatcher.inner'), service('debug.stopwatch'), service('logger')->null_on_invalid(), service('.virtual_request_stack')->null_on_invalid(), service('profiler.is_disabled_state_checker')->null_on_invalid()])->tag('monolog.logger', ['channel' => 'event'])->tag('kernel.reset', ['method' => 'reset'])->set('debug.controller_resolver', Traceable_Controller_Resolver::class)->decorate('controller_resolver')->args([service('debug.controller_resolver.inner'), service('debug.stopwatch')])->set('debug.argument_resolver', Traceable_Argument_Resolver::class)->decorate('argument_resolver')->args([service('debug.argument_resolver.inner'), service('debug.stopwatch')])->set('argument_resolver.not_tagged_controller', Not_Tagged_Controller_Value_Resolver::class)->args([abstract_arg('Controller argument, set in FrameworkExtension')])->tag('controller.argument_value_resolver', ['priority' => -200])->set('debug.console.argument_resolver', Traceable_Console_Argument_Resolver::class)->decorate('console.argument_resolver')->args([service('debug.console.argument_resolver.inner'), service('debug.stopwatch')]);
};