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

use Symfony\Bundle\Framework_Bundle\Error_Handler\Error_Renderer\Runtime_Mode_Error_Renderer_Selector;
use Symfony\Component\Error_Handler\Error_Renderer\Cli_Error_Renderer;
use Symfony\Component\Error_Handler\Error_Renderer\Error_Renderer_Interface;
use Symfony\Component\Error_Handler\Error_Renderer\Html_Error_Renderer;
return static function (Container_Configurator $container): void {
    $container->services()->set('error_handler.error_renderer.html', Html_Error_Renderer::class)->args([inline_service()->factory([Html_Error_Renderer::class, 'isDebug'])->args([service('request_stack'), param('kernel.debug')]), param('kernel.charset'), service('debug.file_link_formatter')->null_on_invalid(), param('kernel.project_dir'), inline_service()->factory([Html_Error_Renderer::class, 'getAndCleanOutputBuffer'])->args([service('request_stack')]), service('logger')->null_on_invalid()])->set('error_handler.error_renderer.cli', Cli_Error_Renderer::class)->set('error_handler.error_renderer.default', Error_Renderer_Interface::class)->factory([Runtime_Mode_Error_Renderer_Selector::class, 'select'])->args([param('kernel.runtime_mode.web'), service_closure('error_renderer.html'), service_closure('error_renderer.cli')])->alias('error_renderer.html', 'error_handler.error_renderer.html')->alias('error_renderer.cli', 'error_handler.error_renderer.cli')->alias('error_renderer.default', 'error_handler.error_renderer.default')->alias('error_renderer', 'error_renderer.default');
};