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

use Http\Client\Http_Async_Client;
use Psr\Http\Client\Client_Interface;
use Psr\Http\Message\Response_Factory_Interface;
use Psr\Http\Message\Stream_Factory_Interface;
use Symfony\Component\Cache\Adapter\Tag_Aware_Adapter;
use Symfony\Component\Http_Client\Http_Client;
use Symfony\Component\Http_Client\Httplug_Client;
use Symfony\Component\Http_Client\Messenger\Ping_Webhook_Message_Handler;
use Symfony\Component\Http_Client\Mock_Http_Client;
use Symfony\Component\Http_Client\Psr18Client;
use Symfony\Component\Http_Client\Retry\Generic_Retry_Strategy;
use Symfony\Component\Http_Client\Uri_Template_Http_Client;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('cache.http_client.pool')->parent('cache.app')->tag('cache.pool')->set('cache.http_client', Tag_Aware_Adapter::class)->args([service('cache.http_client.pool')])->tag('cache.taggable', ['pool' => 'cache.http_client.pool'])->set('http_client.transport', Http_Client_Interface::class)->factory([Http_Client::class, 'create'])->args([
        [],
        // default options
        abstract_arg('max host connections'),
    ])->call('setLogger', [service('logger')->ignore_on_invalid()])->tag('monolog.logger', ['channel' => 'http_client'])->tag('kernel.reset', ['method' => 'reset', 'on_invalid' => 'ignore'])->set('http_client.mock_transport', Mock_Http_Client::class)->tag('kernel.reset', ['method' => 'reset'])->set('http_client', Http_Client_Interface::class)->factory('current')->args([[service('http_client.transport')]])->tag('http_client.client')->tag('kernel.reset', ['method' => 'reset', 'on_invalid' => 'ignore'])->alias(Http_Client_Interface::class, 'http_client')->set('psr18.http_client', Psr18Client::class)->args([service('http_client'), service(Response_Factory_Interface::class)->ignore_on_invalid(), service(Stream_Factory_Interface::class)->ignore_on_invalid()])->alias(Client_Interface::class, 'psr18.http_client')->set('httplug.http_client', Httplug_Client::class)->args([service('http_client'), service(Response_Factory_Interface::class)->ignore_on_invalid(), service(Stream_Factory_Interface::class)->ignore_on_invalid()])->alias(Http_Async_Client::class, 'httplug.http_client')->set('http_client.abstract_retry_strategy', Generic_Retry_Strategy::class)->abstract()->args([abstract_arg('http codes'), abstract_arg('delay ms'), abstract_arg('multiplier'), abstract_arg('max delay ms'), abstract_arg('jitter')])->set('http_client.uri_template', Uri_Template_Http_Client::class)->decorate('http_client', null, 7)->args([service('.inner'), service('http_client.uri_template_expander')->null_on_invalid(), abstract_arg('default vars')])->set('http_client.uri_template_expander.guzzle', \Closure::class)->factory([\Closure::class, 'fromCallable'])->args([[\Guzzle_Http\Uri_Template\Uri_Template::class, 'expand']])->set('http_client.uri_template_expander.rize', \Closure::class)->factory([\Closure::class, 'fromCallable'])->args([[inline_service(\Rize\Uri_Template::class), 'expand']])->set('http_client.messenger.ping_webhook_handler', Ping_Webhook_Message_Handler::class)->args([service('http_client')])->tag('messenger.message_handler');
};