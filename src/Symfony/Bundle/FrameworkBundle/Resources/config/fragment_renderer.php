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

use Symfony\Component\Http_Kernel\Dependency_Injection\Lazy_Loading_Fragment_Handler;
use Symfony\Component\Http_Kernel\Fragment\Esi_Fragment_Renderer;
use Symfony\Component\Http_Kernel\Fragment\Fragment_Uri_Generator;
use Symfony\Component\Http_Kernel\Fragment\Fragment_Uri_Generator_Interface;
use Symfony\Component\Http_Kernel\Fragment\H_Include_Fragment_Renderer;
use Symfony\Component\Http_Kernel\Fragment\Inline_Fragment_Renderer;
use Symfony\Component\Http_Kernel\Fragment\Ssi_Fragment_Renderer;
return static function (Container_Configurator $container): void {
    $container->parameters()->set('fragment.renderer.hinclude.global_template', null)->set('fragment.path', '/_fragment');
    $container->services()->set('fragment.handler', Lazy_Loading_Fragment_Handler::class)->args([abstract_arg('fragment renderer locator'), service('request_stack'), param('kernel.debug')])->set('fragment.uri_generator', Fragment_Uri_Generator::class)->args([param('fragment.path'), service('uri_signer'), service('request_stack')])->alias(Fragment_Uri_Generator_Interface::class, 'fragment.uri_generator')->set('fragment.renderer.inline', Inline_Fragment_Renderer::class)->args([service('http_kernel'), service('event_dispatcher')])->call('setFragmentPath', [param('fragment.path')])->tag('kernel.fragment_renderer', ['alias' => 'inline'])->set('fragment.renderer.hinclude', H_Include_Fragment_Renderer::class)->args([service('twig')->null_on_invalid(), service('uri_signer'), param('fragment.renderer.hinclude.global_template')])->call('setFragmentPath', [param('fragment.path')])->set('fragment.renderer.esi', Esi_Fragment_Renderer::class)->args([service('esi')->null_on_invalid(), service('fragment.renderer.inline'), service('uri_signer')])->call('setFragmentPath', [param('fragment.path')])->tag('kernel.fragment_renderer', ['alias' => 'esi'])->set('fragment.renderer.ssi', Ssi_Fragment_Renderer::class)->args([service('ssi')->null_on_invalid(), service('fragment.renderer.inline'), service('uri_signer')])->call('setFragmentPath', [param('fragment.path')])->tag('kernel.fragment_renderer', ['alias' => 'ssi']);
};