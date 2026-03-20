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
namespace Symfony\Bundle\Framework_Bundle;

use Symfony\Bundle\Framework_Bundle\Console\Application;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Add_Debug_Log_Processor_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Assets_Context_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Console_Argument_Value_Resolver_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Container_Builder_Debug_Dump_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Error_Logger_Compiler_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Php_Config_Reference_Dump_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Profiler_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Remove_Unused_Session_Marshalling_Handler_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Test_Service_Container_Real_Ref_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Test_Service_Container_Weak_Ref_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Translation_Lint_Command_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Translation_Update_Command_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler\Unused_Tags_Pass;
use Symfony\Bundle\Framework_Bundle\Dependency_Injection\Virtual_Request_Stack_Pass;
use Symfony\Component\Cache\Adapter\Apcu_Adapter;
use Symfony\Component\Cache\Adapter\Array_Adapter;
use Symfony\Component\Cache\Adapter\Chain_Adapter;
use Symfony\Component\Cache\Adapter\Php_Array_Adapter;
use Symfony\Component\Cache\Adapter\Php_Files_Adapter;
use Symfony\Component\Cache\Dependency_Injection\Cache_Collector_Pass;
use Symfony\Component\Cache\Dependency_Injection\Cache_Pool_Clearer_Pass;
use Symfony\Component\Cache\Dependency_Injection\Cache_Pool_Pass;
use Symfony\Component\Cache\Dependency_Injection\Cache_Pool_Pruner_Pass;
use Symfony\Component\Config\Resource\Class_Existence_Resource;
use Symfony\Component\Console\Console_Events;
use Symfony\Component\Console\Dependency_Injection\Add_Console_Command_Pass;
use Symfony\Component\Console\Dependency_Injection\Register_Command_Argument_Locators_Pass;
use Symfony\Component\Console\Dependency_Injection\Remove_Empty_Command_Argument_Locators_Pass;
use Symfony\Component\Dependency_Injection\Compiler\Pass_Config;
use Symfony\Component\Dependency_Injection\Compiler\Register_Reverse_Container_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Error_Handler\Error_Handler;
use Symfony\Component\Event_Dispatcher\Dependency_Injection\Register_Listeners_Pass;
use Symfony\Component\Form\Dependency_Injection\Form_Pass;
use Symfony\Component\Http_Client\Dependency_Injection\Http_Client_Pass;
use Symfony\Component\Http_Foundation\Binary_File_Response;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Bundle\Bundle;
use Symfony\Component\Http_Kernel\Dependency_Injection\Controller_Argument_Value_Resolver_Pass;
use Symfony\Component\Http_Kernel\Dependency_Injection\Controller_Attributes_Listener_Pass;
use Symfony\Component\Http_Kernel\Dependency_Injection\Fragment_Renderer_Pass;
use Symfony\Component\Http_Kernel\Dependency_Injection\Logger_Pass;
use Symfony\Component\Http_Kernel\Dependency_Injection\Register_Controller_Argument_Locators_Pass;
use Symfony\Component\Http_Kernel\Dependency_Injection\Register_Locale_Aware_Services_Pass;
use Symfony\Component\Http_Kernel\Dependency_Injection\Remove_Empty_Controller_Argument_Locators_Pass;
use Symfony\Component\Http_Kernel\Dependency_Injection\Resettable_Service_Pass;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Json_Streamer\Dependency_Injection\Streamable_Pass;
use Symfony\Component\Messenger\Dependency_Injection\Messenger_Pass;
use Symfony\Component\Mime\Dependency_Injection\Add_Mime_Type_Guesser_Pass;
use Symfony\Component\Object_Mapper\Dependency_Injection\Reverse_Mapping_Pass;
use Symfony\Component\Property_Info\Dependency_Injection\Property_Info_Constructor_Pass;
use Symfony\Component\Property_Info\Dependency_Injection\Property_Info_Pass;
use Symfony\Component\Routing\Dependency_Injection\Add_Expression_Language_Providers_Pass;
use Symfony\Component\Routing\Dependency_Injection\Routing_Controller_Pass;
use Symfony\Component\Routing\Dependency_Injection\Routing_Resolver_Pass;
use Symfony\Component\Runtime\Symfony_Runtime;
use Symfony\Component\Scheduler\Dependency_Injection\Add_Schedule_Messenger_Pass;
use Symfony\Component\Serializer\Dependency_Injection\Attribute_Metadata_Pass as SerializerAttributeMetadataPass;
use Symfony\Component\Serializer\Dependency_Injection\Serializer_Pass;
use Symfony\Component\Translation\Dependency_Injection\Data_Collector_Translator_Pass;
use Symfony\Component\Translation\Dependency_Injection\Logging_Translator_Pass;
use Symfony\Component\Translation\Dependency_Injection\Translation_Dumper_Pass;
use Symfony\Component\Translation\Dependency_Injection\Translation_Extractor_Pass;
use Symfony\Component\Translation\Dependency_Injection\Translator_Pass;
use Symfony\Component\Translation\Dependency_Injection\Translator_Paths_Pass;
use Symfony\Component\Validator\Dependency_Injection\Add_Auto_Mapping_Configuration_Pass;
use Symfony\Component\Validator\Dependency_Injection\Add_Constraint_Validators_Pass;
use Symfony\Component\Validator\Dependency_Injection\Add_Validator_Initializers_Pass;
use Symfony\Component\Validator\Dependency_Injection\Attribute_Metadata_Pass;
use Symfony\Component\Var_Exporter\Internal\Hydrator;
use Symfony\Component\Var_Exporter\Internal\Registry;
use Symfony\Component\Workflow\Dependency_Injection\Workflow_Debug_Pass;
use Symfony\Component\Workflow\Dependency_Injection\Workflow_Guard_Listener_Pass;
use Symfony\Component\Workflow\Dependency_Injection\Workflow_Validator_Pass;
// Help opcache.preload discover always-needed symbols
class_exists(Apcu_Adapter::class);
class_exists(Array_Adapter::class);
class_exists(Chain_Adapter::class);
class_exists(Php_Array_Adapter::class);
class_exists(Php_Files_Adapter::class);
class_exists(Dotenv::class);
class_exists(Error_Handler::class);
class_exists(Hydrator::class);
class_exists(Registry::class);
/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Framework_Bundle extends Bundle
{
    public function boot(): void
    {
        $_ENV['DOCTRINE_DEPRECATIONS'] = $_SERVER['DOCTRINE_DEPRECATIONS'] ??= 'trigger';
        if (class_exists(Symfony_Runtime::class)) {
            $handler = get_error_handler();
        } else {
            $handler = [Error_Handler::register(null, false)];
        }
        if (\is_array($handler) && $handler[0] instanceof Error_Handler) {
            $this->container->get('debug.error_handler_configurator')->configure($handler[0]);
        }
        if ($this->container->get_parameter('kernel.http_method_override')) {
            Request::enable_http_method_parameter_override();
        }
        if ($this->container->has_parameter('kernel.allowed_http_method_override')) {
            Request::set_allowed_http_method_override($this->container->get_parameter('kernel.allowed_http_method_override'));
        }
        if ($this->container->has_parameter('kernel.trust_x_sendfile_type_header') && $this->container->get_parameter('kernel.trust_x_sendfile_type_header')) {
            Binary_File_Response::trust_x_sendfile_type_header();
        }
    }
    public function build(Container_Builder $container): void
    {
        parent::build($container);
        $register_listeners_pass = new Register_Listeners_Pass();
        $register_listeners_pass->set_hot_path_events([Kernel_Events::REQUEST, Kernel_Events::CONTROLLER, Kernel_Events::CONTROLLER_ARGUMENTS, Kernel_Events::RESPONSE, Kernel_Events::FINISH_REQUEST]);
        if (class_exists(Console_Events::class)) {
            $register_listeners_pass->set_no_preload_events([Console_Events::COMMAND, Console_Events::TERMINATE, Console_Events::ERROR]);
        }
        $container->add_compiler_pass(new Assets_Context_Pass());
        $container->add_compiler_pass(new Logger_Pass(), Pass_Config::TYPE_BEFORE_OPTIMIZATION, -32);
        $container->add_compiler_pass(new Register_Controller_Argument_Locators_Pass());
        $container->add_compiler_pass(new Remove_Empty_Controller_Argument_Locators_Pass(), Pass_Config::TYPE_BEFORE_REMOVING);
        $this->add_compiler_pass_if_exists($container, Register_Command_Argument_Locators_Pass::class);
        $this->add_compiler_pass_if_exists($container, Remove_Empty_Command_Argument_Locators_Pass::class, Pass_Config::TYPE_BEFORE_REMOVING);
        $this->add_compiler_pass_if_exists($container, Console_Argument_Value_Resolver_Pass::class);
        $container->add_compiler_pass(new Routing_Resolver_Pass());
        $this->add_compiler_pass_if_exists($container, Routing_Controller_Pass::class);
        $this->add_compiler_pass_if_exists($container, Data_Collector_Translator_Pass::class);
        $container->add_compiler_pass(new Profiler_Pass());
        // must be registered before removing private services as some might be listeners/subscribers
        // but as late as possible to get resolved parameters
        $container->add_compiler_pass($register_listeners_pass, Pass_Config::TYPE_BEFORE_REMOVING);
        $this->add_compiler_pass_if_exists($container, Controller_Attributes_Listener_Pass::class, Pass_Config::TYPE_BEFORE_REMOVING);
        $this->add_compiler_pass_if_exists($container, Add_Constraint_Validators_Pass::class);
        $this->add_compiler_pass_if_exists($container, Add_Validator_Initializers_Pass::class);
        $this->add_compiler_pass_if_exists($container, Attribute_Metadata_Pass::class);
        $this->add_compiler_pass_if_exists($container, Add_Console_Command_Pass::class, Pass_Config::TYPE_BEFORE_REMOVING);
        // must be registered before the AddConsoleCommandPass
        $container->add_compiler_pass(new Translation_Lint_Command_Pass(), Pass_Config::TYPE_BEFORE_REMOVING, 10);
        // must be registered as late as possible to get access to all Twig paths registered in
        // twig.template_iterator definition
        $this->add_compiler_pass_if_exists($container, Translator_Pass::class, Pass_Config::TYPE_BEFORE_OPTIMIZATION, -32);
        $this->add_compiler_pass_if_exists($container, Translator_Paths_Pass::class, Pass_Config::TYPE_AFTER_REMOVING);
        $this->add_compiler_pass_if_exists($container, Logging_Translator_Pass::class);
        $container->add_compiler_pass(new Add_Expression_Language_Providers_Pass());
        $this->add_compiler_pass_if_exists($container, Translation_Extractor_Pass::class);
        $this->add_compiler_pass_if_exists($container, Translation_Dumper_Pass::class);
        $container->add_compiler_pass(new Fragment_Renderer_Pass());
        $this->add_compiler_pass_if_exists($container, Serializer_Pass::class);
        $this->add_compiler_pass_if_exists($container, Serializer_Attribute_Metadata_Pass::class);
        $this->add_compiler_pass_if_exists($container, Property_Info_Pass::class);
        $this->add_compiler_pass_if_exists($container, Property_Info_Constructor_Pass::class);
        $container->add_compiler_pass(new Controller_Argument_Value_Resolver_Pass());
        $container->add_compiler_pass(new Cache_Pool_Pass(), Pass_Config::TYPE_BEFORE_OPTIMIZATION, 32);
        $container->add_compiler_pass(new Cache_Pool_Clearer_Pass(), Pass_Config::TYPE_AFTER_REMOVING);
        $container->add_compiler_pass(new Cache_Pool_Pruner_Pass(), Pass_Config::TYPE_AFTER_REMOVING);
        $this->add_compiler_pass_if_exists($container, Form_Pass::class);
        $this->add_compiler_pass_if_exists($container, Workflow_Guard_Listener_Pass::class);
        $this->add_compiler_pass_if_exists($container, Workflow_Validator_Pass::class);
        $container->add_compiler_pass(new Resettable_Service_Pass(), Pass_Config::TYPE_BEFORE_OPTIMIZATION, -32);
        $container->add_compiler_pass(new Register_Locale_Aware_Services_Pass());
        $container->add_compiler_pass(new Test_Service_Container_Weak_Ref_Pass(), Pass_Config::TYPE_BEFORE_REMOVING, -32);
        $container->add_compiler_pass(new Test_Service_Container_Real_Ref_Pass(), Pass_Config::TYPE_AFTER_REMOVING);
        $this->add_compiler_pass_if_exists($container, Add_Mime_Type_Guesser_Pass::class);
        $this->add_compiler_pass_if_exists($container, Add_Schedule_Messenger_Pass::class);
        $this->add_compiler_pass_if_exists($container, Messenger_Pass::class);
        $this->add_compiler_pass_if_exists($container, Http_Client_Pass::class);
        $this->add_compiler_pass_if_exists($container, Add_Auto_Mapping_Configuration_Pass::class);
        $container->add_compiler_pass(new Register_Reverse_Container_Pass(true));
        $container->add_compiler_pass(new Register_Reverse_Container_Pass(false), Pass_Config::TYPE_AFTER_REMOVING);
        $container->add_compiler_pass(new Remove_Unused_Session_Marshalling_Handler_Pass());
        // must be registered after MonologBundle's LoggerChannelPass
        $container->add_compiler_pass(new Error_Logger_Compiler_Pass(), Pass_Config::TYPE_BEFORE_OPTIMIZATION, -32);
        $container->add_compiler_pass(new Virtual_Request_Stack_Pass());
        $container->add_compiler_pass(new Translation_Update_Command_Pass(), Pass_Config::TYPE_BEFORE_REMOVING);
        $this->add_compiler_pass_if_exists($container, Streamable_Pass::class);
        $this->add_compiler_pass_if_exists($container, Reverse_Mapping_Pass::class);
        if ($container->get_parameter('kernel.debug')) {
            if ($container->has_parameter('.kernel.config_dir') && $container->has_parameter('.kernel.bundles_definition')) {
                $container->add_compiler_pass(new Php_Config_Reference_Dump_Pass($container->get_parameter('.kernel.config_dir') . '/reference.php', $container->get_parameter('.kernel.bundles_definition')));
            }
            $container->add_compiler_pass(new Add_Debug_Log_Processor_Pass(), Pass_Config::TYPE_BEFORE_OPTIMIZATION, 2);
            $container->add_compiler_pass(new Unused_Tags_Pass(), Pass_Config::TYPE_AFTER_REMOVING);
            $container->add_compiler_pass(new Container_Builder_Debug_Dump_Pass(), Pass_Config::TYPE_BEFORE_REMOVING, -255);
            $container->add_compiler_pass(new Cache_Collector_Pass(), Pass_Config::TYPE_BEFORE_REMOVING);
            $this->add_compiler_pass_if_exists($container, Workflow_Debug_Pass::class);
        }
    }
    /**
     * @internal
     */
    public static function consider_profiler_enabled(): bool
    {
        return !($GLOBALS['app'] ?? null) instanceof Application || empty($_GET) && \in_array('--profile', $_SERVER['argv'] ?? [], true);
    }
    private function add_compiler_pass_if_exists(Container_Builder $container, string $class, string $type = Pass_Config::TYPE_BEFORE_OPTIMIZATION, int $priority = 0): void
    {
        $container->add_resource(new Class_Existence_Resource($class));
        if (class_exists($class)) {
            $container->add_compiler_pass(new $class(), $type, $priority);
        }
    }
}