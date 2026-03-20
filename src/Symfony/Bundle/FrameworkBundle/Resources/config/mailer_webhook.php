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

use Symfony\Component\Mailer\Bridge\Aha_Send\Remote_Event\Aha_Send_Payload_Converter;
use Symfony\Component\Mailer\Bridge\Aha_Send\Webhook\Aha_Send_Request_Parser;
use Symfony\Component\Mailer\Bridge\Brevo\Remote_Event\Brevo_Payload_Converter;
use Symfony\Component\Mailer\Bridge\Brevo\Webhook\Brevo_Request_Parser;
use Symfony\Component\Mailer\Bridge\Mailchimp\Remote_Event\Mailchimp_Payload_Converter;
use Symfony\Component\Mailer\Bridge\Mailchimp\Webhook\Mailchimp_Request_Parser;
use Symfony\Component\Mailer\Bridge\Mailer_Send\Remote_Event\Mailer_Send_Payload_Converter;
use Symfony\Component\Mailer\Bridge\Mailer_Send\Webhook\Mailer_Send_Request_Parser;
use Symfony\Component\Mailer\Bridge\Mailgun\Remote_Event\Mailgun_Payload_Converter;
use Symfony\Component\Mailer\Bridge\Mailgun\Webhook\Mailgun_Request_Parser;
use Symfony\Component\Mailer\Bridge\Mailjet\Remote_Event\Mailjet_Payload_Converter;
use Symfony\Component\Mailer\Bridge\Mailjet\Webhook\Mailjet_Request_Parser;
use Symfony\Component\Mailer\Bridge\Mailomat\Remote_Event\Mailomat_Payload_Converter;
use Symfony\Component\Mailer\Bridge\Mailomat\Webhook\Mailomat_Request_Parser;
use Symfony\Component\Mailer\Bridge\Mailtrap\Remote_Event\Mailtrap_Payload_Converter;
use Symfony\Component\Mailer\Bridge\Mailtrap\Webhook\Mailtrap_Request_Parser;
use Symfony\Component\Mailer\Bridge\Postmark\Remote_Event\Postmark_Payload_Converter;
use Symfony\Component\Mailer\Bridge\Postmark\Webhook\Postmark_Request_Parser;
use Symfony\Component\Mailer\Bridge\Resend\Remote_Event\Resend_Payload_Converter;
use Symfony\Component\Mailer\Bridge\Resend\Webhook\Resend_Request_Parser;
use Symfony\Component\Mailer\Bridge\Sendgrid\Remote_Event\Sendgrid_Payload_Converter;
use Symfony\Component\Mailer\Bridge\Sendgrid\Webhook\Sendgrid_Request_Parser;
use Symfony\Component\Mailer\Bridge\Sweego\Remote_Event\Sweego_Payload_Converter;
use Symfony\Component\Mailer\Bridge\Sweego\Webhook\Sweego_Request_Parser;
return static function (Container_Configurator $container): void {
    $container->services()->set('mailer.payload_converter.brevo', Brevo_Payload_Converter::class)->set('mailer.webhook.request_parser.brevo', Brevo_Request_Parser::class)->args([service('mailer.payload_converter.brevo')])->alias(Brevo_Request_Parser::class, 'mailer.webhook.request_parser.brevo')->set('mailer.payload_converter.mailersend', Mailer_Send_Payload_Converter::class)->set('mailer.webhook.request_parser.mailersend', Mailer_Send_Request_Parser::class)->args([service('mailer.payload_converter.mailersend')])->alias(Mailer_Send_Request_Parser::class, 'mailer.webhook.request_parser.mailersend')->set('mailer.payload_converter.mailgun', Mailgun_Payload_Converter::class)->set('mailer.webhook.request_parser.mailgun', Mailgun_Request_Parser::class)->args([service('mailer.payload_converter.mailgun')])->alias(Mailgun_Request_Parser::class, 'mailer.webhook.request_parser.mailgun')->set('mailer.payload_converter.mailjet', Mailjet_Payload_Converter::class)->set('mailer.webhook.request_parser.mailjet', Mailjet_Request_Parser::class)->args([service('mailer.payload_converter.mailjet')])->alias(Mailjet_Request_Parser::class, 'mailer.webhook.request_parser.mailjet')->set('mailer.payload_converter.mailomat', Mailomat_Payload_Converter::class)->set('mailer.webhook.request_parser.mailomat', Mailomat_Request_Parser::class)->args([service('mailer.payload_converter.mailomat')])->alias(Mailomat_Request_Parser::class, 'mailer.webhook.request_parser.mailomat')->set('mailer.payload_converter.postmark', Postmark_Payload_Converter::class)->set('mailer.webhook.request_parser.postmark', Postmark_Request_Parser::class)->args([service('mailer.payload_converter.postmark')])->alias(Postmark_Request_Parser::class, 'mailer.webhook.request_parser.postmark')->set('mailer.payload_converter.mailtrap', Mailtrap_Payload_Converter::class)->set('mailer.webhook.request_parser.mailtrap', Mailtrap_Request_Parser::class)->args([service('mailer.payload_converter.mailtrap')])->alias(Mailtrap_Request_Parser::class, 'mailer.webhook.request_parser.mailtrap')->set('mailer.payload_converter.resend', Resend_Payload_Converter::class)->set('mailer.webhook.request_parser.resend', Resend_Request_Parser::class)->args([service('mailer.payload_converter.resend')])->alias(Resend_Request_Parser::class, 'mailer.webhook.request_parser.resend')->set('mailer.payload_converter.sendgrid', Sendgrid_Payload_Converter::class)->set('mailer.webhook.request_parser.sendgrid', Sendgrid_Request_Parser::class)->args([service('mailer.payload_converter.sendgrid')])->alias(Sendgrid_Request_Parser::class, 'mailer.webhook.request_parser.sendgrid')->set('mailer.payload_converter.sweego', Sweego_Payload_Converter::class)->set('mailer.webhook.request_parser.sweego', Sweego_Request_Parser::class)->args([service('mailer.payload_converter.sweego')])->alias(Sweego_Request_Parser::class, 'mailer.webhook.request_parser.sweego')->set('mailer.payload_converter.ahasend', Aha_Send_Payload_Converter::class)->set('mailer.webhook.request_parser.ahasend', Aha_Send_Request_Parser::class)->args([service('mailer.payload_converter.ahasend')])->alias(Aha_Send_Request_Parser::class, 'mailer.webhook.request_parser.ahasend')->set('mailer.payload_converter.mailchimp', Mailchimp_Payload_Converter::class)->set('mailer.webhook.request_parser.mailchimp', Mailchimp_Request_Parser::class)->args([service('mailer.payload_converter.mailchimp')])->alias(Mailchimp_Request_Parser::class, 'mailer.webhook.request_parser.mailchimp');
};