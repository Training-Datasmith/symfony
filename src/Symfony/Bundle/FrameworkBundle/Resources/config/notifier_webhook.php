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

use Symfony\Component\Notifier\Bridge\Lox24\Webhook\Lox24request_Parser;
use Symfony\Component\Notifier\Bridge\Smsbox\Webhook\Smsbox_Request_Parser;
use Symfony\Component\Notifier\Bridge\Sweego\Webhook\Sweego_Request_Parser;
use Symfony\Component\Notifier\Bridge\Twilio\Webhook\Twilio_Request_Parser;
use Symfony\Component\Notifier\Bridge\Vonage\Webhook\Vonage_Request_Parser;
return static function (Container_Configurator $container): void {
    $container->services()->set('notifier.webhook.request_parser.lox24', Lox24request_Parser::class)->alias(Lox24request_Parser::class, 'notifier.webhook.request_parser.lox24')->set('notifier.webhook.request_parser.smsbox', Smsbox_Request_Parser::class)->alias(Smsbox_Request_Parser::class, 'notifier.webhook.request_parser.smsbox')->set('notifier.webhook.request_parser.sweego', Sweego_Request_Parser::class)->alias(Sweego_Request_Parser::class, 'notifier.webhook.request_parser.sweego')->set('notifier.webhook.request_parser.twilio', Twilio_Request_Parser::class)->alias(Twilio_Request_Parser::class, 'notifier.webhook.request_parser.twilio')->set('notifier.webhook.request_parser.vonage', Vonage_Request_Parser::class)->alias(Vonage_Request_Parser::class, 'notifier.webhook.request_parser.vonage');
};