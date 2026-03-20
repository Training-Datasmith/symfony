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

use Symfony\Component\Mailer\Bridge\Aha_Send\Transport\Aha_Send_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\Ses_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Azure\Transport\Azure_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\Brevo_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Google\Transport\Gmail_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Infobip\Transport\Infobip_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\Mandrill_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Mailer_Send\Transport\Mailer_Send_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\Mailgun_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Mailjet\Transport\Mailjet_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Mailomat\Transport\Mailomat_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Mail_Pace\Transport\Mail_Pace_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\Mailtrap_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Microsoft_Graph\Transport\Microsoft_Graph_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Postal\Transport\Postal_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\Postmark_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Resend\Transport\Resend_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Scaleway\Transport\Scaleway_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\Sendgrid_Transport_Factory;
use Symfony\Component\Mailer\Bridge\Sweego\Transport\Sweego_Transport_Factory;
use Symfony\Component\Mailer\Transport\Abstract_Transport_Factory;
use Symfony\Component\Mailer\Transport\Native_Transport_Factory;
use Symfony\Component\Mailer\Transport\Null_Transport_Factory;
use Symfony\Component\Mailer\Transport\Sendmail_Transport_Factory;
use Symfony\Component\Mailer\Transport\Smtp\Esmtp_Transport_Factory;
return static function (Container_Configurator $container): void {
    $container->services()->set('mailer.transport_factory.abstract', Abstract_Transport_Factory::class)->abstract()->args([service('event_dispatcher'), service('http_client')->ignore_on_invalid(), service('logger')->ignore_on_invalid()])->tag('monolog.logger', ['channel' => 'mailer']);
    $factories = ['ahasend' => Aha_Send_Transport_Factory::class, 'amazon' => Ses_Transport_Factory::class, 'azure' => Azure_Transport_Factory::class, 'brevo' => Brevo_Transport_Factory::class, 'gmail' => Gmail_Transport_Factory::class, 'infobip' => Infobip_Transport_Factory::class, 'mailchimp' => Mandrill_Transport_Factory::class, 'mailersend' => Mailer_Send_Transport_Factory::class, 'mailgun' => Mailgun_Transport_Factory::class, 'mailjet' => Mailjet_Transport_Factory::class, 'mailomat' => Mailomat_Transport_Factory::class, 'mailpace' => Mail_Pace_Transport_Factory::class, 'microsoftgraph' => Microsoft_Graph_Transport_Factory::class, 'native' => Native_Transport_Factory::class, 'null' => Null_Transport_Factory::class, 'postal' => Postal_Transport_Factory::class, 'postmark' => Postmark_Transport_Factory::class, 'mailtrap' => Mailtrap_Transport_Factory::class, 'resend' => Resend_Transport_Factory::class, 'scaleway' => Scaleway_Transport_Factory::class, 'sendgrid' => Sendgrid_Transport_Factory::class, 'sendmail' => Sendmail_Transport_Factory::class, 'smtp' => Esmtp_Transport_Factory::class, 'sweego' => Sweego_Transport_Factory::class];
    foreach ($factories as $name => $class) {
        $container->services()->set('mailer.transport_factory.' . $name, $class)->parent('mailer.transport_factory.abstract')->tag('mailer.transport_factory');
    }
};