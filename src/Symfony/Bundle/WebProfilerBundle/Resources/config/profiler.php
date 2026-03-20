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

use Symfony\Bundle\Web_Profiler_Bundle\Controller\Exception_Panel_Controller;
use Symfony\Bundle\Web_Profiler_Bundle\Controller\Profiler_Controller;
use Symfony\Bundle\Web_Profiler_Bundle\Controller\Router_Controller;
use Symfony\Bundle\Web_Profiler_Bundle\Csp\Content_Security_Policy_Handler;
use Symfony\Bundle\Web_Profiler_Bundle\Csp\Nonce_Generator;
use Symfony\Bundle\Web_Profiler_Bundle\Profiler\Code_Extension;
use Symfony\Bundle\Web_Profiler_Bundle\Twig\Web_Profiler_Extension;
use Symfony\Component\Error_Handler\Error_Renderer\File_Link_Formatter;
use Symfony\Component\Var_Dumper\Dumper\Html_Dumper;
return static function (Container_Configurator $container): void {
    $container->services()->set('web_profiler.controller.profiler', Profiler_Controller::class)->public()->args([service('router')->null_on_invalid(), service('profiler')->null_on_invalid(), service('twig'), param('data_collector.templates'), service('web_profiler.csp.handler'), param('kernel.project_dir')])->set('web_profiler.controller.router', Router_Controller::class)->public()->args([service('profiler')->null_on_invalid(), service('twig'), service('router')->null_on_invalid(), null, tagged_iterator('routing.expression_language_provider')])->set('web_profiler.controller.exception_panel', Exception_Panel_Controller::class)->public()->args([service('error_handler.error_renderer.html'), service('profiler')->null_on_invalid()])->set('web_profiler.csp.handler', Content_Security_Policy_Handler::class)->args([inline_service(Nonce_Generator::class)])->set('twig.extension.webprofiler', Web_Profiler_Extension::class)->args([inline_service(Html_Dumper::class)->args([null, param('kernel.charset'), Html_Dumper::DUMP_LIGHT_ARRAY])->call('setDisplayOptions', [['maxStringLength' => 4096, 'fileLinkFormat' => service('debug.file_link_formatter')]])])->tag('twig.extension')->set('debug.file_link_formatter', File_Link_Formatter::class)->args([param('debug.file_link_format'), service('request_stack')->ignore_on_invalid(), param('kernel.project_dir'), '/_profiler/open?file=%%f&line=%%l#line%%l'])->set('debug.file_link_formatter.url_format', 'string')->factory([File_Link_Formatter::class, 'generateUrlFormat'])->args([service('router'), '_profiler_open_file', '?file=%%f&line=%%l#line%%l'])->set('twig.extension.code', Code_Extension::class)->args([service('debug.file_link_formatter'), param('kernel.project_dir'), param('kernel.charset')])->tag('twig.extension');
};