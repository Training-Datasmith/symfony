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

use Symfony\Bundle\Framework_Bundle\Command\About_Command;
use Symfony\Bundle\Framework_Bundle\Command\Assets_Install_Command;
use Symfony\Bundle\Framework_Bundle\Command\Cache_Clear_Command;
use Symfony\Bundle\Framework_Bundle\Command\Cache_Pool_Clear_Command;
use Symfony\Bundle\Framework_Bundle\Command\Cache_Pool_Delete_Command;
use Symfony\Bundle\Framework_Bundle\Command\Cache_Pool_Invalidate_Tags_Command;
use Symfony\Bundle\Framework_Bundle\Command\Cache_Pool_List_Command;
use Symfony\Bundle\Framework_Bundle\Command\Cache_Pool_Prune_Command;
use Symfony\Bundle\Framework_Bundle\Command\Cache_Warmup_Command;
use Symfony\Bundle\Framework_Bundle\Command\Config_Debug_Command;
use Symfony\Bundle\Framework_Bundle\Command\Config_Dump_Reference_Command;
use Symfony\Bundle\Framework_Bundle\Command\Container_Debug_Command;
use Symfony\Bundle\Framework_Bundle\Command\Container_Lint_Command;
use Symfony\Bundle\Framework_Bundle\Command\Debug_Autowiring_Command;
use Symfony\Bundle\Framework_Bundle\Command\Event_Dispatcher_Debug_Command;
use Symfony\Bundle\Framework_Bundle\Command\Router_Debug_Command;
use Symfony\Bundle\Framework_Bundle\Command\Router_Match_Command;
use Symfony\Bundle\Framework_Bundle\Command\Secrets_Decrypt_To_Local_Command;
use Symfony\Bundle\Framework_Bundle\Command\Secrets_Encrypt_From_Local_Command;
use Symfony\Bundle\Framework_Bundle\Command\Secrets_Generate_Keys_Command;
use Symfony\Bundle\Framework_Bundle\Command\Secrets_List_Command;
use Symfony\Bundle\Framework_Bundle\Command\Secrets_Remove_Command;
use Symfony\Bundle\Framework_Bundle\Command\Secrets_Reveal_Command;
use Symfony\Bundle\Framework_Bundle\Command\Secrets_Set_Command;
use Symfony\Bundle\Framework_Bundle\Command\Translation_Debug_Command;
use Symfony\Bundle\Framework_Bundle\Command\Translation_Extract_Command;
use Symfony\Bundle\Framework_Bundle\Command\Yaml_Lint_Command;
use Symfony\Bundle\Framework_Bundle\Console\Application;
use Symfony\Bundle\Framework_Bundle\Event_Listener\Suggest_Missing_Package_Subscriber;
use Symfony\Component\Console\Argument_Resolver\Argument_Resolver;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver\Backed_Enum_Value_Resolver;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver\Builtin_Type_Value_Resolver;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver\Date_Time_Value_Resolver;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver\Default_Value_Resolver;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver\Input_File_Value_Resolver;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver\Map_Input_Value_Resolver;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver\Service_Value_Resolver;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver\Uid_Value_Resolver;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver\Variadic_Value_Resolver;
use Symfony\Component\Console\Event_Listener\Error_Listener;
use Symfony\Component\Console\Event_Listener\Validate_Question_Input_Listener;
use Symfony\Component\Console\Messenger\Run_Command_Message_Handler;
use Symfony\Component\Dotenv\Command\Debug_Command as DotenvDebugCommand;
use Symfony\Component\Error_Handler\Command\Error_Dump_Command;
use Symfony\Component\Form\Command\Debug_Command;
use Symfony\Component\Messenger\Command\Consume_Messages_Command;
use Symfony\Component\Messenger\Command\Debug_Command as MessengerDebugCommand;
use Symfony\Component\Messenger\Command\Failed_Messages_Remove_Command;
use Symfony\Component\Messenger\Command\Failed_Messages_Retry_Command;
use Symfony\Component\Messenger\Command\Failed_Messages_Show_Command;
use Symfony\Component\Messenger\Command\Setup_Transports_Command;
use Symfony\Component\Messenger\Command\Stats_Command;
use Symfony\Component\Messenger\Command\Stop_Workers_Command;
use Symfony\Component\Scheduler\Command\Debug_Command as SchedulerDebugCommand;
use Symfony\Component\Serializer\Command\Debug_Command as SerializerDebugCommand;
use Symfony\Component\Translation\Command\Translation_Lint_Command;
use Symfony\Component\Translation\Command\Translation_Pull_Command;
use Symfony\Component\Translation\Command\Translation_Push_Command;
use Symfony\Component\Translation\Command\Xliff_Lint_Command;
use Symfony\Component\Validator\Command\Debug_Command as ValidatorDebugCommand;
use Symfony\Component\Workflow\Command\Workflow_Dump_Command;
use Symfony\Webpack_Encore_Bundle\Asset\Entrypoint_Lookup_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('console.error_listener', Error_Listener::class)->args([service('logger')->null_on_invalid()])->tag('kernel.event_subscriber')->tag('monolog.logger', ['channel' => 'console'])->set('console.suggest_missing_package_subscriber', Suggest_Missing_Package_Subscriber::class)->tag('kernel.event_subscriber')->set('.console.validate_question_input_listener', Validate_Question_Input_Listener::class)->args([service('validator')])->tag('kernel.event_subscriber')->set('console.command.about', About_Command::class)->tag('console.command')->set('console.command.assets_install', Assets_Install_Command::class)->args([service('filesystem'), param('kernel.project_dir')])->tag('console.command')->set('console.command.cache_clear', Cache_Clear_Command::class)->args([service('cache_clearer'), service('filesystem')])->tag('console.command')->set('console.command.cache_pool_clear', Cache_Pool_Clear_Command::class)->args([service('cache.global_clearer')])->tag('console.command')->set('console.command.cache_pool_prune', Cache_Pool_Prune_Command::class)->args([[]])->tag('console.command')->set('console.command.cache_pool_invalidate_tags', Cache_Pool_Invalidate_Tags_Command::class)->args([tagged_locator('cache.taggable', 'pool')])->tag('console.command')->set('console.command.cache_pool_delete', Cache_Pool_Delete_Command::class)->args([service('cache.global_clearer')])->tag('console.command')->set('console.command.cache_pool_list', Cache_Pool_List_Command::class)->args([null])->tag('console.command')->set('console.command.cache_warmup', Cache_Warmup_Command::class)->args([service('cache_warmer')])->tag('console.command')->set('console.command.config_debug', Config_Debug_Command::class)->args([service('container.env_var_processors_locator')])->tag('console.command')->set('console.command.config_dump_reference', Config_Dump_Reference_Command::class)->tag('console.command')->set('console.command.container_debug', Container_Debug_Command::class)->tag('console.command')->set('console.command.container_lint', Container_Lint_Command::class)->tag('console.command')->set('console.command.debug_autowiring', Debug_Autowiring_Command::class)->args([null, service('debug.file_link_formatter')->null_on_invalid()])->tag('console.command')->set('console.command.dotenv_debug', Dotenv_Debug_Command::class)->args([param('kernel.environment'), param('kernel.project_dir')])->tag('console.command')->set('console.command.event_dispatcher_debug', Event_Dispatcher_Debug_Command::class)->args([tagged_locator('event_dispatcher.dispatcher', 'name')])->tag('console.command')->set('console.command.messenger_consume_messages', Consume_Messages_Command::class)->args([
        abstract_arg('Routable message bus'),
        service('messenger.receiver_locator'),
        service('event_dispatcher'),
        service('logger')->null_on_invalid(),
        [],
        // Receiver names
        service('messenger.listener.reset_services')->null_on_invalid(),
        [],
        // Bus names
        service('messenger.rate_limiter_locator')->null_on_invalid(),
        null,
    ])->tag('console.command')->tag('monolog.logger', ['channel' => 'messenger'])->set('console.command.messenger_setup_transports', Setup_Transports_Command::class)->args([service('messenger.receiver_locator'), []])->tag('console.command')->set('console.command.messenger_debug', Messenger_Debug_Command::class)->args([[]])->tag('console.command')->set('console.command.messenger_stop_workers', Stop_Workers_Command::class)->args([service('cache.messenger.restart_workers_signal')])->tag('console.command')->set('console.command.messenger_failed_messages_retry', Failed_Messages_Retry_Command::class)->args([abstract_arg('Default failure receiver name'), abstract_arg('Receivers'), service('messenger.routable_message_bus'), service('event_dispatcher'), service('logger')->null_on_invalid(), service('.messenger.transport.native_php_serializer')->null_on_invalid(), null])->tag('console.command')->tag('monolog.logger', ['channel' => 'messenger'])->set('console.command.messenger_failed_messages_show', Failed_Messages_Show_Command::class)->args([abstract_arg('Default failure receiver name'), abstract_arg('Receivers'), service('.messenger.transport.native_php_serializer')->null_on_invalid()])->tag('console.command')->set('console.command.messenger_failed_messages_remove', Failed_Messages_Remove_Command::class)->args([abstract_arg('Default failure receiver name'), abstract_arg('Receivers'), service('.messenger.transport.native_php_serializer')->null_on_invalid()])->tag('console.command')->set('console.command.messenger_stats', Stats_Command::class)->args([service('messenger.receiver_locator'), abstract_arg('Receivers names')])->tag('console.command')->set('console.command.scheduler_debug', Scheduler_Debug_Command::class)->args([tagged_locator('scheduler.schedule_provider', 'name')])->tag('console.command')->set('console.command.router_debug', Router_Debug_Command::class)->args([service('router'), service('debug.file_link_formatter')->null_on_invalid()])->tag('console.command')->set('console.command.router_match', Router_Match_Command::class)->args([service('router'), tagged_iterator('routing.expression_language_provider')])->tag('console.command')->set('console.command.serializer_debug', Serializer_Debug_Command::class)->args([service('serializer.mapping.class_metadata_factory')])->tag('console.command')->set('console.command.translation_debug', Translation_Debug_Command::class)->args([
        service('translator'),
        service('translation.reader'),
        service('translation.extractor'),
        param('translator.default_path'),
        null,
        // twig.default_path
        [],
        // Translator paths
        [],
        // Twig paths
        param('kernel.enabled_locales'),
    ])->tag('console.command')->set('console.command.translation_extract', Translation_Extract_Command::class)->args([
        service('translation.writer'),
        service('translation.reader'),
        service('translation.extractor'),
        param('kernel.default_locale'),
        param('translator.default_path'),
        null,
        // twig.default_path
        [],
        // Translator paths
        [],
        // Twig paths
        param('kernel.enabled_locales'),
    ])->tag('console.command')->set('console.command.validator_debug', Validator_Debug_Command::class)->args([service('validator')])->tag('console.command')->set('console.command.translation_pull', Translation_Pull_Command::class)->args([
        service('translation.provider_collection'),
        service('translation.writer'),
        service('translation.reader'),
        param('kernel.default_locale'),
        [],
        // Translator paths
        [],
    ])->tag('console.command', ['command' => 'translation:pull'])->set('console.command.translation_push', Translation_Push_Command::class)->args([
        service('translation.provider_collection'),
        service('translation.reader'),
        [],
        // Translator paths
        [],
    ])->tag('console.command', ['command' => 'translation:push'])->set('console.command.workflow_dump', Workflow_Dump_Command::class)->args([tagged_locator('workflow', 'name')])->tag('console.command')->set('console.command.xliff_lint', Xliff_Lint_Command::class)->tag('console.command')->set('console.command.yaml_lint', Yaml_Lint_Command::class)->tag('console.command')->set('console.command.translation_lint', Translation_Lint_Command::class)->args([service('translator'), param('kernel.enabled_locales')])->tag('console.command')->set('console.command.form_debug', Debug_Command::class)->args([
        service('form.registry'),
        [],
        // All form types namespaces are stored here by FormPass
        [],
        // All services form types are stored here by FormPass
        [],
        // All type extensions are stored here by FormPass
        [],
        // All type guessers are stored here by FormPass
        service('debug.file_link_formatter')->null_on_invalid(),
    ])->tag('console.command')->set('console.command.secrets_set', Secrets_Set_Command::class)->args([service('secrets.vault'), service('secrets.local_vault')->null_on_invalid()])->tag('console.command')->set('console.command.secrets_remove', Secrets_Remove_Command::class)->args([service('secrets.vault'), service('secrets.local_vault')->null_on_invalid()])->tag('console.command')->set('console.command.secrets_generate_key', Secrets_Generate_Keys_Command::class)->args([service('secrets.vault'), service('secrets.local_vault')->ignore_on_invalid()])->tag('console.command')->set('console.command.secrets_list', Secrets_List_Command::class)->args([service('secrets.vault'), service('secrets.local_vault')->ignore_on_invalid()])->tag('console.command')->set('console.command.secrets_reveal', Secrets_Reveal_Command::class)->args([service('secrets.vault'), service('secrets.local_vault')->ignore_on_invalid()])->tag('console.command')->set('console.command.secrets_decrypt_to_local', Secrets_Decrypt_To_Local_Command::class)->args([service('secrets.vault'), service('secrets.local_vault')->ignore_on_invalid()])->tag('console.command')->set('console.command.secrets_encrypt_from_local', Secrets_Encrypt_From_Local_Command::class)->args([service('secrets.vault'), service('secrets.local_vault')->ignore_on_invalid()])->tag('console.command')->set('console.command.error_dumper', Error_Dump_Command::class)->args([service('filesystem'), service('error_renderer.html'), service(Entrypoint_Lookup_Interface::class)->null_on_invalid()])->tag('console.command')->set('console.messenger.application', Application::class)->share(false)->call('setAutoExit', [false])->args([service('kernel')])->set('console.messenger.execute_command_handler', Run_Command_Message_Handler::class)->args([service('console.messenger.application')])->tag('messenger.message_handler', ['sign' => true])->set('console.argument_resolver', Argument_Resolver::class)->public()->args([abstract_arg('argument value resolvers'), abstract_arg('named argument value resolvers')])->set('console.argument_resolver.backed_enum', Backed_Enum_Value_Resolver::class)->tag('console.argument_value_resolver', ['priority' => 100, 'name' => Backed_Enum_Value_Resolver::class])->set('console.argument_resolver.uid', Uid_Value_Resolver::class)->tag('console.argument_value_resolver', ['priority' => 100, 'name' => Uid_Value_Resolver::class])->set('console.argument_resolver.input_file', Input_File_Value_Resolver::class)->tag('console.argument_value_resolver', ['priority' => 100, 'name' => Input_File_Value_Resolver::class])->set('console.argument_resolver.builtin_type', Builtin_Type_Value_Resolver::class)->tag('console.argument_value_resolver', ['priority' => 100, 'name' => Builtin_Type_Value_Resolver::class])->set('console.argument_resolver.datetime', Date_Time_Value_Resolver::class)->args([service('clock')->null_on_invalid()])->tag('console.argument_value_resolver', ['priority' => 100, 'name' => Date_Time_Value_Resolver::class])->set('console.argument_resolver.map_input', Map_Input_Value_Resolver::class)->args([service('console.argument_resolver.builtin_type'), service('console.argument_resolver.backed_enum'), service('console.argument_resolver.datetime')])->tag('console.argument_value_resolver', ['priority' => 100, 'name' => Map_Input_Value_Resolver::class])->set('console.argument_resolver.service', Service_Value_Resolver::class)->args([abstract_arg('service locator, set in RegisterCommandArgumentLocatorsPass')])->tag('console.argument_value_resolver', ['priority' => -50, 'name' => Service_Value_Resolver::class])->set('console.argument_resolver.default', Default_Value_Resolver::class)->tag('console.argument_value_resolver', ['priority' => -100, 'name' => Default_Value_Resolver::class])->set('console.argument_resolver.variadic', Variadic_Value_Resolver::class)->tag('console.argument_value_resolver', ['priority' => -150, 'name' => Variadic_Value_Resolver::class]);
};