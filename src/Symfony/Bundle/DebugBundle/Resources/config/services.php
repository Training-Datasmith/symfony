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

use Monolog\Formatter\Formatter_Interface;
use Symfony\Bridge\Monolog\Command\Server_Log_Command;
use Symfony\Bridge\Monolog\Formatter\Console_Formatter;
use Symfony\Bridge\Twig\Extension\Dump_Extension;
use Symfony\Component\Http_Kernel\Data_Collector\Dump_Data_Collector;
use Symfony\Component\Http_Kernel\Event_Listener\Dump_Listener;
use Symfony\Component\Var_Dumper\Cloner\Var_Cloner;
use Symfony\Component\Var_Dumper\Command\Descriptor\Cli_Descriptor;
use Symfony\Component\Var_Dumper\Command\Descriptor\Html_Descriptor;
use Symfony\Component\Var_Dumper\Command\Server_Dump_Command;
use Symfony\Component\Var_Dumper\Dumper\Cli_Dumper;
use Symfony\Component\Var_Dumper\Dumper\Context_Provider\Cli_Context_Provider;
use Symfony\Component\Var_Dumper\Dumper\Context_Provider\Request_Context_Provider;
use Symfony\Component\Var_Dumper\Dumper\Context_Provider\Source_Context_Provider;
use Symfony\Component\Var_Dumper\Dumper\Contextualized_Dumper;
use Symfony\Component\Var_Dumper\Dumper\Html_Dumper;
use Symfony\Component\Var_Dumper\Server\Connection;
use Symfony\Component\Var_Dumper\Server\Dump_Server;
return static function (Container_Configurator $container): void {
    $container->parameters()->set('env(VAR_DUMPER_SERVER)', '127.0.0.1:9912');
    $container->services()->set('twig.extension.dump', Dump_Extension::class)->args([service('var_dumper.cloner'), service('var_dumper.html_dumper')])->tag('twig.extension')->set('data_collector.dump', Dump_Data_Collector::class)->public()->args([
        service('debug.stopwatch')->ignore_on_invalid(),
        service('debug.file_link_formatter')->ignore_on_invalid(),
        param('kernel.charset'),
        service('.virtual_request_stack'),
        null,
        // var_dumper.cli_dumper or var_dumper.server_connection when debug.dump_destination is set
        param('kernel.runtime_mode.web'),
    ])->tag('data_collector', ['id' => 'dump', 'template' => '@Debug/Profiler/dump.html.twig', 'priority' => 240])->set('.lazy.data_collector.dump', Dump_Data_Collector::class)->factory('current')->args([[service('data_collector.dump')]])->lazy()->set('debug.dump_listener', Dump_Listener::class)->args([service('var_dumper.cloner'), service('var_dumper.cli_dumper'), null, service('.lazy.data_collector.dump')])->tag('kernel.event_subscriber')->set('var_dumper.cloner', Var_Cloner::class)->public()->set('var_dumper.cli_dumper', Cli_Dumper::class)->args([
        null,
        // debug.dump_destination,
        param('kernel.charset'),
        0,
    ])->set('var_dumper.contextualized_cli_dumper', Contextualized_Dumper::class)->decorate('var_dumper.cli_dumper')->args([service('var_dumper.contextualized_cli_dumper.inner'), ['source' => inline_service(Source_Context_Provider::class)->args([param('kernel.charset'), param('kernel.project_dir'), service('debug.file_link_formatter')->null_on_invalid()])]])->set('var_dumper.html_dumper', Html_Dumper::class)->args([null, param('kernel.charset'), 0])->call('setDisplayOptions', [['fileLinkFormat' => service('debug.file_link_formatter')->ignore_on_invalid()]])->set('var_dumper.server_connection', Connection::class)->args([
        '',
        // server host
        ['source' => inline_service(Source_Context_Provider::class)->args([param('kernel.charset'), param('kernel.project_dir'), service('debug.file_link_formatter')->null_on_invalid()]), 'request' => inline_service(Request_Context_Provider::class)->args([service('request_stack')]), 'cli' => inline_service(Cli_Context_Provider::class)],
    ])->set('var_dumper.dump_server', Dump_Server::class)->args([
        '',
        // server host
        service('logger')->null_on_invalid(),
    ])->tag('monolog.logger', ['channel' => 'debug'])->set('var_dumper.command.server_dump', Server_Dump_Command::class)->args([service('var_dumper.dump_server'), ['cli' => inline_service(Cli_Descriptor::class)->args([service('var_dumper.contextualized_cli_dumper.inner')]), 'html' => inline_service(Html_Descriptor::class)->args([service('var_dumper.html_dumper')])]])->tag('console.command')->set('monolog.command.server_log', Server_Log_Command::class);
    if (class_exists(Console_Formatter::class) && interface_exists(Formatter_Interface::class)) {
        $container->services()->get('monolog.command.server_log')->tag('console.command');
    }
};