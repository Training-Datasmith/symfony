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

use Psr\Clock\Clock_Interface as PsrClockInterface;
use Psr\Event_Dispatcher\Event_Dispatcher_Interface as PsrEventDispatcherInterface;
use Symfony\Bundle\Framework_Bundle\Http_Cache\Http_Cache;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\Clock_Interface;
use Symfony\Component\Config\Loader\Loader_Interface;
use Symfony\Component\Config\Resource\Self_Checking_Resource_Checker;
use Symfony\Component\Config\Resource_Checker_Config_Cache_Factory;
use Symfony\Component\Console\Console_Events;
use Symfony\Component\Dependency_Injection\Config\Container_Parameters_Resource_Checker;
use Symfony\Component\Dependency_Injection\Env_Var_Processor;
use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Container_Bag;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Container_Bag_Interface;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag_Interface;
use Symfony\Component\Dependency_Injection\Reverse_Container;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher_Interface as EventDispatcherInterfaceComponentAlias;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Session\Session_Interface;
use Symfony\Component\Http_Foundation\Uri_Signer;
use Symfony\Component\Http_Foundation\Url_Helper;
use Symfony\Component\Http_Kernel\Cache_Clearer\Chain_Cache_Clearer;
use Symfony\Component\Http_Kernel\Cache_Warmer\Cache_Warmer_Aggregate;
use Symfony\Component\Http_Kernel\Config\File_Locator;
use Symfony\Component\Http_Kernel\Dependency_Injection\Services_Resetter;
use Symfony\Component\Http_Kernel\Dependency_Injection\Services_Resetter_Interface;
use Symfony\Component\Http_Kernel\Event_Listener\Locale_Aware_Listener;
use Symfony\Component\Http_Kernel\Http_Cache\Store;
use Symfony\Component\Http_Kernel\Http_Cache\Store_Interface;
use Symfony\Component\Http_Kernel\Http_Kernel;
use Symfony\Component\Http_Kernel\Http_Kernel_Interface;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Http_Kernel\Kernel_Interface;
use Symfony\Component\Runtime\Runner\Symfony\Http_Kernel_Runner;
use Symfony\Component\Runtime\Runner\Symfony\Response_Runner;
use Symfony\Component\Runtime\Symfony_Runtime;
use Symfony\Component\String\Lazy_String;
use Symfony\Component\String\Slugger\Ascii_Slugger;
use Symfony\Component\String\Slugger\Slugger_Interface;
use Symfony\Component\Workflow\Workflow_Events;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
return static function (Container_Configurator $container): void {
    // this parameter is used at compile time in RegisterListenersPass
    $container->parameters()->set('event_dispatcher.event_aliases', array_merge(class_exists(Console_Events::class) ? Console_Events::ALIASES : [], class_exists(Form_Events::class) ? Form_Events::ALIASES : [], Kernel_Events::ALIASES, class_exists(Workflow_Events::class) ? Workflow_Events::ALIASES : []));
    $container->services()->set('parameter_bag', Container_Bag::class)->args([service('service_container')])->alias(Container_Bag_Interface::class, 'parameter_bag')->alias(Parameter_Bag_Interface::class, 'parameter_bag')->set('event_dispatcher', Event_Dispatcher::class)->public()->tag('container.hot_path')->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher'])->alias(Event_Dispatcher_Interface_Component_Alias::class, 'event_dispatcher')->alias(Event_Dispatcher_Interface::class, 'event_dispatcher')->alias(Psr_Event_Dispatcher_Interface::class, 'event_dispatcher')->set('http_kernel', Http_Kernel::class)->public()->args([service('event_dispatcher'), service('controller_resolver'), service('request_stack'), service('argument_resolver'), false])->tag('container.hot_path')->tag('container.preload', ['class' => Http_Kernel_Runner::class])->tag('container.preload', ['class' => Response_Runner::class])->tag('container.preload', ['class' => Symfony_Runtime::class])->alias(Http_Kernel_Interface::class, 'http_kernel')->set('request_stack', Request_Stack::class)->tag('kernel.reset', ['method' => 'resetRequestFormats', 'on_invalid' => 'ignore'])->public()->alias(Request_Stack::class, 'request_stack')->set('http_cache', Http_Cache::class)->args([service('kernel'), service('http_cache.store'), service('esi')->null_on_invalid(), abstract_arg('options')])->tag('container.hot_path')->set('http_cache.store', Store::class)->args([param('kernel.share_dir') . '/http_cache'])->alias(Store_Interface::class, 'http_cache.store')->set('url_helper', Url_Helper::class)->args([service('request_stack'), service('router')->ignore_on_invalid()])->alias(Url_Helper::class, 'url_helper')->set('cache_warmer', Cache_Warmer_Aggregate::class)->public()->args([tagged_iterator('kernel.cache_warmer'), param('kernel.debug'), \sprintf('%s/%sDeprecations.log', param('kernel.build_dir'), param('kernel.container_class'))])->tag('container.no_preload')->set('cache_clearer', Chain_Cache_Clearer::class)->args([tagged_iterator('kernel.cache_clearer')])->set('kernel')->synthetic()->public()->alias(Kernel_Interface::class, 'kernel')->set('filesystem', Filesystem::class)->alias(Filesystem::class, 'filesystem')->set('file_locator', File_Locator::class)->args([service('kernel')])->alias(File_Locator::class, 'file_locator')->set('uri_signer', Uri_Signer::class)->args([new Parameter('kernel.secret'), '_hash', '_expiration', service('clock')->null_on_invalid()])->lazy()->alias(Uri_Signer::class, 'uri_signer')->set('config_cache_factory', Resource_Checker_Config_Cache_Factory::class)->args([tagged_iterator('config_cache.resource_checker')])->set('dependency_injection.config.container_parameters_resource_checker', Container_Parameters_Resource_Checker::class)->args([service('service_container')])->tag('config_cache.resource_checker', ['priority' => -980])->set('config.resource.self_checking_resource_checker', Self_Checking_Resource_Checker::class)->tag('config_cache.resource_checker', ['priority' => -990])->set('services_resetter', Services_Resetter::class)->public()->alias(Services_Resetter_Interface::class, 'services_resetter')->set('reverse_container', Reverse_Container::class)->args([service('service_container'), service_locator([])])->alias(Reverse_Container::class, 'reverse_container')->set('locale_aware_listener', Locale_Aware_Listener::class)->args([
        [],
        // locale aware services
        service('request_stack'),
    ])->tag('kernel.event_subscriber')->set('container.env_var_processor', Env_Var_Processor::class)->args([service('service_container'), tagged_iterator('container.env_var_loader')])->tag('container.env_var_processor')->tag('kernel.reset', ['method' => 'reset'])->set('slugger', Ascii_Slugger::class)->args([param('kernel.default_locale')])->tag('kernel.locale_aware')->alias(Slugger_Interface::class, 'slugger')->set('container.getenv', \Closure::class)->factory([\Closure::class, 'fromCallable'])->args([[service('service_container'), 'getEnv']])->tag('routing.expression_language_function', ['function' => 'env'])->set('container.get_routing_condition_service', \Closure::class)->public()->factory([\Closure::class, 'fromCallable'])->args([[tagged_locator('routing.condition_service', 'alias'), 'get']])->tag('routing.expression_language_function', ['function' => 'service'])->set('container.env', Lazy_String::class)->abstract()->factory([Lazy_String::class, 'fromCallable'])->args([service('container.getenv')])->set('clock', Clock::class)->alias(Clock_Interface::class, 'clock')->alias(Psr_Clock_Interface::class, 'clock')->set(Loader_Interface::class)->abstract()->tag('container.excluded')->set(Request::class)->abstract()->tag('container.excluded')->set(Response::class)->abstract()->tag('container.excluded')->set(Session_Interface::class)->abstract()->tag('container.excluded');
};