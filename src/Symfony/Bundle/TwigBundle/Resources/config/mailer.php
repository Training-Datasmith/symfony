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

use Symfony\Bridge\Twig\Mime\Body_Renderer;
use Symfony\Component\Mailer\Event_Listener\Message_Listener;
use Symfony\Component\Mime\Body_Renderer_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('twig.mailer.message_listener', Message_Listener::class)->args([null, service('twig.mime_body_renderer')])->tag('kernel.event_subscriber')->set('twig.mime_body_renderer', Body_Renderer::class)->args([service('twig')])->alias(Body_Renderer_Interface::class, 'twig.mime_body_renderer');
};