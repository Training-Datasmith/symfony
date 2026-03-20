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
use Symfony\Bridge\Twig\App_Variable;
use Symfony\Bridge\Twig\Data_Collector\Twig_Data_Collector;
use Symfony\Bridge\Twig\Error_Renderer\Twig_Error_Renderer;
use Symfony\Bridge\Twig\Event_Listener\Template_Attribute_Listener;
use Symfony\Bridge\Twig\Extension\Asset_Extension;
use Symfony\Bridge\Twig\Extension\Emoji_Extension;
use Symfony\Bridge\Twig\Extension\Expression_Extension;
use Symfony\Bridge\Twig\Extension\Html_Sanitizer_Extension;
use Symfony\Bridge\Twig\Extension\Http_Foundation_Extension;
use Symfony\Bridge\Twig\Extension\Http_Kernel_Extension;
use Symfony\Bridge\Twig\Extension\Http_Kernel_Runtime;
use Symfony\Bridge\Twig\Extension\Profiler_Extension;
use Symfony\Bridge\Twig\Extension\Routing_Extension;
use Symfony\Bridge\Twig\Extension\Serializer_Extension;
use Symfony\Bridge\Twig\Extension\Serializer_Runtime;
use Symfony\Bridge\Twig\Extension\Stopwatch_Extension;
use Symfony\Bridge\Twig\Extension\Translation_Extension;
use Symfony\Bridge\Twig\Extension\Web_Link_Extension;
use Symfony\Bridge\Twig\Extension\Workflow_Extension;
use Symfony\Bridge\Twig\Extension\Yaml_Extension;
use Symfony\Bridge\Twig\Translation\Twig_Extractor;
use Symfony\Bundle\Twig_Bundle\Cache_Warmer\Template_Cache_Warmer;
use Symfony\Bundle\Twig_Bundle\Dependency_Injection\Configurator\Environment_Configurator;
use Symfony\Bundle\Twig_Bundle\Template_Iterator;
use Twig\Cache\Chain_Cache;
use Twig\Cache\Filesystem_Cache;
use Twig\Cache\Read_Only_Filesystem_Cache;
use Twig\Environment;
use Twig\Expression_Parser\Infix\Binary_Operator_Expression_Parser;
use Twig\Extension\Core_Extension;
use Twig\Extension\Debug_Extension;
use Twig\Extension\Escaper_Extension;
use Twig\Extension\Optimizer_Extension;
use Twig\Extension\Staging_Extension;
use Twig\Extension_Set;
use Twig\Loader\Chain_Loader;
use Twig\Loader\Filesystem_Loader;
use Twig\Profiler\Profile;
use Twig\Runtime_Loader\Container_Runtime_Loader;
use Twig\Template;
use Twig\Template_Wrapper;
return static function (Container_Configurator $container): void {
    $container->services()->set('twig', Environment::class)->args([service('twig.loader'), abstract_arg('Twig options')])->call('addGlobal', ['app', service('twig.app_variable')])->call('addRuntimeLoader', [service('twig.runtime_loader')])->configurator([service('twig.configurator.environment'), 'configure'])->tag('container.preload', ['class' => Filesystem_Cache::class])->tag('container.preload', ['class' => Core_Extension::class])->tag('container.preload', ['class' => Escaper_Extension::class])->tag('container.preload', ['class' => Optimizer_Extension::class])->tag('container.preload', ['class' => Staging_Extension::class])->tag('container.preload', ['class' => Binary_Operator_Expression_Parser::class])->tag('container.preload', ['class' => Extension_Set::class])->tag('container.preload', ['class' => Template::class])->tag('container.preload', ['class' => Template_Wrapper::class])->tag('kernel.reset', ['method' => '?resetGlobals'])->alias(Environment::class, 'twig')->set('twig.app_variable', App_Variable::class)->call('setEnvironment', [param('kernel.environment')])->call('setDebug', [param('kernel.debug')])->call('setTokenStorage', [service('security.token_storage')->ignore_on_invalid()])->call('setRequestStack', [service('request_stack')->ignore_on_invalid()])->call('setLocaleSwitcher', [service('translation.locale_switcher')->ignore_on_invalid()])->call('setEnabledLocales', [param('kernel.enabled_locales')])->set('twig.template_iterator', Template_Iterator::class)->args([service('kernel'), abstract_arg('Twig paths'), param('twig.default_path'), abstract_arg('File name pattern')])->set('twig.template_cache.runtime_cache', Filesystem_Cache::class)->args([param('kernel.cache_dir') . '/twig'])->set('twig.template_cache.readonly_cache', Read_Only_Filesystem_Cache::class)->args([param('kernel.build_dir') . '/twig'])->set('twig.template_cache.warmup_cache', Filesystem_Cache::class)->args([param('kernel.build_dir') . '/twig'])->set('twig.template_cache.chain', Chain_Cache::class)->args([[service('twig.template_cache.readonly_cache'), service('twig.template_cache.runtime_cache')]])->set('twig.template_cache_warmer', Template_Cache_Warmer::class)->args([service(Container_Interface::class), service('twig.template_iterator'), service('twig.template_cache.warmup_cache')])->tag('kernel.cache_warmer')->tag('container.service_subscriber', ['id' => 'twig'])->set('twig.loader.native_filesystem', Filesystem_Loader::class)->args([[], param('kernel.project_dir')])->tag('twig.loader')->set('twig.loader.chain', Chain_Loader::class)->set('twig.extension.profiler', Profiler_Extension::class)->args([service('twig.profile'), service('debug.stopwatch')->ignore_on_invalid()])->set('twig.profile', Profile::class)->set('data_collector.twig', Twig_Data_Collector::class)->args([service('twig.profile'), service('twig')])->tag('data_collector', ['template' => '@WebProfiler/Collector/twig.html.twig', 'id' => 'twig', 'priority' => 257])->set('twig.extension.trans', Translation_Extension::class)->args([service('translator')->null_on_invalid()])->tag('twig.extension')->set('twig.extension.assets', Asset_Extension::class)->args([service('assets.packages')])->set('twig.extension.routing', Routing_Extension::class)->args([service('router')])->set('twig.extension.yaml', Yaml_Extension::class)->set('twig.extension.debug.stopwatch', Stopwatch_Extension::class)->args([service('debug.stopwatch')->ignore_on_invalid(), param('kernel.debug')])->set('twig.extension.expression', Expression_Extension::class)->set('twig.extension.emoji', Emoji_Extension::class)->set('twig.extension.htmlsanitizer', Html_Sanitizer_Extension::class)->args([tagged_locator('html_sanitizer', 'sanitizer')])->set('twig.extension.httpkernel', Http_Kernel_Extension::class)->set('twig.runtime.httpkernel', Http_Kernel_Runtime::class)->args([service('fragment.handler'), service('fragment.uri_generator')->ignore_on_invalid()])->set('twig.extension.httpfoundation', Http_Foundation_Extension::class)->args([service('url_helper')])->set('twig.extension.debug', Debug_Extension::class)->set('twig.extension.weblink', Web_Link_Extension::class)->args([service('request_stack')])->set('twig.translation.extractor', Twig_Extractor::class)->args([service('twig')])->tag('translation.extractor', ['alias' => 'twig'])->set('workflow.twig_extension', Workflow_Extension::class)->args([service('workflow.registry')])->set('twig.configurator.environment', Environment_Configurator::class)->args([abstract_arg('date format, set in TwigExtension'), abstract_arg('interval format, set in TwigExtension'), abstract_arg('timezone, set in TwigExtension'), abstract_arg('decimals, set in TwigExtension'), abstract_arg('decimal point, set in TwigExtension'), abstract_arg('thousands separator, set in TwigExtension')])->set('twig.runtime_loader', Container_Runtime_Loader::class)->args([abstract_arg('runtime locator')])->set('twig.error_renderer.html', Twig_Error_Renderer::class)->decorate('error_renderer.html')->args([service('twig'), service('twig.error_renderer.html.inner'), inline_service('bool')->factory([Twig_Error_Renderer::class, 'isDebug'])->args([service('request_stack'), param('kernel.debug')])])->set('twig.runtime.serializer', Serializer_Runtime::class)->args([service('serializer')])->set('twig.extension.serializer', Serializer_Extension::class)->set('controller.template_attribute_listener', Template_Attribute_Listener::class)->args([service('twig')])->tag('kernel.event_subscriber');
};