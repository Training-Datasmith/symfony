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

use Symfony\Component\Webhook\Client\Request_Parser;
use Symfony\Component\Webhook\Controller\Webhook_Controller;
use Symfony\Component\Webhook\Messenger\Send_Webhook_Handler;
use Symfony\Component\Webhook\Server\Headers_Configurator;
use Symfony\Component\Webhook\Server\Header_Signature_Configurator;
use Symfony\Component\Webhook\Server\Json_Body_Configurator;
use Symfony\Component\Webhook\Server\Native_Json_Payload_Serializer;
use Symfony\Component\Webhook\Server\Serializer_Payload_Serializer;
use Symfony\Component\Webhook\Server\Transport;
return static function (Container_Configurator $container): void {
    $container->services()->set('webhook.transport', Transport::class)->args([service('http_client'), service('webhook.headers_configurator'), service('webhook.body_configurator.json'), service('webhook.signer')])->set('webhook.headers_configurator', Headers_Configurator::class)->set('webhook.body_configurator.json', Json_Body_Configurator::class)->args([abstract_arg('payload serializer')])->set('webhook.payload_serializer.json', Native_Json_Payload_Serializer::class)->set('webhook.payload_serializer.serializer', Serializer_Payload_Serializer::class)->args([service('serializer')])->set('webhook.signer', Header_Signature_Configurator::class)->set('webhook.messenger.send_handler', Send_Webhook_Handler::class)->args([service('webhook.transport')])->tag('messenger.message_handler')->set('webhook.request_parser', Request_Parser::class)->alias(Request_Parser::class, 'webhook.request_parser')->set('webhook.controller', Webhook_Controller::class)->public()->args([abstract_arg('user defined parsers'), abstract_arg('message bus')]);
};