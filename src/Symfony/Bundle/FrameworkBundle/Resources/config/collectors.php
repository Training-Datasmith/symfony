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

use Symfony\Bundle\Framework_Bundle\Data_Collector\Router_Data_Collector;
use Symfony\Component\Console\Data_Collector\Command_Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Ajax_Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Config_Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Event_Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Exception_Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Logger_Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Memory_Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Request_Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Time_Data_Collector;
use Symfony\Component\Http_Kernel\Kernel_Events;
return static function (Container_Configurator $container): void {
    $container->services()->set('data_collector.config', Config_Data_Collector::class)->call('setKernel', [service('kernel')->ignore_on_invalid()])->tag('data_collector', ['template' => '@WebProfiler/Collector/config.html.twig', 'id' => 'config', 'priority' => -255])->set('data_collector.request', Request_Data_Collector::class)->args([service('.virtual_request_stack')->ignore_on_invalid()])->tag('kernel.event_subscriber')->tag('data_collector', ['template' => '@WebProfiler/Collector/request.html.twig', 'id' => 'request', 'priority' => 335])->set('data_collector.request.session_collector', \Closure::class)->factory([\Closure::class, 'fromCallable'])->args([[service('data_collector.request'), 'collectSessionUsage']])->set('data_collector.ajax', Ajax_Data_Collector::class)->tag('data_collector', ['template' => '@WebProfiler/Collector/ajax.html.twig', 'id' => 'ajax', 'priority' => 315])->set('data_collector.exception', Exception_Data_Collector::class)->tag('data_collector', ['template' => '@WebProfiler/Collector/exception.html.twig', 'id' => 'exception', 'priority' => 305])->set('data_collector.events', Event_Data_Collector::class)->args([tagged_iterator('event_dispatcher.dispatcher', 'name'), service('.virtual_request_stack')->ignore_on_invalid()])->tag('data_collector', ['template' => '@WebProfiler/Collector/events.html.twig', 'id' => 'events', 'priority' => 290])->set('data_collector.logger', Logger_Data_Collector::class)->args([service('logger')->ignore_on_invalid(), \sprintf('%s/%s', param('kernel.build_dir'), param('kernel.container_class')), service('.virtual_request_stack')->ignore_on_invalid()])->tag('monolog.logger', ['channel' => 'profiler'])->tag('data_collector', ['template' => '@WebProfiler/Collector/logger.html.twig', 'id' => 'logger', 'priority' => 300])->set('data_collector.time', Time_Data_Collector::class)->args([service('kernel')->ignore_on_invalid(), service('debug.stopwatch')->ignore_on_invalid()])->tag('data_collector', ['template' => '@WebProfiler/Collector/time.html.twig', 'id' => 'time', 'priority' => 330])->set('data_collector.memory', Memory_Data_Collector::class)->tag('data_collector', ['template' => '@WebProfiler/Collector/memory.html.twig', 'id' => 'memory', 'priority' => 325])->set('data_collector.router', Router_Data_Collector::class)->tag('kernel.event_listener', ['event' => Kernel_Events::CONTROLLER, 'method' => 'onKernelController'])->tag('data_collector', ['template' => '@WebProfiler/Collector/router.html.twig', 'id' => 'router', 'priority' => 285])->set('.data_collector.command', Command_Data_Collector::class)->tag('data_collector', ['template' => '@WebProfiler/Collector/command.html.twig', 'id' => 'command', 'priority' => 335]);
};