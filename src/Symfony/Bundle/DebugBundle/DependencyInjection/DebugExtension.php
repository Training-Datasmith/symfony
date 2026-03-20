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
namespace Symfony\Bundle\Debug_Bundle\Dependency_Injection;

use Symfony\Bridge\Monolog\Command\Server_Log_Command;
use Symfony\Bundle\Debug_Bundle\Command\Server_Dump_Placeholder_Command;
use Symfony\Component\Config\File_Locator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Extension\Extension;
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Var_Dumper\Caster\Reflection_Caster;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Debug_Extension extends Extension
{
    public function load(array $configs, Container_Builder $container): void
    {
        $configuration = new Configuration();
        $config = $this->process_configuration($configuration, $configs);
        $loader = new Php_File_Loader($container, new File_Locator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
        $container->get_definition('var_dumper.cloner')->add_method_call('setMaxItems', [$config['max_items']])->add_method_call('setMinDepth', [$config['min_depth']])->add_method_call('setMaxString', [$config['max_string_length']])->add_method_call('addCasters', [Reflection_Caster::UNSET_CLOSURE_FILE_INFO]);
        if ('dark' !== $config['theme']) {
            $container->get_definition('var_dumper.html_dumper')->add_method_call('setTheme', [$config['theme']]);
        }
        if (null === $config['dump_destination']) {
            $container->get_definition('var_dumper.command.server_dump')->set_class(Server_Dump_Placeholder_Command::class);
        } elseif (str_starts_with($config['dump_destination'], 'tcp://')) {
            $container->get_definition('debug.dump_listener')->replace_argument(2, new Reference('var_dumper.server_connection'));
            $container->get_definition('data_collector.dump')->replace_argument(4, new Reference('var_dumper.server_connection'));
            $container->get_definition('var_dumper.dump_server')->replace_argument(0, $config['dump_destination']);
            $container->get_definition('var_dumper.server_connection')->replace_argument(0, $config['dump_destination']);
        } else {
            $container->get_definition('var_dumper.cli_dumper')->replace_argument(0, $config['dump_destination']);
            $container->get_definition('data_collector.dump')->replace_argument(4, new Reference('var_dumper.cli_dumper'));
            $container->get_definition('var_dumper.command.server_dump')->set_class(Server_Dump_Placeholder_Command::class);
        }
        $container->get_definition('var_dumper.cli_dumper')->add_method_call('setDisplayOptions', [['fileLinkFormat' => new Reference('debug.file_link_formatter', Container_Builder::IGNORE_ON_INVALID_REFERENCE)]]);
        if (!class_exists(Command::class) || !class_exists(Server_Log_Command::class)) {
            $container->remove_definition('monolog.command.server_log');
        }
    }
}