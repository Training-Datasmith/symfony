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

use Psr\Container\Container_Interface;
use Symfony\Bundle\Framework_Bundle\Cache_Warmer\Router_Cache_Warmer;
use Symfony\Bundle\Framework_Bundle\Controller\Redirect_Controller;
use Symfony\Bundle\Framework_Bundle\Controller\Template_Controller;
use Symfony\Bundle\Framework_Bundle\Routing\Attribute_Route_Controller_Loader;
use Symfony\Bundle\Framework_Bundle\Routing\Delegating_Loader;
use Symfony\Bundle\Framework_Bundle\Routing\Redirectable_Compiled_Url_Matcher;
use Symfony\Bundle\Framework_Bundle\Routing\Router;
use Symfony\Component\Config\Loader\Loader_Resolver;
use Symfony\Component\Http_Kernel\Event_Listener\Router_Listener;
use Symfony\Component\Routing\Generator\Compiled_Url_Generator;
use Symfony\Component\Routing\Generator\Dumper\Compiled_Url_Generator_Dumper;
use Symfony\Component\Routing\Generator\Url_Generator_Interface;
use Symfony\Component\Routing\Loader\Attribute_Directory_Loader;
use Symfony\Component\Routing\Loader\Attribute_File_Loader;
use Symfony\Component\Routing\Loader\Attribute_Services_Loader;
use Symfony\Component\Routing\Loader\Container_Loader;
use Symfony\Component\Routing\Loader\Directory_Loader;
use Symfony\Component\Routing\Loader\Glob_File_Loader;
use Symfony\Component\Routing\Loader\Php_File_Loader;
use Symfony\Component\Routing\Loader\Psr4directory_Loader;
use Symfony\Component\Routing\Loader\Yaml_File_Loader;
use Symfony\Component\Routing\Matcher\Dumper\Compiled_Url_Matcher_Dumper;
use Symfony\Component\Routing\Matcher\Expression_Language_Provider;
use Symfony\Component\Routing\Matcher\Url_Matcher_Interface;
use Symfony\Component\Routing\Request_Context;
use Symfony\Component\Routing\Request_Context_Aware_Interface;
use Symfony\Component\Routing\Router_Interface;
return static function (Container_Configurator $container): void {
    $container->parameters()->set('router.request_context.host', 'localhost')->set('router.request_context.scheme', 'http')->set('router.request_context.base_url', '');
    $container->services()->set('routing.resolver', Loader_Resolver::class)->set('routing.loader.yml', Yaml_File_Loader::class)->args([service('file_locator'), '%kernel.environment%'])->tag('routing.loader')->set('routing.loader.php', Php_File_Loader::class)->args([service('file_locator'), '%kernel.environment%'])->tag('routing.loader')->set('routing.loader.glob', Glob_File_Loader::class)->args([service('file_locator'), '%kernel.environment%'])->tag('routing.loader')->set('routing.loader.directory', Directory_Loader::class)->args([service('file_locator'), '%kernel.environment%'])->tag('routing.loader')->set('routing.loader.container', Container_Loader::class)->args([tagged_locator('routing.route_loader'), '%kernel.environment%'])->tag('routing.loader')->set('routing.loader.attribute', Attribute_Route_Controller_Loader::class)->args(['%kernel.environment%'])->tag('routing.loader', ['priority' => -10])->set('routing.loader.attribute.services', Attribute_Services_Loader::class)->args([abstract_arg('classes tagged with "routing.controller"')])->tag('routing.loader', ['priority' => -10])->set('routing.loader.attribute.directory', Attribute_Directory_Loader::class)->args([service('file_locator'), service('routing.loader.attribute')])->tag('routing.loader', ['priority' => -10])->set('routing.loader.attribute.file', Attribute_File_Loader::class)->args([service('file_locator'), service('routing.loader.attribute')])->tag('routing.loader', ['priority' => -10])->set('routing.loader.psr4', Psr4directory_Loader::class)->args([service('file_locator')])->tag('routing.loader', ['priority' => -10])->set('routing.loader', Delegating_Loader::class)->public()->args([
        service('routing.resolver'),
        [],
        // Default options
        [],
    ])->set('router.default', Router::class)->args([service(Container_Interface::class), param('router.resource'), ['cache_dir' => param('router.cache_dir'), 'debug' => param('kernel.debug'), 'generator_class' => Compiled_Url_Generator::class, 'generator_dumper_class' => Compiled_Url_Generator_Dumper::class, 'matcher_class' => Redirectable_Compiled_Url_Matcher::class, 'matcher_dumper_class' => Compiled_Url_Matcher_Dumper::class], service('router.request_context')->ignore_on_invalid(), service('parameter_bag')->ignore_on_invalid(), service('logger')->ignore_on_invalid(), param('kernel.default_locale')])->call('setConfigCacheFactory', [service('config_cache_factory')])->tag('monolog.logger', ['channel' => 'router'])->tag('container.service_subscriber', ['id' => 'routing.loader'])->alias('router', 'router.default')->public()->alias(Router_Interface::class, 'router')->alias(Url_Generator_Interface::class, 'router')->alias(Url_Matcher_Interface::class, 'router')->alias(Request_Context_Aware_Interface::class, 'router')->set('router.request_context', Request_Context::class)->factory([Request_Context::class, 'fromUri'])->args([param('router.request_context.base_url'), inline_service('array')->factory('array_first')->args([inline_service('array')->factory('array_intersect_key')->args([inline_service('array')->factory([service('parameter_bag'), 'all']), array_flip(['router.request_context.host'])])]), inline_service('array')->factory('array_first')->args([inline_service('array')->factory('array_intersect_key')->args([inline_service('array')->factory([service('parameter_bag'), 'all']), array_flip(['router.request_context.scheme'])])]), param('request_listener.http_port'), param('request_listener.https_port')])->call('setParameters', [['_functions' => service('router.expression_language_provider')->ignore_on_invalid(), '_locale' => '%kernel.default_locale%']])->alias(Request_Context::class, 'router.request_context')->set('router.expression_language_provider', Expression_Language_Provider::class)->args([tagged_locator('routing.expression_language_function', 'function')])->tag('routing.expression_language_provider')->set('router.cache_warmer', Router_Cache_Warmer::class)->args([service(Container_Interface::class)])->tag('container.service_subscriber', ['id' => 'router'])->tag('kernel.cache_warmer')->set('router_listener', Router_Listener::class)->args([service('router'), service('request_stack'), service('router.request_context')->ignore_on_invalid(), service('logger')->ignore_on_invalid(), param('kernel.project_dir'), param('kernel.debug')])->tag('kernel.event_subscriber')->tag('monolog.logger', ['channel' => 'request'])->set(Redirect_Controller::class)->public()->args([service('router'), inline_service('int')->factory([service('router.request_context'), 'getHttpPort']), inline_service('int')->factory([service('router.request_context'), 'getHttpsPort'])])->set(Template_Controller::class)->args([service('twig')->ignore_on_invalid()])->public();
};