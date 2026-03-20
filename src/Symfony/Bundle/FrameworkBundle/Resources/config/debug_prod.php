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

use Symfony\Component\Error_Handler\Error_Renderer\File_Link_Formatter;
use Symfony\Component\Http_Kernel\Debug\Error_Handler_Configurator;
use Symfony\Component\Http_Kernel\Event_Listener\Debug_Handlers_Listener;
return static function (Container_Configurator $container): void {
    $container->parameters()->set('debug.error_handler.throw_at', -1);
    $container->services()->set('debug.error_handler_configurator', Error_Handler_Configurator::class)->public()->args([
        service('logger')->null_on_invalid(),
        null,
        // Log levels map for enabled error levels
        param('debug.error_handler.throw_at'),
        param('kernel.debug'),
        param('kernel.debug'),
        null,
    ])->tag('monolog.logger', ['channel' => 'php'])->set('debug.debug_handlers_listener', Debug_Handlers_Listener::class)->args([null, param('kernel.runtime_mode.web')])->tag('kernel.event_subscriber')->set('debug.file_link_formatter', File_Link_Formatter::class)->args([param('debug.file_link_format')])->alias(File_Link_Formatter::class, 'debug.file_link_formatter');
};