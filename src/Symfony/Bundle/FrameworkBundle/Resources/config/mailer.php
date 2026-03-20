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

use Symfony\Component\Mailer\Command\Mailer_Test_Command;
use Symfony\Component\Mailer\Event_Listener\Dkim_Signed_Message_Listener;
use Symfony\Component\Mailer\Event_Listener\Envelope_Listener;
use Symfony\Component\Mailer\Event_Listener\Message_Listener;
use Symfony\Component\Mailer\Event_Listener\Message_Logger_Listener;
use Symfony\Component\Mailer\Event_Listener\Messenger_Transport_Listener;
use Symfony\Component\Mailer\Event_Listener\Smime_Encrypted_Message_Listener;
use Symfony\Component\Mailer\Event_Listener\Smime_Signed_Message_Listener;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Mailer_Interface;
use Symfony\Component\Mailer\Messenger\Message_Handler;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Transport_Interface;
use Symfony\Component\Mailer\Transport\Transports;
use Symfony\Component\Mime\Crypto\Dkim_Signer;
use Symfony\Component\Mime\Crypto\S_Mime_Signer;
return static function (Container_Configurator $container): void {
    $container->services()->set('mailer.mailer', Mailer::class)->args([service('mailer.transports'), abstract_arg('message bus'), service('event_dispatcher')->ignore_on_invalid()])->alias('mailer', 'mailer.mailer')->alias(Mailer_Interface::class, 'mailer.mailer')->set('mailer.transports', Transports::class)->factory([service('mailer.transport_factory'), 'fromStrings'])->args([abstract_arg('transports')])->set('mailer.transport_factory', Transport::class)->args([tagged_iterator('mailer.transport_factory')])->alias('mailer.default_transport', 'mailer.transports')->alias(Transport_Interface::class, 'mailer.default_transport')->set('mailer.messenger.message_handler', Message_Handler::class)->args([service('mailer.transports')])->tag('messenger.message_handler')->set('mailer.envelope_listener', Envelope_Listener::class)->args([abstract_arg('sender'), abstract_arg('recipients')])->tag('kernel.event_subscriber')->set('mailer.message_listener', Message_Listener::class)->args([abstract_arg('headers')])->tag('kernel.event_subscriber')->set('mailer.message_logger_listener', Message_Logger_Listener::class)->tag('kernel.event_subscriber')->tag('kernel.reset', ['method' => 'reset'])->set('mailer.messenger_transport_listener', Messenger_Transport_Listener::class)->tag('kernel.event_subscriber')->set('mailer.dkim_signer', Dkim_Signer::class)->args([abstract_arg('key'), abstract_arg('domain'), abstract_arg('select'), abstract_arg('options'), abstract_arg('passphrase')])->set('mailer.smime_signer', S_Mime_Signer::class)->args([abstract_arg('certificate'), abstract_arg('key'), abstract_arg('passphrase'), abstract_arg('extraCertificates'), abstract_arg('signOptions')])->set('mailer.dkim_signer.listener', Dkim_Signed_Message_Listener::class)->args([service('mailer.dkim_signer')])->tag('kernel.event_subscriber')->set('mailer.smime_signer.listener', Smime_Signed_Message_Listener::class)->args([service('mailer.smime_signer')])->tag('kernel.event_subscriber')->set('mailer.smime_encrypter.listener', Smime_Encrypted_Message_Listener::class)->args([service('mailer.smime_encrypter.repository'), param('mailer.smime_encrypter.cipher')])->tag('kernel.event_subscriber')->set('console.command.mailer_test', Mailer_Test_Command::class)->args([service('mailer.transports')])->tag('console.command');
};