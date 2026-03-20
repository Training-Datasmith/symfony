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
namespace Symfony\Bundle\Framework_Bundle\Dependency_Injection;

use Composer\Installed_Versions;
use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Mapped_Superclass;
use Http\Client\Http_Async_Client;
use Http\Client\Http_Client;
use Php_Documentor\Reflection\Doc_Block_Factory_Interface;
use Php_Documentor\Reflection\Types\Context_Factory;
use Php_Parser\Parser;
use Php_Stan\Php_Doc_Parser\Parser\Php_Doc_Parser;
use Php_Unit\Framework\Test_Case;
use Psr\Cache\Cache_Item_Pool_Interface;
use Psr\Clock\Clock_Interface as PsrClockInterface;
use Psr\Container\Container_Interface as PsrContainerInterface;
use Psr\Http\Client\Client_Interface;
use Psr\Log\Logger_Aware_Interface;
use Symfony\Bridge\Monolog\Processor\Debug_Processor;
use Symfony\Bridge\Twig\Extension\Csrf_Extension;
use Symfony\Bundle\Framework_Bundle\Controller\Abstract_Controller;
use Symfony\Bundle\Framework_Bundle\Routing\Route_Loader_Interface;
use Symfony\Bundle\Full_Stack;
use Symfony\Bundle\Mercure_Bundle\Mercure_Bundle;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Package_Interface;
use Symfony\Component\Asset_Mapper\Asset_Mapper;
use Symfony\Component\Asset_Mapper\Compiler\Asset_Compiler_Interface;
use Symfony\Component\Browser_Kit\Abstract_Browser;
use Symfony\Component\Cache\Adapter\Adapter_Interface;
use Symfony\Component\Cache\Adapter\Array_Adapter;
use Symfony\Component\Cache\Adapter\Chain_Adapter;
use Symfony\Component\Cache\Adapter\Tag_Aware_Adapter;
use Symfony\Component\Cache\Dependency_Injection\Cache_Pool_Pass;
use Symfony\Component\Clock\Clock_Interface;
use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Config\File_Locator;
use Symfony\Component\Config\Loader\Loader_Interface;
use Symfony\Component\Config\Resource\Directory_Resource;
use Symfony\Component\Config\Resource\File_Resource;
use Symfony\Component\Config\Resource_Checker_Interface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver\Value_Resolver_Interface as ConsoleValueResolverInterface;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Attribute\As_Targeted_Value_Resolver as AsTargetedConsoleValueResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event_Listener\Validate_Question_Input_Listener;
use Symfony\Component\Console\Messenger\Run_Command_Message_Handler;
use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Service_Locator_Tag_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Env_Var_Loader_Interface;
use Symfony\Component\Dependency_Injection\Env_Var_Processor_Interface;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Extension\Extension;
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader;
use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Service_Locator;
use Symfony\Component\Dotenv\Command\Debug_Command;
use Symfony\Component\Event_Dispatcher\Attribute\As_Event_Listener;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Expression_Language\Expression_Language;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;
use Symfony\Component\Form\Extension\Validator\Validator_Extension;
use Symfony\Component\Form\Extension\Validator\Violation_Mapper\Violation_Mapper_Interface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\Form_Type_Extension_Interface;
use Symfony\Component\Form\Form_Type_Guesser_Interface;
use Symfony\Component\Form\Form_Type_Interface;
use Symfony\Component\Html_Sanitizer\Html_Sanitizer;
use Symfony\Component\Html_Sanitizer\Html_Sanitizer_Action;
use Symfony\Component\Html_Sanitizer\Html_Sanitizer_Config;
use Symfony\Component\Html_Sanitizer\Html_Sanitizer_Interface;
use Symfony\Component\Http_Client\Caching_Http_Client;
use Symfony\Component\Http_Client\Exception\Chunk_Cache_Item_Not_Found_Exception;
use Symfony\Component\Http_Client\Mock_Http_Client;
use Symfony\Component\Http_Client\Retry\Generic_Retry_Strategy;
use Symfony\Component\Http_Client\Retryable_Http_Client;
use Symfony\Component\Http_Client\Scoping_Http_Client;
use Symfony\Component\Http_Client\Throttling_Http_Client;
use Symfony\Component\Http_Client\Uri_Template_Http_Client;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Attribute\As_Controller;
use Symfony\Component\Http_Kernel\Attribute\As_Targeted_Value_Resolver;
use Symfony\Component\Http_Kernel\Cache_Clearer\Cache_Clearer_Interface;
use Symfony\Component\Http_Kernel\Cache_Warmer\Cache_Warmer_Interface;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Data_Collector\Data_Collector_Interface;
use Symfony\Component\Http_Kernel\Event_Listener\Controller_Attributes_Listener;
use Symfony\Component\Http_Kernel\Log\Debug_Logger_Configurator;
use Symfony\Component\Json_Streamer\Attribute\Json_Streamable;
use Symfony\Component\Json_Streamer\Json_Stream_Writer;
use Symfony\Component\Json_Streamer\Mapping\Property_Metadata;
use Symfony\Component\Json_Streamer\Value_Transformer\Value_Transformer_Interface;
use Symfony\Component\Lock\Lock_Factory;
use Symfony\Component\Lock\Lock_Interface;
use Symfony\Component\Lock\Persisting_Store_Interface;
use Symfony\Component\Lock\Serializer\Lock_Key_Normalizer;
use Symfony\Component\Lock\Store\Store_Factory;
use Symfony\Component\Mailer\Bridge as MailerBridge;
use Symfony\Component\Mailer\Command\Mailer_Test_Command;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mercure\Hub_Registry;
use Symfony\Component\Messenger\Attribute\As_Message;
use Symfony\Component\Messenger\Attribute\As_Message_Handler;
use Symfony\Component\Messenger\Bridge as MessengerBridge;
use Symfony\Component\Messenger\Handler\Batch_Handler_Interface;
use Symfony\Component\Messenger\Message_Bus;
use Symfony\Component\Messenger\Message_Bus_Interface;
use Symfony\Component\Messenger\Middleware\Decode_Failed_Message_Middleware;
use Symfony\Component\Messenger\Middleware\Router_Context_Middleware;
use Symfony\Component\Messenger\Transport\Serialization\Serializer_Interface;
use Symfony\Component\Messenger\Transport\Transport_Factory_Interface as MessengerTransportFactoryInterface;
use Symfony\Component\Messenger\Transport\Transport_Interface;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Mime_Type_Guesser_Interface;
use Symfony\Component\Mime\Mime_Types;
use Symfony\Component\Notifier\Bridge as NotifierBridge;
use Symfony\Component\Notifier\Bridge\Fake_Chat\Fake_Chat_Transport_Factory;
use Symfony\Component\Notifier\Bridge\Fake_Sms\Fake_Sms_Transport_Factory;
use Symfony\Component\Notifier\Chatter_Interface;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Texter_Interface;
use Symfony\Component\Notifier\Transport\Transport_Factory_Interface as NotifierTransportFactoryInterface;
use Symfony\Component\Object_Mapper\Attribute\Map;
use Symfony\Component\Object_Mapper\Condition_Callable_Interface;
use Symfony\Component\Object_Mapper\Metadata\Reverse_Class_Object_Mapper_Metadata_Factory;
use Symfony\Component\Object_Mapper\Object_Mapper_Interface;
use Symfony\Component\Object_Mapper\Transform_Callable_Interface;
use Symfony\Component\Process\Messenger\Run_Process_Message_Handler;
use Symfony\Component\Property_Access\Property_Accessor;
use Symfony\Component\Property_Info\Extractor\Constructor_Argument_Type_Extractor_Interface;
use Symfony\Component\Property_Info\Extractor\Php_Doc_Extractor;
use Symfony\Component\Property_Info\Extractor\Php_Stan_Extractor;
use Symfony\Component\Property_Info\Property_Access_Extractor_Interface;
use Symfony\Component\Property_Info\Property_Description_Extractor_Interface;
use Symfony\Component\Property_Info\Property_Info_Extractor_Interface;
use Symfony\Component\Property_Info\Property_Initializable_Extractor_Interface;
use Symfony\Component\Property_Info\Property_List_Extractor_Interface;
use Symfony\Component\Property_Info\Property_Type_Extractor_Interface;
use Symfony\Component\Rate_Limiter\Compound_Rate_Limiter_Factory;
use Symfony\Component\Rate_Limiter\Limiter_Interface;
use Symfony\Component\Rate_Limiter\Rate_Limiter_Factory_Interface;
use Symfony\Component\Rate_Limiter\Storage\Cache_Storage;
use Symfony\Component\Remote_Event\Attribute\As_Remote_Event_Consumer;
use Symfony\Component\Remote_Event\Remote_Event;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Scheduler\Attribute\As_Cron_Task;
use Symfony\Component\Scheduler\Attribute\As_Periodic_Task;
use Symfony\Component\Scheduler\Attribute\As_Schedule;
use Symfony\Component\Scheduler\Messenger\Scheduler_Transport_Factory;
use Symfony\Component\Security\Core\Authentication_Events;
use Symfony\Component\Security\Core\Exception\Authentication_Exception;
use Symfony\Component\Security\Csrf\Csrf_Token;
use Symfony\Component\Security\Csrf\Csrf_Token_Manager_Interface;
use Symfony\Component\Semaphore\Persisting_Store_Interface as SemaphoreStoreInterface;
use Symfony\Component\Semaphore\Semaphore;
use Symfony\Component\Semaphore\Semaphore_Factory;
use Symfony\Component\Semaphore\Serializer\Semaphore_Key_Normalizer;
use Symfony\Component\Semaphore\Store\Lock_Store;
use Symfony\Component\Semaphore\Store\Store_Factory as SemaphoreStoreFactory;
use Symfony\Component\Serializer\Attribute as SerializerMapping;
use Symfony\Component\Serializer\Attribute\Extends_Serialization_For;
use Symfony\Component\Serializer\Encoder\Decoder_Interface;
use Symfony\Component\Serializer\Encoder\Encoder_Interface;
use Symfony\Component\Serializer\Mapping\Loader\Xml_File_Loader;
use Symfony\Component\Serializer\Mapping\Loader\Yaml_File_Loader;
use Symfony\Component\Serializer\Normalizer\Denormalizer_Interface;
use Symfony\Component\Serializer\Normalizer\Normalizer_Interface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\String\Lazy_String;
use Symfony\Component\String\Slugger\Slugger_Interface;
use Symfony\Component\Translation\Bridge as TranslationBridge;
use Symfony\Component\Translation\Command\Translation_Lint_Command as BaseTranslationLintCommand;
use Symfony\Component\Translation\Command\Xliff_Lint_Command as BaseXliffLintCommand;
use Symfony\Component\Translation\Locale_Switcher;
use Symfony\Component\Translation\Pseudo_Localization_Translator;
use Symfony\Component\Translation\Translatable_Message;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Type_Info\Type;
use Symfony\Component\Type_Info\Type_Resolver\Php_Doc_Aware_Reflection_Type_Resolver;
use Symfony\Component\Type_Info\Type_Resolver\String_Type_Resolver;
use Symfony\Component\Type_Info\Type_Resolver\Type_Resolver_Interface;
use Symfony\Component\Uid\Factory\Uuid_Factory;
use Symfony\Component\Uid\Uuid_V4;
use Symfony\Component\Validator\Attribute\Extends_Validation_For;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Expression_Language_Provider;
use Symfony\Component\Validator\Constraint_Validator_Interface;
use Symfony\Component\Validator\Group_Provider_Interface;
use Symfony\Component\Validator\Mapping\Loader\Property_Info_Loader;
use Symfony\Component\Validator\Object_Initializer_Interface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Webhook\Controller\Webhook_Controller;
use Symfony\Component\Web_Link\Http_Header_Serializer;
use Symfony\Component\Workflow;
use Symfony\Component\Workflow\Arc;
use Symfony\Component\Workflow\Workflow_Interface;
use Symfony\Component\Yaml\Command\Lint_Command as BaseYamlLintCommand;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\Cache_Interface;
use Symfony\Contracts\Cache\Callback_Interface;
use Symfony\Contracts\Cache\Namespaced_Pool_Interface;
use Symfony\Contracts\Cache\Tag_Aware_Cache_Interface;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Service\Reset_Interface;
use Symfony\Contracts\Service\Service_Subscriber_Interface;
use Symfony\Contracts\Translation\Locale_Aware_Interface;
/**
 * Process the configuration and prepare the dependency injection container with
 * parameters and services.
 */
class Framework_Extension extends Extension
{
    private array $configs_enabled = [];
    /**
     * Responds to the app.config configuration parameter.
     *
     * @throws LogicException
     */
    public function load(array $configs, Container_Builder $container): void
    {
        $loader = new Php_File_Loader($container, new File_Locator(\dirname(__DIR__) . '/Resources/config'));
        if (class_exists(Installed_Versions::class) && Installed_Versions::is_installed('symfony/symfony') && 'symfony/symfony' !== (Installed_Versions::get_root_package()['name'] ?? '')) {
            throw new \LogicException('Requiring the "symfony/symfony" package is unsupported; replace it with standalone components instead.');
        }
        if (!Container_Builder::will_be_available('symfony/validator', Validation::class, ['symfony/framework-bundle', 'symfony/form'])) {
            $container->set_parameter('validator.translation_domain', 'validators');
        }
        $loader->load('web.php');
        $loader->load('services.php');
        $loader->load('fragment_renderer.php');
        $loader->load('error_renderer.php');
        if (!class_exists(Controller_Attributes_Listener::class)) {
            $container->remove_definition('kernel.controller_attributes_listener');
            $container->remove_definition('serialize_controller_result_listener');
        }
        if (!Container_Builder::will_be_available('symfony/clock', Clock_Interface::class, ['symfony/framework-bundle'])) {
            $container->remove_definition('clock');
            $container->remove_alias(Clock_Interface::class);
            $container->remove_alias(Psr_Clock_Interface::class);
        }
        if (!Container_Builder::will_be_available('symfony/expression-language', Expression_Language::class, ['symfony/framework-bundle'])) {
            $container->remove_definition('controller.expression_language');
        }
        $container->register_alias_for_argument('parameter_bag', Psr_Container_Interface::class);
        $loader->load('process.php');
        if (!class_exists(Run_Process_Message_Handler::class)) {
            $container->remove_definition('process.messenger.process_message_handler');
        }
        if ($this->has_console()) {
            $loader->load('console.php');
            if (!class_exists(Base_Xliff_Lint_Command::class)) {
                $container->remove_definition('console.command.xliff_lint');
            }
            if (!class_exists(Base_Yaml_Lint_Command::class)) {
                $container->remove_definition('console.command.yaml_lint');
            }
            if (!class_exists(Base_Translation_Lint_Command::class)) {
                $container->remove_definition('console.command.translation_lint');
            }
            if (!class_exists(Debug_Command::class)) {
                $container->remove_definition('console.command.dotenv_debug');
            }
            if (!class_exists(Run_Command_Message_Handler::class)) {
                $container->remove_definition('console.messenger.application');
                $container->remove_definition('console.messenger.execute_command_handler');
            }
        }
        // Load Cache configuration first as it is used by other components
        $loader->load('cache.php');
        $configuration = $this->get_configuration($configs, $container);
        $config = $this->process_configuration($configuration, $configs);
        // warmup config enabled
        $this->read_config_enabled('translator', $container, $config['translator']);
        $this->read_config_enabled('property_access', $container, $config['property_access']);
        $this->read_config_enabled('profiler', $container, $config['profiler']);
        $this->read_config_enabled('workflows', $container, $config['workflows']);
        // A translator must always be registered (as support is included by
        // default in the Form and Validator component). If disabled, an identity
        // translator will be used and everything will still work as expected.
        if ($this->read_config_enabled('translator', $container, $config['translator']) || $this->read_config_enabled('form', $container, $config['form']) || $this->read_config_enabled('validation', $container, $config['validation'])) {
            if (!class_exists(Translator::class) && $this->read_config_enabled('translator', $container, $config['translator'])) {
                throw new LogicException('Translation support cannot be enabled as the Translation component is not installed. Try running "composer require symfony/translation".');
            }
            if (class_exists(Translator::class)) {
                $loader->load('identity_translator.php');
            }
        }
        $container->get_definition('locale_listener')->replace_argument(3, $config['set_locale_from_accept_language']);
        $container->get_definition('response_listener')->replace_argument(1, $config['set_content_language_from_locale']);
        $container->get_definition('http_kernel')->replace_argument(4, $config['handle_all_throwables'] ?? false);
        // If the slugger is used but the String component is not available, we should throw an error
        if (!Container_Builder::will_be_available('symfony/string', Slugger_Interface::class, ['symfony/framework-bundle'])) {
            $container->register('slugger', Slugger_Interface::class)->add_error('You cannot use the "slugger" service since the String component is not installed. Try running "composer require symfony/string".');
        } else {
            if (!Container_Builder::will_be_available('symfony/translation', Locale_Aware_Interface::class, ['symfony/framework-bundle'])) {
                $container->register('slugger', Slugger_Interface::class)->add_error('You cannot use the "slugger" service since the Translation contracts are not installed. Try running "composer require symfony/translation".');
            }
            if (!\extension_loaded('intl') && !\defined('PHPUNIT_COMPOSER_INSTALL')) {
                trigger_deprecation('', '', 'Please install the "intl" PHP extension for best performance.');
            }
        }
        $empty_secret_hint = '"framework.secret" option';
        if (isset($config['secret'])) {
            $container->set_parameter('kernel.secret', $config['secret']);
            $used_envs = [];
            $container->resolve_env_placeholders($config['secret'], null, $used_envs);
            if ($used_envs) {
                $empty_secret_hint = \sprintf('"%s" env var%s', implode('", "', $used_envs), 1 === \count($used_envs) ? '' : 's');
            }
        }
        $container->parameter_cannot_be_empty('kernel.secret', 'A non-empty value for the parameter "kernel.secret" is required. Did you forget to configure the ' . $empty_secret_hint . '?');
        $container->set_parameter('kernel.http_method_override', $config['http_method_override']);
        $container->set_parameter('kernel.allowed_http_method_override', $config['allowed_http_method_override']);
        $container->set_parameter('kernel.trust_x_sendfile_type_header', $config['trust_x_sendfile_type_header']);
        $container->set_parameter('kernel.trusted_hosts', [0] === array_keys($config['trusted_hosts']) ? $config['trusted_hosts'][0] : $config['trusted_hosts']);
        $container->set_parameter('kernel.default_locale', $config['default_locale']);
        $container->set_parameter('kernel.enabled_locales', $config['enabled_locales']);
        $container->set_parameter('kernel.error_controller', $config['error_controller']);
        if (($config['trusted_proxies'] ?? false) && ($config['trusted_headers'] ?? false)) {
            $container->set_parameter('kernel.trusted_proxies', \is_array($config['trusted_proxies']) && [0] === array_keys($config['trusted_proxies']) ? $config['trusted_proxies'][0] : $config['trusted_proxies']);
            $container->set_parameter('kernel.trusted_headers', [0] === array_keys($config['trusted_headers']) ? $config['trusted_headers'][0] : $config['trusted_headers']);
        }
        if (!$container->has_parameter('debug.file_link_format')) {
            $container->set_parameter('debug.file_link_format', $config['ide']);
        }
        if (!empty($config['test'])) {
            $loader->load('test.php');
            if (!class_exists(Abstract_Browser::class)) {
                $container->remove_definition('test.client');
            }
        }
        if ($this->read_config_enabled('request', $container, $config['request'])) {
            $this->register_request_configuration($config['request'], $container, $loader);
        }
        if ($this->read_config_enabled('assets', $container, $config['assets'])) {
            if (!class_exists(Package::class)) {
                throw new LogicException('Asset support cannot be enabled as the Asset component is not installed. Try running "composer require symfony/asset".');
            }
            $this->register_assets_configuration($config['assets'], $container, $loader);
        }
        if ($this->read_config_enabled('asset_mapper', $container, $config['asset_mapper'])) {
            if (!class_exists(Asset_Mapper::class)) {
                throw new LogicException('AssetMapper support cannot be enabled as the AssetMapper component is not installed. Try running "composer require symfony/asset-mapper".');
            }
            $this->register_asset_mapper_configuration($config['asset_mapper'], $container, $loader, $this->read_config_enabled('assets', $container, $config['assets']), $this->read_config_enabled('http_client', $container, $config['http_client']));
        } else {
            $container->remove_definition('cache.asset_mapper');
        }
        if ($this->read_config_enabled('http_client', $container, $config['http_client'])) {
            $this->read_config_enabled('rate_limiter', $container, $config['rate_limiter']);
            // makes sure that isInitializedConfigEnabled() will work
            $this->register_http_client_configuration($config['http_client'], $container, $loader);
        }
        if ($this->read_config_enabled('mailer', $container, $config['mailer'])) {
            $this->register_mailer_configuration($config['mailer'], $container, $loader, $this->read_config_enabled('webhook', $container, $config['webhook']));
            if (!$this->has_console() || !class_exists(Mailer_Test_Command::class)) {
                $container->remove_definition('console.command.mailer_test');
            }
        }
        $property_info_enabled = $this->read_config_enabled('property_info', $container, $config['property_info']);
        $this->register_http_cache_configuration($config['http_cache'], $container, $config['http_method_override'], $config['allowed_http_method_override']);
        $this->register_esi_configuration($config['esi'], $container, $loader);
        $this->register_ssi_configuration($config['ssi'], $container, $loader);
        $this->register_fragments_configuration($config['fragments'], $container, $loader);
        $this->register_translator_configuration($config['translator'], $container, $loader, $config['default_locale'], $config['enabled_locales']);
        $this->register_workflow_configuration($config['workflows'], $container, $loader);
        $this->register_debug_configuration($config['php_errors'], $container, $loader);
        $this->register_router_configuration($config['router'], $container, $loader, $config['enabled_locales']);
        $this->register_property_access_configuration($config['property_access'], $container, $loader);
        $this->register_secrets_configuration($config['secrets'], $container, $loader, $config['secret'] ?? null);
        $exception_listener = $container->get_definition('exception_listener');
        $loggers = [];
        foreach ($config['exceptions'] as $exception) {
            if (!isset($exception['log_channel'])) {
                continue;
            }
            $loggers[$exception['log_channel']] = new Reference('monolog.logger.' . $exception['log_channel'], Container_Interface::NULL_ON_INVALID_REFERENCE);
        }
        $exception_listener->replace_argument(3, $config['exceptions'])->set_argument(4, $loggers);
        if ($this->read_config_enabled('serializer', $container, $config['serializer'])) {
            if (!class_exists(Serializer::class)) {
                throw new LogicException('Serializer support cannot be enabled as the Serializer component is not installed. Try running "composer require symfony/serializer-pack".');
            }
            $this->register_serializer_configuration($config['serializer'], $container, $loader);
        } else {
            $container->get_definition('argument_resolver.request_payload')->set_arguments([])->add_error('You can neither use "#[MapRequestPayload]" nor "#[MapQueryString]" since the Serializer component is not ' . (class_exists(Serializer::class) ? 'enabled. Try setting "framework.serializer.enabled" to true.' : 'installed. Try running "composer require symfony/serializer-pack".'))->add_tag('container.error')->clear_tag('kernel.event_subscriber');
            $container->remove_definition('console.command.serializer_debug');
        }
        if ($type_info_enabled = $this->read_config_enabled('type_info', $container, $config['type_info'])) {
            $this->register_type_info_configuration($config['type_info'], $container, $loader);
        }
        if ($property_info_enabled) {
            $this->register_property_info_configuration($config['property_info'], $container, $loader);
        }
        if ($this->read_config_enabled('json_streamer', $container, $config['json_streamer'])) {
            if (!$type_info_enabled) {
                throw new LogicException('JsonStreamer support cannot be enabled as the TypeInfo component is not ' . (interface_exists(Type_Resolver_Interface::class) ? 'enabled.' : 'installed. Try running "composer require symfony/type-info".'));
            }
            $this->register_json_streamer_configuration($config['json_streamer'], $container, $loader);
        }
        if ($this->read_config_enabled('lock', $container, $config['lock'])) {
            $this->register_lock_configuration($config['lock'], $container, $loader);
        }
        if ($this->read_config_enabled('semaphore', $container, $config['semaphore'])) {
            $this->register_semaphore_configuration($config['semaphore'], $container, $loader);
        }
        if ($this->read_config_enabled('rate_limiter', $container, $config['rate_limiter'])) {
            if (!interface_exists(Limiter_Interface::class)) {
                throw new LogicException('Rate limiter support cannot be enabled as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
            }
            $this->register_rate_limiter_configuration($config['rate_limiter'], $container, $loader);
        }
        if ($this->read_config_enabled('web_link', $container, $config['web_link'])) {
            if (!class_exists(Http_Header_Serializer::class)) {
                throw new LogicException('WebLink support cannot be enabled as the WebLink component is not installed. Try running "composer require symfony/weblink".');
            }
            $loader->load('web_link.php');
        }
        if ($this->read_config_enabled('uid', $container, $config['uid'])) {
            if (!class_exists(Uuid_Factory::class)) {
                throw new LogicException('Uid support cannot be enabled as the Uid component is not installed. Try running "composer require symfony/uid".');
            }
            $this->register_uid_configuration($config['uid'], $container, $loader);
        } else {
            $container->remove_definition('argument_resolver.uid');
        }
        // register cache before session so both can share the connection services
        $this->register_cache_configuration($config['cache'], $container);
        if ($this->read_config_enabled('session', $container, $config['session'])) {
            if (!\extension_loaded('session')) {
                throw new LogicException('Session support cannot be enabled as the session extension is not installed. See https://php.net/session.installation for instructions.');
            }
            $this->register_session_configuration($config['session'], $container, $loader);
            if (!empty($config['test'])) {
                // test listener will replace the existing session listener
                // as we are aliasing to avoid duplicated registered events
                $container->set_alias('session_listener', 'test.session.listener');
            }
        } elseif (!empty($config['test'])) {
            $container->remove_definition('test.session.listener');
        }
        // csrf depends on session or stateless token ids being registered
        if (null === $config['csrf_protection']['enabled']) {
            $this->write_config_enabled('csrf_protection', ($config['csrf_protection']['stateless_token_ids'] || $this->read_config_enabled('session', $container, $config['session'])) && !class_exists(Full_Stack::class) && Container_Builder::will_be_available('symfony/security-csrf', Csrf_Token_Manager_Interface::class, ['symfony/framework-bundle']), $config['csrf_protection']);
        }
        $this->register_security_csrf_configuration($config['csrf_protection'], $container, $loader);
        // form depends on csrf being registered
        if ($this->read_config_enabled('form', $container, $config['form'])) {
            if (!class_exists(Form::class)) {
                throw new LogicException('Form support cannot be enabled as the Form component is not installed. Try running "composer require symfony/form".');
            }
            $this->register_form_configuration($config, $container, $loader);
            if (Container_Builder::will_be_available('symfony/validator', Validation::class, ['symfony/framework-bundle', 'symfony/form'])) {
                $this->write_config_enabled('validation', true, $config['validation']);
            } else {
                $container->remove_definition('form.type_extension.form.validator');
                $container->remove_definition('form.type_guesser.validator');
            }
            if (!$this->read_config_enabled('html_sanitizer', $container, $config['html_sanitizer'])) {
                $container->remove_definition('form.type_extension.form.html_sanitizer');
            }
        } else {
            $container->remove_definition('console.command.form_debug');
        }
        // validation depends on form, annotations being registered
        $this->register_validation_configuration($config['validation'], $container, $loader, $property_info_enabled);
        $messenger_enabled = $this->read_config_enabled('messenger', $container, $config['messenger']);
        if ($this->read_config_enabled('scheduler', $container, $config['scheduler'])) {
            if (!$messenger_enabled) {
                throw new LogicException('Scheduler support cannot be enabled as the Messenger component is not ' . (interface_exists(Message_Bus_Interface::class) ? 'enabled.' : 'installed. Try running "composer require symfony/messenger".'));
            }
            $this->register_scheduler_configuration($container, $loader);
        } else {
            $container->remove_definition('cache.scheduler');
            $container->remove_definition('console.command.scheduler_debug');
        }
        // messenger depends on validation, and lock being registered
        if ($messenger_enabled) {
            $this->register_messenger_configuration($config['messenger'], $container, $loader, $this->read_config_enabled('validation', $container, $config['validation']), $this->read_config_enabled('lock', $container, $config['lock']) && ($config['lock']['resources']['default'] ?? false));
        } else {
            $container->remove_definition('console.command.messenger_consume_messages');
            $container->remove_definition('console.command.messenger_stats');
            $container->remove_definition('console.command.messenger_debug');
            $container->remove_definition('console.command.messenger_stop_workers');
            $container->remove_definition('console.command.messenger_setup_transports');
            $container->remove_definition('console.command.messenger_failed_messages_retry');
            $container->remove_definition('console.command.messenger_failed_messages_show');
            $container->remove_definition('console.command.messenger_failed_messages_remove');
            $container->remove_definition('cache.messenger.restart_workers_signal');
        }
        // notifier depends on messenger, mailer being registered
        if ($this->read_config_enabled('notifier', $container, $config['notifier'])) {
            $this->register_notifier_configuration($config['notifier'], $container, $loader, $this->read_config_enabled('webhook', $container, $config['webhook']));
        }
        // profiler depends on form, validation, translation, messenger, mailer, http-client, notifier, serializer being registered. console is optional
        $this->register_profiler_configuration($config['profiler'], $container, $loader);
        if ($this->read_config_enabled('webhook', $container, $config['webhook'])) {
            $this->register_webhook_configuration($config['webhook'], $container, $loader, $this->read_config_enabled('serializer', $container, $config['serializer']));
            // If Webhook is installed but the HttpClient component is not available, we should throw an error
            if (!$this->read_config_enabled('http_client', $container, $config['http_client'])) {
                $container->get_definition('webhook.transport')->set_arguments([])->add_error('You cannot use the "webhook transport" service since the HttpClient component is not ' . (class_exists(Scoping_Http_Client::class) ? 'enabled. Try setting "framework.http_client.enabled" to true.' : 'installed. Try running "composer require symfony/http-client".'))->add_tag('container.error');
            }
        }
        if ($this->read_config_enabled('remote-event', $container, $config['remote-event'])) {
            $this->register_remote_event_configuration($loader);
        }
        if ($this->read_config_enabled('html_sanitizer', $container, $config['html_sanitizer'])) {
            if (!class_exists(Html_Sanitizer_Config::class)) {
                throw new LogicException('HtmlSanitizer support cannot be enabled as the HtmlSanitizer component is not installed. Try running "composer require symfony/html-sanitizer".');
            }
            $this->register_html_sanitizer_configuration($config['html_sanitizer'], $container, $loader);
        }
        if (Container_Builder::will_be_available('symfony/mime', Mime_Types::class, ['symfony/framework-bundle'])) {
            $loader->load('mime_type.php');
        }
        if (Container_Builder::will_be_available('symfony/object-mapper', Object_Mapper_Interface::class, ['symfony/framework-bundle'])) {
            $loader->load('object_mapper.php');
            $container->register_for_autoconfiguration(Transform_Callable_Interface::class)->add_tag('object_mapper.transform_callable');
            $container->register_for_autoconfiguration(Condition_Callable_Interface::class)->add_tag('object_mapper.condition_callable');
            $container->register_attribute_for_autoconfiguration(Map::class, static function (Child_Definition $definition, Map $attribute, \ReflectionClass $reflector): void {
                $definition->add_resource_tag('object_mapper.map', ['source' => $attribute->source ?? $reflector->name, 'target' => $attribute->target ?? $reflector->name]);
            });
            if (!class_exists(Reverse_Class_Object_Mapper_Metadata_Factory::class)) {
                $container->remove_definition('object_mapper.metadata_factory.reverse_class');
            }
        }
        $container->register_for_autoconfiguration(Package_Interface::class)->add_tag('assets.package');
        $container->register_for_autoconfiguration(Asset_Compiler_Interface::class)->add_tag('asset_mapper.compiler');
        $container->register_attribute_for_autoconfiguration(As_Command::class, static function (Child_Definition $definition, As_Command $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
            $tag_attributes = ['command' => $attribute->name, 'description' => $attribute->description, 'help' => $attribute->help ?? null];
            if ($reflector instanceof \ReflectionMethod) {
                $tag_attributes['method'] = $reflector->get_name();
            }
            $definition->add_tag('console.command', $tag_attributes);
            $definition->add_tag('console.command.service_arguments');
        });
        $container->register_for_autoconfiguration(Command::class)->add_tag('console.command')->add_tag('console.command.service_arguments');
        $container->register_for_autoconfiguration(Console_Value_Resolver_Interface::class)->add_tag('console.argument_value_resolver');
        $container->register_for_autoconfiguration(Resource_Checker_Interface::class)->add_tag('config_cache.resource_checker');
        $container->register_for_autoconfiguration(Env_Var_Loader_Interface::class)->add_tag('container.env_var_loader');
        $container->register_for_autoconfiguration(Env_Var_Processor_Interface::class)->add_tag('container.env_var_processor');
        $container->register_for_autoconfiguration(Callback_Interface::class)->add_tag('container.reversible');
        $container->register_for_autoconfiguration(Service_Locator::class)->add_tag('container.service_locator');
        $container->register_for_autoconfiguration(Service_Subscriber_Interface::class)->add_tag('container.service_subscriber');
        $container->register_for_autoconfiguration(Value_Resolver_Interface::class)->add_tag('controller.argument_value_resolver');
        $container->register_for_autoconfiguration(Abstract_Controller::class)->add_tag('controller.service_arguments');
        $container->register_for_autoconfiguration(Data_Collector_Interface::class)->add_tag('data_collector');
        $container->register_for_autoconfiguration(Form_Type_Interface::class)->add_tag('form.type', ['csrf_token_id' => '%.form.type_extension.csrf.token_id%']);
        $container->register_for_autoconfiguration(Form_Type_Guesser_Interface::class)->add_tag('form.type_guesser');
        $container->register_for_autoconfiguration(Form_Type_Extension_Interface::class)->add_tag('form.type_extension');
        $container->register_for_autoconfiguration(Cache_Clearer_Interface::class)->add_tag('kernel.cache_clearer');
        $container->register_for_autoconfiguration(Cache_Warmer_Interface::class)->add_tag('kernel.cache_warmer');
        $container->register_for_autoconfiguration(Event_Dispatcher_Interface::class)->add_tag('event_dispatcher.dispatcher');
        $container->register_for_autoconfiguration(Event_Subscriber_Interface::class)->add_tag('kernel.event_subscriber');
        $container->register_for_autoconfiguration(Locale_Aware_Interface::class)->add_tag('kernel.locale_aware');
        $container->register_for_autoconfiguration(Reset_Interface::class)->add_tag('kernel.reset', ['method' => 'reset']);
        $container->register_for_autoconfiguration(Property_List_Extractor_Interface::class)->add_tag('property_info.list_extractor');
        $container->register_for_autoconfiguration(Property_Type_Extractor_Interface::class)->add_tag('property_info.type_extractor');
        $container->register_for_autoconfiguration(Constructor_Argument_Type_Extractor_Interface::class)->add_tag('property_info.constructor_extractor');
        $container->register_for_autoconfiguration(Property_Description_Extractor_Interface::class)->add_tag('property_info.description_extractor');
        $container->register_for_autoconfiguration(Property_Access_Extractor_Interface::class)->add_tag('property_info.access_extractor');
        $container->register_for_autoconfiguration(Property_Initializable_Extractor_Interface::class)->add_tag('property_info.initializable_extractor');
        $container->register_for_autoconfiguration(Encoder_Interface::class)->add_tag('serializer.encoder');
        $container->register_for_autoconfiguration(Decoder_Interface::class)->add_tag('serializer.encoder');
        $container->register_for_autoconfiguration(Normalizer_Interface::class)->add_tag('serializer.normalizer');
        $container->register_for_autoconfiguration(Denormalizer_Interface::class)->add_tag('serializer.normalizer');
        $container->register_for_autoconfiguration(Constraint_Validator_Interface::class)->add_tag('validator.constraint_validator');
        $container->register_for_autoconfiguration(Group_Provider_Interface::class)->add_tag('validator.group_provider');
        $container->register_for_autoconfiguration(Object_Initializer_Interface::class)->add_tag('validator.initializer');
        $container->register_for_autoconfiguration(Batch_Handler_Interface::class)->add_tag('messenger.message_handler');
        $container->register_for_autoconfiguration(Messenger_Transport_Factory_Interface::class)->add_tag('messenger.transport_factory');
        $container->register_for_autoconfiguration(Mime_Type_Guesser_Interface::class)->add_tag('mime.mime_type_guesser');
        $container->register_for_autoconfiguration(Logger_Aware_Interface::class)->add_method_call('setLogger', [new Reference('logger')]);
        $container->register_attribute_for_autoconfiguration(As_Event_Listener::class, static function (Child_Definition $definition, As_Event_Listener $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
            $tag_attributes = get_object_vars($attribute);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tag_attributes['method'])) {
                    throw new LogicException(\sprintf('AsEventListener attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tag_attributes['method'] = $reflector->get_name();
            }
            $definition->add_tag('kernel.event_listener', $tag_attributes);
        });
        $container->register_attribute_for_autoconfiguration(As_Controller::class, static function (Child_Definition $definition, As_Controller $attribute): void {
            $definition->add_tag('controller.service_arguments');
        });
        $container->register_attribute_for_autoconfiguration(Route::class, static function (Child_Definition $definition, Route $attribute, \ReflectionClass|\ReflectionMethod $reflection): void {
            $definition->add_tag('controller.service_arguments')->add_tag('routing.controller');
        });
        $container->register_attribute_for_autoconfiguration(As_Remote_Event_Consumer::class, static function (Child_Definition $definition, As_Remote_Event_Consumer $attribute): void {
            $definition->add_tag('remote_event.consumer', ['consumer' => $attribute->name]);
        });
        $container->register_attribute_for_autoconfiguration(As_Message_Handler::class, static function (Child_Definition $definition, As_Message_Handler $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
            $tag_attributes = get_object_vars($attribute);
            $tag_attributes['from_transport'] = $tag_attributes['fromTransport'];
            unset($tag_attributes['fromTransport']);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tag_attributes['method'])) {
                    throw new LogicException(\sprintf('AsMessageHandler attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tag_attributes['method'] = $reflector->get_name();
            }
            $definition->add_tag('messenger.message_handler', $tag_attributes);
        });
        $container->register_attribute_for_autoconfiguration(As_Targeted_Value_Resolver::class, static function (Child_Definition $definition, As_Targeted_Value_Resolver $attribute): void {
            $definition->add_tag('controller.targeted_value_resolver', $attribute->name ? ['name' => $attribute->name] : []);
        });
        $container->register_attribute_for_autoconfiguration(As_Targeted_Console_Value_Resolver::class, static function (Child_Definition $definition, As_Targeted_Console_Value_Resolver $attribute): void {
            $definition->add_tag('console.targeted_value_resolver', $attribute->name ? ['name' => $attribute->name] : []);
        });
        $container->register_attribute_for_autoconfiguration(As_Schedule::class, static function (Child_Definition $definition, As_Schedule $attribute): void {
            $definition->add_tag('scheduler.schedule_provider', ['name' => $attribute->name]);
        });
        foreach ([As_Periodic_Task::class, As_Cron_Task::class] as $task_attribute_class) {
            $container->register_attribute_for_autoconfiguration($task_attribute_class, static function (Child_Definition $definition, As_Periodic_Task|As_Cron_Task $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
                $tag_attributes = get_object_vars($attribute) + ['trigger' => match (true) {
                    $attribute instanceof As_Periodic_Task => 'every',
                    $attribute instanceof As_Cron_Task => 'cron',
                }];
                if ($reflector instanceof \ReflectionMethod) {
                    if (isset($tag_attributes['method'])) {
                        throw new LogicException(\sprintf('"%s" attribute cannot declare a method on "%s::%s()".', $attribute::class, $reflector->class, $reflector->name));
                    }
                    $tag_attributes['method'] = $reflector->get_name();
                }
                $definition->add_tag('scheduler.task', $tag_attributes);
            });
        }
        $container->register_for_autoconfiguration(Compiler_Pass_Interface::class)->add_tag('container.excluded', ['source' => 'because it\'s a compiler pass']);
        $container->register_for_autoconfiguration(Constraint::class)->add_tag('container.excluded', ['source' => 'because it\'s a validation constraint']);
        $container->register_for_autoconfiguration(Test_Case::class)->add_tag('container.excluded', ['source' => 'because it\'s a test case']);
        $container->register_for_autoconfiguration(\Unit_Enum::class)->add_tag('container.excluded', ['source' => 'because it\'s an enum']);
        $container->register_attribute_for_autoconfiguration(As_Message::class, static function (Child_Definition $definition, As_Message $attribute): void {
            $definition->add_tag('container.excluded', ['source' => 'because it\'s a messenger message']);
            $definition->add_tag('messenger.message', ['serializedTypeName' => $attribute->serialized_type_name]);
        });
        $container->register_attribute_for_autoconfiguration(\Attribute::class, static function (Child_Definition $definition): void {
            $definition->add_tag('container.excluded', ['source' => 'because it\'s a PHP attribute']);
        });
        $container->register_attribute_for_autoconfiguration(Entity::class, static function (Child_Definition $definition): void {
            $definition->add_tag('container.excluded', ['source' => 'because it\'s a Doctrine entity']);
        });
        $container->register_attribute_for_autoconfiguration(Embeddable::class, static function (Child_Definition $definition): void {
            $definition->add_tag('container.excluded', ['source' => 'because it\'s a Doctrine embeddable']);
        });
        $container->register_attribute_for_autoconfiguration(Mapped_Superclass::class, static function (Child_Definition $definition): void {
            $definition->add_tag('container.excluded', ['source' => 'because it\'s a Doctrine mapped superclass']);
        });
        $container->register_attribute_for_autoconfiguration(Json_Streamable::class, static function (Child_Definition $definition, Json_Streamable $attribute): void {
            $definition->add_tag('json_streamer.streamable', ['object' => $attribute->as_object, 'list' => $attribute->as_list])->add_tag('container.excluded', ['source' => 'because it\'s a streamable JSON']);
        });
        if (!$container->get_parameter('kernel.debug')) {
            // remove tagged iterator argument for resource checkers
            $container->get_definition('config_cache_factory')->set_arguments([]);
        }
        if (!$config['disallow_search_engine_index']) {
            $container->remove_definition('disallow_search_engine_index_response_listener');
        }
        $container->register_for_autoconfiguration(Route_Loader_Interface::class)->add_tag('routing.route_loader');
        $container->set_parameter('container.behavior_describing_tags', ['container.do_not_inline', 'container.service_locator', 'container.service_subscriber', 'kernel.event_subscriber', 'kernel.event_listener', 'kernel.locale_aware', 'kernel.reset']);
    }
    public function get_configuration(array $config, Container_Builder $container): ?Configuration_Interface
    {
        return new Configuration($container->get_parameter('kernel.debug'));
    }
    protected function has_console(): bool
    {
        return class_exists(Application::class);
    }
    private function register_form_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        $loader->load('form.php');
        if (!property_exists(Validator_Extension::class, 'violationMapper')) {
            $container->remove_definition('form.violation_mapper');
            $container->remove_alias(Violation_Mapper_Interface::class);
            $container->get_definition('form.type_extension.form.validator')->replace_argument(1, false);
        }
        if (null === $config['form']['csrf_protection']['enabled']) {
            $this->write_config_enabled('form.csrf_protection', $config['csrf_protection']['enabled'], $config['form']['csrf_protection']);
        }
        if ($this->read_config_enabled('form.csrf_protection', $container, $config['form']['csrf_protection'])) {
            if (!$container->has_definition('security.csrf.token_generator')) {
                throw new \LogicException('To use form CSRF protection, "framework.csrf_protection" must be enabled.');
            }
            $loader->load('form_csrf.php');
            $container->set_parameter('form.type_extension.csrf.enabled', true);
            $container->set_parameter('form.type_extension.csrf.field_name', $config['form']['csrf_protection']['field_name']);
            $container->set_parameter('form.type_extension.csrf.field_attr', $config['form']['csrf_protection']['field_attr']);
            $container->set_parameter('.form.type_extension.csrf.token_id', $config['form']['csrf_protection']['token_id']);
        } else {
            $container->set_parameter('form.type_extension.csrf.enabled', false);
        }
        if (!Container_Builder::will_be_available('symfony/translation', Translator::class, ['symfony/framework-bundle', 'symfony/form'])) {
            $container->remove_definition('form.type_extension.upload.validator');
        }
    }
    private function register_http_cache_configuration(array $config, Container_Builder $container, bool $http_method_override, ?array $allowed_http_method_override): void
    {
        $options = $config;
        unset($options['enabled']);
        if (!$options['private_headers']) {
            unset($options['private_headers']);
        }
        if (!$options['skip_response_headers']) {
            unset($options['skip_response_headers']);
        }
        $container->get_definition('http_cache')->set_public($config['enabled'])->replace_argument(3, $options);
        if ($http_method_override) {
            $container->get_definition('http_cache')->add_argument((new Definition('void'))->set_factory(Request::enable_http_method_parameter_override(...)));
        }
        if (null !== $allowed_http_method_override) {
            $container->get_definition('http_cache')->add_argument((new Definition('void'))->set_factory(Request::set_allowed_http_method_override(...))->add_argument($allowed_http_method_override));
        }
    }
    private function register_esi_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        if (!$this->read_config_enabled('esi', $container, $config)) {
            $container->remove_definition('fragment.renderer.esi');
            return;
        }
        $loader->load('esi.php');
    }
    private function register_ssi_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        if (!$this->read_config_enabled('ssi', $container, $config)) {
            $container->remove_definition('fragment.renderer.ssi');
            return;
        }
        $loader->load('ssi.php');
    }
    private function register_fragments_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        if (!$this->read_config_enabled('fragments', $container, $config)) {
            $container->remove_definition('fragment.renderer.hinclude');
            return;
        }
        $container->set_parameter('fragment.renderer.hinclude.global_template', $config['hinclude_default_template']);
        $loader->load('fragment_listener.php');
        $container->set_parameter('fragment.path', $config['path']);
    }
    private function register_profiler_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        if (!$this->read_config_enabled('profiler', $container, $config)) {
            // this is needed for the WebProfiler to work even if the profiler is disabled
            $container->set_parameter('data_collector.templates', []);
            return;
        }
        $loader->load('profiling.php');
        $loader->load('collectors.php');
        $loader->load('cache_debug.php');
        if ($this->is_initialized_config_enabled('form')) {
            $loader->load('form_debug.php');
        }
        if ($this->is_initialized_config_enabled('validation')) {
            $loader->load('validator_debug.php');
        }
        if ($this->is_initialized_config_enabled('translator')) {
            $loader->load('translation_debug.php');
            $container->get_definition('translator.data_collector')->set_decorated_service('translator');
        }
        if ($this->is_initialized_config_enabled('messenger')) {
            $loader->load('messenger_debug.php');
        }
        if ($this->is_initialized_config_enabled('mailer')) {
            $loader->load('mailer_debug.php');
        }
        if ($this->is_initialized_config_enabled('workflows')) {
            $loader->load('workflow_debug.php');
        }
        if ($this->is_initialized_config_enabled('http_client')) {
            $loader->load('http_client_debug.php');
        }
        if ($this->is_initialized_config_enabled('notifier')) {
            $loader->load('notifier_debug.php');
        }
        if ($this->is_initialized_config_enabled('serializer')) {
            $loader->load('serializer_debug.php');
        }
        $container->set_parameter('profiler_listener.only_exceptions', $config['only_exceptions']);
        $container->set_parameter('profiler_listener.only_main_requests', $config['only_main_requests']);
        // Choose storage class based on the DSN
        [$class] = explode(':', (string) $config['dsn'], 2);
        if ('file' !== $class) {
            throw new \LogicException(\sprintf('Driver "%s" is not supported for the profiler.', $class));
        }
        $container->set_parameter('profiler.storage.dsn', $config['dsn']);
        $container->get_definition('profiler')->add_argument($config['collect'])->add_tag('kernel.reset', ['method' => 'reset']);
        $container->get_definition('profiler_listener')->add_argument($config['collect_parameter']);
        if (!$container->get_parameter('kernel.debug') || !$this->has_console() || !$container->has('debug.stopwatch')) {
            $container->remove_definition('console_profiler_listener');
        }
        if (!$this->has_console()) {
            $container->remove_definition('.data_collector.command');
        }
    }
    private function register_workflow_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        if (!$config['enabled']) {
            $container->remove_definition('console.command.workflow_dump');
            return;
        }
        if (!class_exists(Workflow\Workflow::class)) {
            throw new LogicException('Workflow support cannot be enabled as the Workflow component is not installed. Try running "composer require symfony/workflow".');
        }
        $loader->load('workflow.php');
        $registry_definition = $container->get_definition('workflow.registry');
        foreach ($config['workflows'] as $name => $workflow) {
            $type = $workflow['type'];
            $workflow_id = \sprintf('%s.%s', $type, $name);
            // Process Metadata (workflow + places (transition is done in the "create transition" block))
            $metadata_store_definition = new Definition(Workflow\Metadata\In_Memory_Metadata_Store::class, [[], [], null]);
            if ($workflow['metadata']) {
                $metadata_store_definition->replace_argument(0, $workflow['metadata']);
            }
            $places_metadata = [];
            foreach ($workflow['places'] as $place) {
                if ($place['metadata']) {
                    $places_metadata[$place['name']] = $place['metadata'];
                }
            }
            if ($places_metadata) {
                $metadata_store_definition->replace_argument(1, $places_metadata);
            }
            // Create transitions
            $transitions = [];
            $guards_configuration = [];
            $transitions_metadata_definition = new Definition(\Spl_Object_Storage::class);
            // Global transition counter per workflow
            $transition_counter = 0;
            foreach ($workflow['transitions'] as $transition) {
                foreach (['from', 'to'] as $direction) {
                    foreach ($transition[$direction] as $k => $arc) {
                        $transition[$direction][$k] = new Definition(Arc::class, [$arc['place'], $arc['weight'] ?? 1]);
                    }
                }
                if ('workflow' === $type) {
                    $transition_id = \sprintf('.%s.transition.%s', $workflow_id, $transition_counter++);
                    $container->register($transition_id, Workflow\Transition::class)->set_arguments([$transition['name'], $transition['from'], $transition['to']]);
                    $transitions[] = new Reference($transition_id);
                    if (isset($transition['guard'])) {
                        $event_name = \sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                        $guards_configuration[$event_name][] = new Definition(Workflow\Event_Listener\Guard_Expression::class, [new Reference($transition_id), $transition['guard']]);
                    }
                    if ($transition['metadata']) {
                        $transitions_metadata_definition->add_method_call('offsetSet', [new Reference($transition_id), $transition['metadata']]);
                    }
                } elseif ('state_machine' === $type) {
                    foreach ($transition['from'] as $from) {
                        foreach ($transition['to'] as $to) {
                            $transition_id = \sprintf('.%s.transition.%s', $workflow_id, $transition_counter++);
                            $container->register($transition_id, Workflow\Transition::class)->set_arguments([$transition['name'], [$from], [$to]]);
                            $transitions[] = new Reference($transition_id);
                            if (isset($transition['guard'])) {
                                $event_name = \sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                                $guards_configuration[$event_name][] = new Definition(Workflow\Event_Listener\Guard_Expression::class, [new Reference($transition_id), $transition['guard']]);
                            }
                            if ($transition['metadata']) {
                                $transitions_metadata_definition->add_method_call('offsetSet', [new Reference($transition_id), $transition['metadata']]);
                            }
                        }
                    }
                }
            }
            $metadata_store_definition->replace_argument(2, $transitions_metadata_definition);
            $metadata_store_id = \sprintf('%s.metadata_store', $workflow_id);
            $container->set_definition($metadata_store_id, $metadata_store_definition);
            // Create places
            $places = array_column($workflow['places'], 'name');
            $initial_marking = $workflow['initial_marking'] ?? [];
            // Create a Definition
            $definition_definition = new Definition(Workflow\Definition::class);
            $definition_definition->add_argument($places);
            $definition_definition->add_argument($transitions);
            $definition_definition->add_argument($initial_marking);
            $definition_definition->add_argument(new Reference($metadata_store_id));
            $definition_definition_id = \sprintf('%s.definition', $workflow_id);
            // Create MarkingStore
            $marking_store_definition = null;
            if (isset($workflow['marking_store']['type']) || isset($workflow['marking_store']['property'])) {
                $marking_store_definition = new Child_Definition('workflow.marking_store.method');
                $marking_store_definition->set_arguments([
                    'state_machine' === $type,
                    // single state
                    $workflow['marking_store']['property'] ?? 'marking',
                ]);
            } elseif (isset($workflow['marking_store']['service'])) {
                $marking_store_definition = new Reference($workflow['marking_store']['service']);
            }
            // Validation
            $workflow['definition_validators'][] = match ($workflow['type']) {
                'state_machine' => Workflow\Validator\State_Machine_Validator::class,
                'workflow' => Workflow\Validator\Workflow_Validator::class,
                default => throw new \LogicException(\sprintf('Invalid workflow type "%s".', $workflow['type'])),
            };
            // Create Workflow
            $workflow_definition = new Child_Definition(\sprintf('%s.abstract', $type));
            $workflow_definition->replace_argument(0, new Reference($definition_definition_id));
            $workflow_definition->replace_argument(1, $marking_store_definition);
            $workflow_definition->replace_argument(3, $name);
            $workflow_definition->replace_argument(4, $workflow['events_to_dispatch']);
            $workflow_definition->add_tag('workflow', ['name' => $name, 'metadata' => $workflow['metadata'], 'definition_validators' => $workflow['definition_validators'], 'definition_id' => $definition_definition_id]);
            if ('workflow' === $type) {
                $workflow_definition->add_tag('workflow.workflow', ['name' => $name]);
            } elseif ('state_machine' === $type) {
                $workflow_definition->add_tag('workflow.state_machine', ['name' => $name]);
            }
            // Store to container
            $container->set_definition($workflow_id, $workflow_definition);
            $container->set_definition($definition_definition_id, $definition_definition);
            $container->register_alias_for_argument($workflow_id, Workflow_Interface::class, $name . '.' . $type, $name);
            // Add workflow to Registry
            if ($workflow['supports']) {
                foreach ($workflow['supports'] as $supported_class_name) {
                    $strategy_definition = new Definition(Workflow\Support_Strategy\Instance_Of_Support_Strategy::class, [$supported_class_name]);
                    $registry_definition->add_method_call('addWorkflow', [new Reference($workflow_id), $strategy_definition]);
                }
            } elseif (isset($workflow['support_strategy'])) {
                $registry_definition->add_method_call('addWorkflow', [new Reference($workflow_id), new Reference($workflow['support_strategy'])]);
            }
            // Enable the AuditTrail
            if ($workflow['audit_trail']['enabled']) {
                $listener = new Definition(Workflow\Event_Listener\Audit_Trail_Listener::class);
                $listener->add_tag('monolog.logger', ['channel' => 'workflow']);
                $listener->add_tag('kernel.event_listener', ['event' => \sprintf('workflow.%s.leave', $name), 'method' => 'onLeave']);
                $listener->add_tag('kernel.event_listener', ['event' => \sprintf('workflow.%s.transition', $name), 'method' => 'onTransition']);
                $listener->add_tag('kernel.event_listener', ['event' => \sprintf('workflow.%s.enter', $name), 'method' => 'onEnter']);
                $listener->add_argument(new Reference('logger'));
                $container->set_definition(\sprintf('.%s.listener.audit_trail', $workflow_id), $listener);
            }
            // Add Guard Listener
            if ($guards_configuration) {
                if (!class_exists(Expression_Language::class)) {
                    throw new LogicException('Cannot guard workflows as the ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
                }
                if (!class_exists(Authentication_Events::class)) {
                    throw new LogicException('Cannot guard workflows as the Security component is not installed. Try running "composer require symfony/security-core".');
                }
                $guard = new Definition(Workflow\Event_Listener\Guard_Listener::class);
                $guard->set_arguments([$guards_configuration, new Reference('workflow.security.expression_language'), new Reference('security.token_storage'), new Reference('security.authorization_checker'), new Reference('security.authentication.trust_resolver'), new Reference('security.role_hierarchy'), new Reference('validator', Container_Interface::NULL_ON_INVALID_REFERENCE)]);
                foreach ($guards_configuration as $event_name => $config) {
                    $guard->add_tag('kernel.event_listener', ['event' => $event_name, 'method' => 'onTransition']);
                }
                $container->set_definition(\sprintf('.%s.listener.guard', $workflow_id), $guard);
                $container->set_parameter('workflow.has_guard_listeners', true);
            }
        }
    }
    private function register_debug_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        $loader->load('debug_prod.php');
        $debug = $container->get_parameter('kernel.debug');
        if (class_exists(Stopwatch::class)) {
            $container->register('debug.stopwatch', Stopwatch::class)->add_argument(true)->set_public($debug)->add_tag('kernel.reset', ['method' => 'reset']);
            $container->set_alias(Stopwatch::class, new Alias('debug.stopwatch', false));
        }
        if ($debug && !$container->has_parameter('debug.container.dump')) {
            $container->set_parameter('debug.container.dump', '%kernel.build_dir%/%kernel.container_class%.xml');
        }
        if ($debug && class_exists(Stopwatch::class)) {
            $loader->load('debug.php');
        }
        $definition = $container->find_definition('debug.error_handler_configurator');
        if (false === $config['log']) {
            $definition->replace_argument(0, null);
        } elseif (true !== $config['log']) {
            $definition->replace_argument(1, $config['log']);
        }
        if (!$config['throw']) {
            $container->set_parameter('debug.error_handler.throw_at', 0);
        }
        if ($debug && class_exists(Debug_Processor::class)) {
            $definition = new Definition(Debug_Processor::class);
            $definition->add_argument(new Reference('.virtual_request_stack'));
            $definition->add_tag('kernel.reset', ['method' => 'reset']);
            $container->set_definition('debug.log_processor', $definition);
            $container->register('debug.debug_logger_configurator', Debug_Logger_Configurator::class)->set_arguments([new Reference('debug.log_processor'), '%kernel.runtime_mode.web%']);
        }
    }
    private function register_router_configuration(array $config, Container_Builder $container, Php_File_Loader $loader, array $enabled_locales): void
    {
        if (!$this->read_config_enabled('router', $container, $config)) {
            $container->remove_definition('console.command.router_debug');
            $container->remove_definition('console.command.router_match');
            $container->remove_definition('messenger.middleware.router_context');
            return;
        }
        if (!class_exists(Router_Context_Middleware::class)) {
            $container->remove_definition('messenger.middleware.router_context');
        }
        $loader->load('routing.php');
        $container->deprecate_parameter('router.request_context.scheme', 'symfony/framework-bundle', '8.1', 'Parameter "router.request_context.scheme" is deprecated, use "router.request_context.base_url" parameter or the "framework.router.default_uri" config option instead.');
        $container->deprecate_parameter('router.request_context.host', 'symfony/framework-bundle', '8.1', 'Parameter "router.request_context.host" is deprecated, use "router.request_context.base_url" parameter or the "framework.router.default_uri" config option instead.');
        if ($config['utf8']) {
            $container->get_definition('routing.loader')->replace_argument(1, ['utf8' => true]);
        }
        if ($enabled_locales) {
            $used_envs = [];
            $container->resolve_env_placeholders($enabled_locales, null, $used_envs);
            if (!$used_envs) {
                $locales = implode('|', array_map(preg_quote(...), $enabled_locales));
            } else {
                $locales = (new Definition('string'))->set_factory('implode')->set_arguments(['|', (new Definition('array'))->set_factory('array_map')->set_arguments(['preg_quote', $enabled_locales])]);
            }
            $container->get_definition('routing.loader')->replace_argument(2, ['_locale' => $locales]);
        }
        if (!Container_Builder::will_be_available('symfony/expression-language', Expression_Language::class, ['symfony/framework-bundle', 'symfony/routing'])) {
            $container->remove_definition('router.expression_language_provider');
        }
        $container->set_parameter('router.resource', $config['resource']);
        $container->set_parameter('router.cache_dir', '%kernel.build_dir%');
        $router = $container->find_definition('router.default');
        $argument = $router->get_argument(2);
        $argument['strict_requirements'] = $config['strict_requirements'];
        if (isset($config['type'])) {
            $argument['resource_type'] = $config['type'];
        }
        $router->replace_argument(2, $argument);
        $container->set_parameter('request_listener.http_port', $config['http_port']);
        $container->set_parameter('request_listener.https_port', $config['https_port']);
        if (null !== $config['default_uri']) {
            $container->set_parameter('router.request_context.base_url', $config['default_uri']);
        }
    }
    private function register_session_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        $loader->load('session.php');
        // session storage
        $container->set_alias('session.storage.factory', $config['storage_factory_id']);
        $options = ['cache_limiter' => '0'];
        foreach (['name', 'cookie_lifetime', 'cookie_path', 'cookie_domain', 'cookie_secure', 'cookie_httponly', 'cookie_samesite', 'use_cookies', 'gc_maxlifetime', 'gc_probability', 'gc_divisor'] as $key) {
            if (isset($config[$key])) {
                $options[$key] = $config[$key];
            }
        }
        if ('auto' === ($options['cookie_secure'] ?? null)) {
            $container->get_definition('session.storage.factory.native')->replace_argument(3, true);
            $container->get_definition('session.storage.factory.php_bridge')->replace_argument(2, true);
        }
        $container->set_parameter('session.storage.options', $options);
        // session handler (the internal callback registered with PHP session management)
        if (null === ($config['handler_id'] ?? $config['save_path'] ?? null)) {
            $config['save_path'] = null;
            $container->set_alias('session.handler', 'session.handler.native');
        } else {
            $config['handler_id'] ??= 'session.handler.native_file';
            if (!\array_key_exists('save_path', $config)) {
                $config['save_path'] = '%kernel.cache_dir%/sessions';
            }
            $container->resolve_env_placeholders($config['handler_id'], null, $used_envs);
            if ($used_envs || str_contains($config['handler_id'], '://')) {
                $id = '.cache_connection.' . Container_Builder::hash($config['handler_id']);
                $container->get_definition('session.abstract_handler')->replace_argument(0, $container->has_definition($id) ? new Reference($id) : $config['handler_id']);
                $container->set_alias('session.handler', 'session.abstract_handler');
            } else {
                $container->set_alias('session.handler', $config['handler_id']);
            }
        }
        $container->set_parameter('session.save_path', $config['save_path']);
        $container->set_parameter('session.metadata.update_threshold', $config['metadata_update_threshold']);
    }
    private function register_request_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        if ($config['formats']) {
            $loader->load('request.php');
            $listener = $container->get_definition('request.add_request_formats_listener');
            $listener->replace_argument(0, $config['formats']);
        }
    }
    private function register_assets_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        $loader->load('assets.php');
        if ($config['version_strategy']) {
            $default_version = new Reference($config['version_strategy']);
        } else {
            $default_version = $this->create_version($container, $config['version'], $config['version_format'], $config['json_manifest_path'], '_default', $config['strict_mode']);
        }
        $default_package = $this->create_package_definition($config['base_path'], $config['base_urls'], $default_version);
        $container->set_definition('assets._default_package', $default_package);
        foreach ($config['packages'] as $name => $package) {
            if (null !== $package['version_strategy']) {
                $version = new Reference($package['version_strategy']);
            } elseif (!\array_key_exists('version', $package) && null === $package['json_manifest_path']) {
                // if neither version nor json_manifest_path are specified, use the default
                $version = $default_version;
            } else {
                // let format fallback to main version_format
                $format = $package['version_format'] ?: $config['version_format'];
                $version = $package['version'] ?? null;
                $version = $this->create_version($container, $version, $format, $package['json_manifest_path'], $name, $package['strict_mode']);
            }
            $package_definition = $this->create_package_definition($package['base_path'], $package['base_urls'], $version)->add_tag('assets.package', ['package' => $name]);
            $container->set_definition('assets._package_' . $name, $package_definition);
            $container->register_alias_for_argument('assets._package_' . $name, Package_Interface::class, $name . '.package', $name);
        }
    }
    private function register_asset_mapper_configuration(array $config, Container_Builder $container, Php_File_Loader $loader, bool $asset_enabled, bool $http_client_enabled): void
    {
        $loader->load('asset_mapper.php');
        if (!$asset_enabled) {
            $container->remove_definition('asset_mapper.asset_package');
        }
        if (!$http_client_enabled) {
            $container->register('asset_mapper.http_client', Http_Client_Interface::class)->add_tag('container.error')->add_error('You cannot use the AssetMapper integration since the HttpClient component is not enabled. Try enabling the "framework.http_client" config option.');
        }
        $paths = $config['paths'];
        foreach ($container->get_parameter('kernel.bundles_metadata') as $name => $bundle) {
            if ($container->file_exists($dir = $bundle['path'] . '/Resources/public') || $container->file_exists($dir = $bundle['path'] . '/public')) {
                $paths[$dir] = \sprintf('bundles/%s', preg_replace('/bundle$/', '', strtolower((string) $name)));
            }
        }
        $excluded_path_patterns = [];
        foreach ($config['excluded_patterns'] as $path) {
            $excluded_path_patterns[] = Glob::to_regex($path, true, false);
        }
        $container->get_definition('asset_mapper.repository')->set_argument(0, $paths)->set_argument(2, $excluded_path_patterns)->set_argument(3, $config['exclude_dotfiles']);
        $container->get_definition('asset_mapper.public_assets_path_resolver')->set_argument(0, $config['public_prefix']);
        $public_directory = $this->get_public_directory($container);
        $public_assets_directory = rtrim($public_directory . '/' . ltrim((string) $config['public_prefix'], '/'), '/');
        $container->get_definition('asset_mapper.local_public_assets_filesystem')->set_argument(0, $public_directory);
        $container->get_definition('asset_mapper.compiled_asset_mapper_config_reader')->set_argument(0, $public_assets_directory);
        if (!$config['server']) {
            $container->remove_definition('asset_mapper.dev_server_subscriber');
        } else {
            $container->get_definition('asset_mapper.dev_server_subscriber')->set_argument(1, $config['public_prefix'])->set_argument(2, $config['extensions']);
        }
        $container->get_definition('asset_mapper.compiler.css_asset_url_compiler')->set_argument(0, $config['missing_import_mode']);
        $container->get_definition('asset_mapper.compiler.javascript_import_path_compiler')->set_argument(1, $config['missing_import_mode']);
        $container->get_definition('asset_mapper.importmap.remote_package_storage')->replace_argument(0, $config['vendor_dir']);
        $container->get_definition('asset_mapper.mapped_asset_factory')->replace_argument(2, $config['vendor_dir']);
        $container->get_definition('asset_mapper.importmap.config_reader')->replace_argument(0, $config['importmap_path']);
        $container->get_definition('asset_mapper.importmap.renderer')->replace_argument(3, $config['importmap_polyfill'])->replace_argument(4, $config['importmap_script_attributes']);
        $compressors = [];
        foreach ($config['precompress']['formats'] as $format) {
            $compressors[$format] = new Reference("asset_mapper.compressor.{$format}");
        }
        $container->get_definition('asset_mapper.compressor')->replace_argument(0, $compressors ?: null);
        if ($config['precompress']['enabled']) {
            $container->get_definition('asset_mapper.local_public_assets_filesystem')->add_argument(new Reference('asset_mapper.compressor'))->add_argument($config['precompress']['extensions']);
        }
    }
    /**
     * Returns a definition for an asset package.
     */
    private function create_package_definition(?string $base_path, array $base_urls, Reference $version): Definition
    {
        if ($base_path && $base_urls) {
            throw new \LogicException('An asset package cannot have base URLs and base paths.');
        }
        $package = new Child_Definition($base_urls ? 'assets.url_package' : 'assets.path_package');
        $package->replace_argument(0, $base_urls ?: $base_path)->replace_argument(1, $version);
        return $package;
    }
    private function create_version(Container_Builder $container, ?string $version, ?string $format, ?string $json_manifest_path, string $name, bool $strict_mode): Reference
    {
        // Configuration prevents $version and $jsonManifestPath from being set
        if (null !== $version) {
            $def = new Child_Definition('assets.static_version_strategy');
            $def->replace_argument(0, $version)->replace_argument(1, $format);
            $container->set_definition('assets._version_' . $name, $def);
            return new Reference('assets._version_' . $name);
        }
        if (null !== $json_manifest_path) {
            $def = new Child_Definition('assets.json_manifest_version_strategy');
            $def->replace_argument(0, $json_manifest_path);
            $def->replace_argument(2, $strict_mode);
            $container->set_definition('assets._version_' . $name, $def);
            return new Reference('assets._version_' . $name);
        }
        return new Reference('assets.empty_version_strategy');
    }
    private function register_translator_configuration(array $config, Container_Builder $container, Loader_Interface $loader, string $default_locale, array $enabled_locales): void
    {
        if (!$this->read_config_enabled('translator', $container, $config)) {
            $container->remove_definition('console.command.translation_debug');
            $container->remove_definition('console.command.translation_extract');
            $container->remove_definition('console.command.translation_pull');
            $container->remove_definition('console.command.translation_push');
            $container->remove_definition('console.command.translation_lint');
            return;
        }
        $loader->load('translation.php');
        if (!Container_Builder::will_be_available('symfony/translation', Locale_Switcher::class, ['symfony/framework-bundle'])) {
            $container->remove_definition('translation.locale_switcher');
        }
        // don't use ContainerBuilder::willBeAvailable() as these are not needed in production
        if (interface_exists(Parser::class)) {
            $container->remove_definition('translation.extractor.php');
        } else {
            $container->remove_definition('translation.extractor.php_ast');
        }
        $loader->load('translation_providers.php');
        // Use the "real" translator instead of the identity default
        $container->set_alias('translator', 'translator.default')->set_public(true);
        $container->set_alias('translator.formatter', new Alias($config['formatter'], false));
        $translator = $container->find_definition('translator.default');
        $translator->add_method_call('setFallbackLocales', [$config['fallbacks'] ?: [$default_locale]]);
        $default_options = $translator->get_argument(4);
        $default_options['cache_dir'] = $config['cache_dir'];
        $translator->set_argument(4, $default_options);
        $translator->set_argument(5, $enabled_locales);
        $container->set_parameter('translator.logging', $config['logging']);
        $container->set_parameter('translator.default_path', $config['default_path']);
        // Discover translation directories
        $dirs = [];
        $trans_paths = [];
        $non_existing_dirs = [];
        if (Container_Builder::will_be_available('symfony/validator', Validation::class, ['symfony/framework-bundle', 'symfony/translation'])) {
            $r = new \ReflectionClass(Validation::class);
            $dirs[] = $trans_paths[] = \dirname($r->get_file_name()) . '/Resources/translations';
        }
        if (Container_Builder::will_be_available('symfony/form', Form::class, ['symfony/framework-bundle', 'symfony/translation'])) {
            $r = new \ReflectionClass(Form::class);
            $dirs[] = $trans_paths[] = \dirname($r->get_file_name()) . '/Resources/translations';
        }
        if (Container_Builder::will_be_available('symfony/security-core', Authentication_Exception::class, ['symfony/framework-bundle', 'symfony/translation'])) {
            $r = new \ReflectionClass(Authentication_Exception::class);
            $dirs[] = $trans_paths[] = \dirname($r->get_file_name(), 2) . '/Resources/translations';
        }
        $default_dir = $container->get_parameter_bag()->resolve_value($config['default_path']);
        foreach ($container->get_parameter('kernel.bundles_metadata') as $name => $bundle) {
            if ($container->file_exists($dir = $bundle['path'] . '/Resources/translations') || $container->file_exists($dir = $bundle['path'] . '/translations')) {
                $dirs[] = $trans_paths[] = $dir;
            } else {
                $non_existing_dirs[] = $dir;
            }
        }
        foreach ($config['paths'] as $dir) {
            if ($container->file_exists($dir)) {
                $dirs[] = $trans_paths[] = $dir;
            } else {
                throw new \UnexpectedValueException(\sprintf('"%s" defined in translator.paths does not exist or is not a directory.', $dir));
            }
        }
        if ($container->has_definition('console.command.translation_debug')) {
            $container->get_definition('console.command.translation_debug')->replace_argument(5, $trans_paths);
        }
        if ($container->has_definition('console.command.translation_extract')) {
            $container->get_definition('console.command.translation_extract')->replace_argument(6, $trans_paths);
        }
        if (null === $default_dir) {
            // allow null
        } elseif ($container->file_exists($default_dir)) {
            $dirs[] = $default_dir;
        } else {
            $non_existing_dirs[] = $default_dir;
        }
        // Register translation resources
        if ($dirs) {
            $files = [];
            foreach ($dirs as $dir) {
                $finder = Finder::create()->follow_links()->files()->filter(static fn(\Spl_File_Info $file): bool => 2 <= substr_count($file->get_basename(), '.') && preg_match('/\.\w+$/', $file->get_basename()))->in($dir)->sort_by_name();
                foreach ($finder as $file) {
                    $file_name_parts = explode('.', basename($file));
                    $locale = $file_name_parts[\count($file_name_parts) - 2];
                    if (!isset($files[$locale])) {
                        $files[$locale] = [];
                    }
                    $files[$locale][] = (string) $file;
                }
            }
            $project_dir = $container->get_parameter('kernel.project_dir');
            $options = array_merge($translator->get_argument(4), ['resource_files' => $files, 'scanned_directories' => $scanned_directories = array_merge($dirs, $non_existing_dirs), 'cache_vary' => ['scanned_directories' => array_map(static fn($dir) => str_starts_with((string) $dir, $project_dir . '/') ? substr((string) $dir, 1 + \strlen($project_dir)) : $dir, $scanned_directories)]]);
            $translator->replace_argument(4, $options);
        }
        foreach ($config['globals'] as $name => $global) {
            $translator->add_method_call('addGlobalParameter', [$name, $global['value'] ?? new Definition(Translatable_Message::class, [$global['message'], $global['parameters'] ?? [], $global['domain'] ?? null])]);
        }
        if ($config['pseudo_localization']['enabled']) {
            $options = $config['pseudo_localization'];
            unset($options['enabled']);
            $container->register('translator.pseudo', Pseudo_Localization_Translator::class)->set_decorated_service('translator', null, -1)->set_arguments([new Reference('translator.pseudo.inner'), $options]);
        }
        $class_to_services = [Translation_Bridge\Crowdin\Crowdin_Provider_Factory::class => 'translation.provider_factory.crowdin', Translation_Bridge\Loco\Loco_Provider_Factory::class => 'translation.provider_factory.loco', Translation_Bridge\Lokalise\Lokalise_Provider_Factory::class => 'translation.provider_factory.lokalise', Translation_Bridge\Phrase\Phrase_Provider_Factory::class => 'translation.provider_factory.phrase'];
        $parent_packages = ['symfony/framework-bundle', 'symfony/translation', 'symfony/http-client'];
        foreach ($class_to_services as $class => $service) {
            $package = substr($service, \strlen('translation.provider_factory.'));
            if (!$container->has_definition('http_client') || !Container_Builder::will_be_available(\sprintf('symfony/%s-translation-provider', $package), $class, $parent_packages)) {
                $container->remove_definition($service);
            }
        }
        if (!$config['providers']) {
            return;
        }
        $locales = $enabled_locales;
        foreach ($config['providers'] as $provider) {
            if ($provider['locales']) {
                $locales = array_merge($locales, $provider['locales']);
            }
        }
        $locales = array_values(array_unique($locales));
        $container->get_definition('console.command.translation_pull')->replace_argument(4, array_merge($trans_paths, [$config['default_path']]))->replace_argument(5, $locales);
        $container->get_definition('console.command.translation_push')->replace_argument(2, array_merge($trans_paths, [$config['default_path']]))->replace_argument(3, $locales);
        $container->get_definition('translation.provider_collection_factory')->replace_argument(1, $locales);
        $container->get_definition('translation.provider_collection')->set_argument(0, $config['providers']);
    }
    private function register_validation_configuration(array $config, Container_Builder $container, Php_File_Loader $loader, bool $property_info_enabled): void
    {
        if (!$this->read_config_enabled('validation', $container, $config)) {
            $container->remove_definition('console.command.validator_debug');
            $container->remove_definition('.console.validate_question_input_listener');
            return;
        }
        if (!class_exists(Validation::class)) {
            throw new LogicException('Validation support cannot be enabled as the Validator component is not installed. Try running "composer require symfony/validator".');
        }
        if (!class_exists(Validate_Question_Input_Listener::class)) {
            $container->remove_definition('.console.validate_question_input_listener');
        }
        $loader->load('validator.php');
        $validator_builder = $container->get_definition('validator.builder');
        $container->set_parameter('validator.translation_domain', $config['translation_domain']);
        $files = ['xml' => [], 'yml' => []];
        $this->register_validator_mapping($container, $config, $files);
        if ($files['xml']) {
            $validator_builder->add_method_call('addXmlMappings', [$files['xml']]);
        }
        if ($files['yml']) {
            $validator_builder->add_method_call('addYamlMappings', [$files['yml']]);
        }
        $definition = $container->find_definition('validator.email');
        $definition->replace_argument(0, $config['email_validation_mode']);
        // When attributes are disabled, it means from runtime-discovery only; autoconfiguration should still happen.
        // And when runtime-discovery of attributes is enabled, we can skip compile-time autoconfiguration in debug mode.
        if (!($config['enable_attributes'] ?? false) || !$container->get_parameter('kernel.debug')) {
            // The $reflector argument hints at where the attribute could be used
            $container->register_attribute_for_autoconfiguration(Constraint::class, static function (Child_Definition $definition, Constraint $attribute, \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector): void {
                $definition->add_tag('validator.attribute_metadata');
            });
        }
        $container->register_attribute_for_autoconfiguration(Extends_Validation_For::class, static function (Child_Definition $definition, Extends_Validation_For $attribute): void {
            $definition->add_tag('validator.attribute_metadata', ['for' => $attribute->class])->add_tag('container.excluded', ['source' => 'because it\'s a validator constraint extension']);
        });
        if ($config['enable_attributes'] ?? false) {
            $validator_builder->add_method_call('enableAttributeMapping');
        }
        if ($config['static_method'] ?? false) {
            foreach ($config['static_method'] as $method_name) {
                $validator_builder->add_method_call('addMethodMapping', [$method_name]);
            }
        }
        if (!$container->get_parameter('kernel.debug')) {
            $validator_builder->add_method_call('setMappingCache', [new Reference('validator.mapping.cache.adapter')]);
        }
        if ($config['disable_translation'] ?? false) {
            $validator_builder->add_method_call('disableTranslation');
        }
        $container->set_parameter('validator.auto_mapping', $config['auto_mapping']);
        if (!$property_info_enabled || !class_exists(Property_Info_Loader::class)) {
            $container->remove_definition('validator.property_info_loader');
        }
        $container->get_definition('validator.not_compromised_password')->set_argument(2, $config['not_compromised_password']['enabled'])->set_argument(3, $config['not_compromised_password']['endpoint']);
        if (!class_exists(Expression_Language::class)) {
            $container->remove_definition('validator.expression_language');
            $container->remove_definition('validator.expression_language_provider');
        } elseif (!class_exists(Expression_Language_Provider::class)) {
            $container->remove_definition('validator.expression_language_provider');
        }
    }
    private function register_validator_mapping(Container_Builder $container, array $config, array &$files): void
    {
        $file_recorder = static function ($extension, $path) use (&$files): void {
            $files['yaml' === $extension ? 'yml' : $extension][] = $path;
        };
        if (!Container_Builder::will_be_available('symfony/form', Form::class, ['symfony/framework-bundle', 'symfony/validator'])) {
            $container->remove_definition('validator.form.attribute_metadata');
        }
        foreach ($container->get_parameter('kernel.bundles_metadata') as $bundle) {
            $config_dir = is_dir($bundle['path'] . '/Resources/config') ? $bundle['path'] . '/Resources/config' : $bundle['path'] . '/config';
            if ($container->file_exists($file = $config_dir . '/validation.yaml', false) || $container->file_exists($file = $config_dir . '/validation.yml', false)) {
                $file_recorder('yml', $file);
            }
            if ($container->file_exists($file = $config_dir . '/validation.xml', false)) {
                $file_recorder('xml', $file);
            }
            if ($container->file_exists($dir = $config_dir . '/validation', '/^$/')) {
                $this->register_mapping_files_from_dir($dir, $file_recorder);
            }
        }
        $project_dir = $container->get_parameter('kernel.project_dir');
        if ($container->file_exists($dir = $project_dir . '/config/validator', '/^$/')) {
            $this->register_mapping_files_from_dir($dir, $file_recorder);
        }
        $this->register_mapping_files_from_config($container, $config, $file_recorder);
    }
    private function register_mapping_files_from_dir(string $dir, callable $file_recorder): void
    {
        foreach (Finder::create()->follow_links()->files()->in($dir)->name('/\.(xml|ya?ml)$/')->sort_by_name() as $file) {
            $file_recorder($file->get_extension(), $file->get_real_path());
        }
    }
    private function register_mapping_files_from_config(Container_Builder $container, array $config, callable $file_recorder): void
    {
        foreach ($config['mapping']['paths'] as $path) {
            if (is_dir($path)) {
                $this->register_mapping_files_from_dir($path, $file_recorder);
                $container->add_resource(new Directory_Resource($path, '/^$/'));
            } elseif ($container->file_exists($path, false)) {
                if (!preg_match('/\.(xml|ya?ml)$/', (string) $path, $matches)) {
                    throw new \RuntimeException(\sprintf('Unsupported mapping type in "%s", supported types are XML & Yaml.', $path));
                }
                $file_recorder($matches[1], $path);
            } else {
                throw new \RuntimeException(\sprintf('Could not open file or directory "%s".', $path));
            }
        }
    }
    private function register_property_access_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        if (!$this->read_config_enabled('property_access', $container, $config)) {
            return;
        }
        $loader->load('property_access.php');
        $magic_methods = Property_Accessor::DISALLOW_MAGIC_METHODS;
        $magic_methods |= $config['magic_call'] ? Property_Accessor::MAGIC_CALL : 0;
        $magic_methods |= $config['magic_get'] ? Property_Accessor::MAGIC_GET : 0;
        $magic_methods |= $config['magic_set'] ? Property_Accessor::MAGIC_SET : 0;
        $throw = Property_Accessor::DO_NOT_THROW;
        $throw |= $config['throw_exception_on_invalid_index'] ? Property_Accessor::THROW_ON_INVALID_INDEX : 0;
        $throw |= $config['throw_exception_on_invalid_property_path'] ? Property_Accessor::THROW_ON_INVALID_PROPERTY_PATH : 0;
        $container->get_definition('property_accessor')->replace_argument(0, $magic_methods)->replace_argument(1, $throw);
    }
    private function register_secrets_configuration(array $config, Container_Builder $container, Php_File_Loader $loader, ?string $secret): void
    {
        if (!$this->read_config_enabled('secrets', $container, $config)) {
            $container->remove_definition('console.command.secrets_set');
            $container->remove_definition('console.command.secrets_list');
            $container->remove_definition('console.command.secrets_reveal');
            $container->remove_definition('console.command.secrets_remove');
            $container->remove_definition('console.command.secrets_generate_key');
            $container->remove_definition('console.command.secrets_decrypt_to_local');
            $container->remove_definition('console.command.secrets_encrypt_from_local');
            return;
        }
        $loader->load('secrets.php');
        $container->resolve_env_placeholders($secret, null, $used_envs);
        $secret_env_var = 1 === \count($used_envs ?? []) ? substr((string) key($used_envs), 1 + (strrpos((string) key($used_envs), ':') ?: -1)) : null;
        $container->get_definition('secrets.vault')->replace_argument(2, $secret_env_var);
        $container->get_definition('secrets.vault')->replace_argument(0, $config['vault_directory']);
        if ($config['local_dotenv_file']) {
            $container->get_definition('secrets.local_vault')->replace_argument(0, $config['local_dotenv_file']);
        } else {
            $container->remove_definition('secrets.local_vault');
        }
        if ($config['decryption_env_var']) {
            if (!preg_match('/^(?:[-.\w\\\\]*+:)*+[\w.]++$/', (string) $config['decryption_env_var'])) {
                throw new InvalidArgumentException(\sprintf('Invalid value "%s" set as "decryption_env_var": only "word" and dot characters are allowed.', $config['decryption_env_var']));
            }
            if (Container_Builder::will_be_available('symfony/string', Lazy_String::class, ['symfony/framework-bundle'])) {
                $container->get_definition('secrets.decryption_key')->replace_argument(1, $config['decryption_env_var']);
            } else {
                $container->get_definition('secrets.vault')->replace_argument(1, "%env({$config['decryption_env_var']})%");
                $container->remove_definition('secrets.decryption_key');
            }
        } else {
            $container->get_definition('secrets.vault')->replace_argument(1, null);
            $container->remove_definition('secrets.decryption_key');
        }
    }
    private function register_security_csrf_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        if (!$this->read_config_enabled('csrf_protection', $container, $config)) {
            return;
        }
        if (!class_exists(Csrf_Token::class)) {
            throw new LogicException('CSRF support cannot be enabled as the Security CSRF component is not installed. Try running "composer require symfony/security-csrf".');
        }
        if (!$config['stateless_token_ids'] && !$this->is_initialized_config_enabled('session')) {
            throw new \LogicException('CSRF protection needs sessions to be enabled.');
        }
        // Enable services for CSRF protection (even without forms)
        $loader->load('security_csrf.php');
        if (!class_exists(Csrf_Extension::class)) {
            $container->remove_definition('twig.extension.security_csrf');
        }
        if (!$config['stateless_token_ids']) {
            $container->remove_definition('security.csrf.same_origin_token_manager');
            $container->remove_definition('security.csrf.same_origin_listener');
            return;
        }
        $container->get_definition('security.csrf.same_origin_token_manager')->replace_argument(3, $config['stateless_token_ids'])->replace_argument(4, $config['check_header'])->replace_argument(5, $config['cookie_name']);
        $container->get_definition('security.csrf.same_origin_listener')->replace_argument(0, $config['cookie_name']);
        if (!$this->is_initialized_config_enabled('session')) {
            $container->set_alias('security.csrf.token_manager', 'security.csrf.same_origin_token_manager');
            $container->get_definition('security.csrf.same_origin_token_manager')->set_decorated_service(null)->replace_argument(2, null);
        }
    }
    private function register_serializer_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        $loader->load('serializer.php');
        $chain_loader = $container->get_definition('serializer.mapping.chain_loader');
        if (!$this->is_initialized_config_enabled('property_access')) {
            $container->remove_alias('serializer.property_accessor');
            $container->remove_definition('serializer.normalizer.object');
        }
        if (!class_exists(Yaml::class)) {
            $container->remove_definition('serializer.encoder.yaml');
        }
        if (!$this->is_initialized_config_enabled('property_access')) {
            $container->remove_definition('serializer.denormalizer.unwrapping');
        }
        if (!class_exists(Headers::class)) {
            $container->remove_definition('serializer.normalizer.mime_message');
        }
        if ($container->get_parameter('kernel.debug')) {
            $container->remove_definition('serializer.mapping.cache_class_metadata_factory');
        }
        if (!$this->read_config_enabled('translator', $container, $config)) {
            $container->remove_definition('serializer.normalizer.translatable');
        }
        $serializer_loaders = [];
        // When attributes are disabled, it means from runtime-discovery only; autoconfiguration should still happen.
        // And when runtime-discovery of attributes is enabled, we can skip compile-time autoconfiguration in debug mode.
        if (!($config['enable_attributes'] ?? false) || !$container->get_parameter('kernel.debug')) {
            // The $reflector argument hints at where the attribute could be used
            $configurator = static function (Child_Definition $definition, object $attribute, \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector): void {
                $definition->add_tag('serializer.attribute_metadata');
            };
            $container->register_attribute_for_autoconfiguration(Serializer_Mapping\Context::class, $configurator);
            $container->register_attribute_for_autoconfiguration(Serializer_Mapping\Groups::class, $configurator);
            $configurator = static function (Child_Definition $definition, object $attribute, \ReflectionMethod|\ReflectionProperty $reflector): void {
                $definition->add_tag('serializer.attribute_metadata');
            };
            $container->register_attribute_for_autoconfiguration(Serializer_Mapping\Ignore::class, $configurator);
            $container->register_attribute_for_autoconfiguration(Serializer_Mapping\Max_Depth::class, $configurator);
            $container->register_attribute_for_autoconfiguration(Serializer_Mapping\Serialized_Name::class, $configurator);
            $container->register_attribute_for_autoconfiguration(Serializer_Mapping\Serialized_Path::class, $configurator);
            $container->register_attribute_for_autoconfiguration(Serializer_Mapping\Discriminator_Map::class, static function (Child_Definition $definition): void {
                $definition->add_tag('serializer.attribute_metadata');
            });
        }
        $serializer_loaders[] = new Reference('serializer.mapping.attribute_loader');
        $container->get_definition('serializer.mapping.attribute_loader')->replace_argument(0, $config['enable_attributes'] ?? false);
        $file_recorder = static function ($extension, $path) use (&$serializer_loaders): void {
            $definition = new Definition(\in_array($extension, ['yaml', 'yml'], true) ? Yaml_File_Loader::class : Xml_File_Loader::class, [$path]);
            $serializer_loaders[] = $definition;
        };
        foreach ($container->get_parameter('kernel.bundles_metadata') as $bundle) {
            $config_dir = is_dir($bundle['path'] . '/Resources/config') ? $bundle['path'] . '/Resources/config' : $bundle['path'] . '/config';
            if ($container->file_exists($file = $config_dir . '/serialization.xml', false)) {
                $file_recorder('xml', $file);
            }
            if ($container->file_exists($file = $config_dir . '/serialization.yaml', false) || $container->file_exists($file = $config_dir . '/serialization.yml', false)) {
                $file_recorder('yml', $file);
            }
            if ($container->file_exists($dir = $config_dir . '/serialization', '/^$/')) {
                $this->register_mapping_files_from_dir($dir, $file_recorder);
            }
        }
        $project_dir = $container->get_parameter('kernel.project_dir');
        if ($container->file_exists($dir = $project_dir . '/config/serializer', '/^$/')) {
            $this->register_mapping_files_from_dir($dir, $file_recorder);
        }
        $this->register_mapping_files_from_config($container, $config, $file_recorder);
        $chain_loader->replace_argument(0, $serializer_loaders);
        $container->get_definition('serializer.mapping.cache_warmer')->replace_argument(0, $serializer_loaders);
        if ($config['name_converter'] ?? false) {
            $container->set_parameter('.serializer.name_converter', $config['name_converter']);
            $container->get_definition('serializer.name_converter.metadata_aware')->set_argument(1, new Reference($config['name_converter']));
        }
        $default_context = $config['default_context'] ?? [];
        if ($default_context) {
            $container->set_parameter('serializer.default_context', $default_context);
        }
        if ($config['circular_reference_handler'] ?? false) {
            $container->set_parameter('.serializer.circular_reference_handler', $config['circular_reference_handler']);
        }
        if ($config['max_depth_handler'] ?? false) {
            $container->set_parameter('.serializer.max_depth_handler', $config['max_depth_handler']);
        }
        $container->get_definition('serializer.normalizer.property')->set_argument(5, $default_context);
        $container->set_parameter('.serializer.named_serializers', $config['named_serializers'] ?? []);
        $container->register_attribute_for_autoconfiguration(Extends_Serialization_For::class, static function (Child_Definition $definition, Extends_Serialization_For $attribute): void {
            $definition->add_tag('serializer.attribute_metadata', ['for' => $attribute->class])->add_tag('container.excluded', ['source' => 'because it\'s a serializer metadata extension']);
        });
    }
    private function register_json_streamer_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        if (!class_exists(Json_Stream_Writer::class)) {
            throw new LogicException('JsonStreamer support cannot be enabled as the JsonStreamer component is not installed. Try running "composer require symfony/json-streamer".');
        }
        $container->register_for_autoconfiguration(Value_Transformer_Interface::class)->add_tag('json_streamer.value_transformer');
        $loader->load('json_streamer.php');
        $container->set_parameter('.json_streamer.default_options', $config['default_options']);
        $container->set_parameter('.json_streamer.stream_writers_dir', '%kernel.cache_dir%/json_streamer/stream_writer');
        $container->set_parameter('.json_streamer.stream_readers_dir', '%kernel.cache_dir%/json_streamer/stream_reader');
        // BC layer for "symfony/json-streamer" < 8.0
        if (method_exists(Property_Metadata::class, 'getNativeToStreamValueTransformer')) {
            $container->get_definition('json_streamer.stream_writer')->replace_argument(4, null);
            $container->get_definition('json_streamer.stream_reader')->replace_argument(4, null);
        }
    }
    private function register_property_info_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        if (!interface_exists(Property_Info_Extractor_Interface::class)) {
            throw new LogicException('PropertyInfo support cannot be enabled as the PropertyInfo component is not installed. Try running "composer require symfony/property-info".');
        }
        $loader->load('property_info.php');
        if (!$config['with_constructor_extractor']) {
            $container->remove_definition('property_info.constructor_extractor');
        }
        if (Container_Builder::will_be_available('phpstan/phpdoc-parser', Php_Doc_Parser::class, ['symfony/framework-bundle', 'symfony/property-info']) && Container_Builder::will_be_available('phpdocumentor/type-resolver', Context_Factory::class, ['symfony/framework-bundle', 'symfony/property-info'])) {
            $definition = $container->register('property_info.phpstan_extractor', Php_Stan_Extractor::class);
            $definition->add_tag('property_info.type_extractor', ['priority' => -1000]);
            $definition->add_tag('property_info.constructor_extractor', ['priority' => -1000]);
        }
        if (Container_Builder::will_be_available('phpdocumentor/reflection-docblock', Doc_Block_Factory_Interface::class, ['symfony/framework-bundle', 'symfony/property-info'])) {
            $definition = $container->register('property_info.php_doc_extractor', Php_Doc_Extractor::class);
            $definition->add_tag('property_info.description_extractor', ['priority' => -1000]);
            $definition->add_tag('property_info.type_extractor', ['priority' => -1001]);
            $definition->add_tag('property_info.constructor_extractor', ['priority' => -1001]);
        }
        if ($container->get_parameter('kernel.debug')) {
            $container->remove_definition('property_info.cache');
        }
    }
    private function register_type_info_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        if (!class_exists(Type::class)) {
            throw new LogicException('TypeInfo support cannot be enabled as the TypeInfo component is not installed. Try running "composer require symfony/type-info".');
        }
        $loader->load('type_info.php');
        if (Container_Builder::will_be_available('phpstan/phpdoc-parser', Php_Doc_Parser::class, ['symfony/framework-bundle', 'symfony/type-info'])) {
            $container->register('type_info.resolver.string', String_Type_Resolver::class)->set_arguments([null, null, $config['aliases']]);
            $container->register('type_info.resolver.reflection_parameter.phpdoc_aware', Php_Doc_Aware_Reflection_Type_Resolver::class)->set_arguments([new Reference('type_info.resolver.reflection_parameter'), new Reference('type_info.resolver.string'), new Reference('type_info.type_context_factory')]);
            $container->register('type_info.resolver.reflection_property.phpdoc_aware', Php_Doc_Aware_Reflection_Type_Resolver::class)->set_arguments([new Reference('type_info.resolver.reflection_property'), new Reference('type_info.resolver.string'), new Reference('type_info.type_context_factory')]);
            $container->register('type_info.resolver.reflection_return.phpdoc_aware', Php_Doc_Aware_Reflection_Type_Resolver::class)->set_arguments([new Reference('type_info.resolver.reflection_return'), new Reference('type_info.resolver.string'), new Reference('type_info.type_context_factory')]);
            /** @var ServiceLocatorArgument $resolversLocator */
            $resolvers_locator = $container->get_definition('type_info.resolver')->get_argument(0);
            $resolvers_locator->set_values(['string' => new Reference('type_info.resolver.string'), \ReflectionParameter::class => new Reference('type_info.resolver.reflection_parameter.phpdoc_aware'), \ReflectionProperty::class => new Reference('type_info.resolver.reflection_property.phpdoc_aware'), \Reflection_Function_Abstract::class => new Reference('type_info.resolver.reflection_return.phpdoc_aware')] + $resolvers_locator->get_values());
            $container->get_definition('type_info.type_context_factory')->replace_argument(1, $config['aliases']);
        }
    }
    private function register_lock_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        $loader->load('lock.php');
        // BC layer Lock < 7.4
        if (!interface_exists(Denormalizer_Interface::class) || !class_exists(Lock_Key_Normalizer::class)) {
            $container->remove_definition('serializer.normalizer.lock_key');
        }
        foreach ($config['resources'] as $resource_name => $resource_stores) {
            if (0 === \count($resource_stores)) {
                continue;
            }
            // Generate stores
            $store_definitions = [];
            foreach ($resource_stores as $resource_store) {
                if (\in_array($resource_store, ['flock', 'semaphore'], true)) {
                    $store_definition_id = \sprintf('.lock.%s.store', $resource_store);
                    $store_definitions[] = new Reference($store_definition_id);
                    $container->get_definition($store_definition_id)->add_tag('lock.store');
                    continue;
                }
                $used_envs = [];
                $store_dsn = $container->resolve_env_placeholders($resource_store, null, $used_envs);
                if (!$used_envs && !str_contains((string) $resource_store, ':') && !\in_array($resource_store, ['flock', 'semaphore', 'in-memory', 'null'], true)) {
                    $resource_store = new Reference($resource_store);
                }
                $store_definition = new Definition(Persisting_Store_Interface::class);
                $store_definition->set_factory(Store_Factory::create_store(...))->set_arguments([$resource_store])->add_tag('lock.store');
                $container->set_definition($store_definition_id = '.lock.' . $resource_name . '.store.' . $container->hash($store_dsn), $store_definition);
                $store_definitions[] = new Reference($store_definition_id);
            }
            // Wrap array of stores with CombinedStore
            if (\count($store_definitions) > 1) {
                $combined_definition = new Child_Definition('lock.store.combined.abstract');
                $combined_definition->replace_argument(0, $store_definitions);
                $container->set_definition($store_definition_id = '.lock.' . $resource_name . '.store.' . $container->hash($resource_stores), $combined_definition);
            }
            // Generate factories for each resource
            $factory_definition = new Child_Definition('lock.factory.abstract');
            $factory_definition->replace_argument(0, new Reference($store_definition_id));
            $container->set_definition('lock.' . $resource_name . '.factory', $factory_definition);
            // provide alias for default resource
            if ('default' === $resource_name) {
                $container->set_alias('lock.factory', new Alias('lock.' . $resource_name . '.factory', false));
                $container->set_alias(Lock_Factory::class, new Alias('lock.factory', false));
            } else {
                $container->register_alias_for_argument('lock.' . $resource_name . '.factory', Lock_Factory::class, $resource_name . '.lock.factory', $resource_name);
            }
        }
    }
    private function register_semaphore_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        if (!class_exists(Semaphore::class)) {
            throw new LogicException('Semaphore support cannot be enabled as the Semaphore component is not installed. Try running "composer require symfony/semaphore".');
        }
        $loader->load('semaphore.php');
        // BC layer Semaphore < 7.4
        if (!interface_exists(Denormalizer_Interface::class) || !class_exists(Semaphore_Key_Normalizer::class)) {
            $container->remove_definition('serializer.normalizer.semaphore_key');
        }
        foreach ($config['resources'] as $resource_name => $resource_store) {
            $store_dsn = $container->resolve_env_placeholders($resource_store, null, $used_envs);
            if (str_starts_with((string) $store_dsn, 'lock://') && !class_exists(Lock_Store::class)) {
                throw new LogicException('Cannot use a lock store as the installed version of the Semaphore component does not support it. Try running "composer require symfony/semaphore:^8.1".');
            }
            $store_definition = new Definition(Semaphore_Store_Interface::class);
            $store_definition->set_factory(Semaphore_Store_Factory::create_store(...));
            $store_definition->set_arguments([match (true) {
                $used_envs => $resource_store,
                str_starts_with((string) $store_dsn, 'lock://') => new Reference('lock.' . (substr((string) $store_dsn, 7) ?: 'default') . '.factory'),
                !str_contains((string) $resource_store, '://') => new Reference($resource_store),
                default => $resource_store,
            }]);
            $container->set_definition($store_definition_id = '.semaphore.' . $resource_name . '.store.' . $container->hash($store_dsn), $store_definition);
            // Generate factories for each resource
            $factory_definition = new Child_Definition('semaphore.factory.abstract');
            $factory_definition->replace_argument(0, new Reference($store_definition_id));
            $container->set_definition('semaphore.' . $resource_name . '.factory', $factory_definition);
            // Generate services for semaphore instances
            $semaphore_definition = new Definition(Semaphore::class);
            $semaphore_definition->set_factory([new Reference('semaphore.' . $resource_name . '.factory'), 'createSemaphore']);
            $semaphore_definition->set_arguments([$resource_name]);
            // provide alias for default resource
            if ('default' === $resource_name) {
                $container->set_alias('semaphore.factory', new Alias('semaphore.' . $resource_name . '.factory', false));
                $container->set_alias(Semaphore_Factory::class, new Alias('semaphore.factory', false));
            } else {
                $container->register_alias_for_argument('semaphore.' . $resource_name . '.factory', Semaphore_Factory::class, $resource_name . '.semaphore.factory', $resource_name);
            }
        }
    }
    private function register_scheduler_configuration(Container_Builder $container, Php_File_Loader $loader): void
    {
        if (!class_exists(Scheduler_Transport_Factory::class)) {
            throw new LogicException('Scheduler support cannot be enabled as the Scheduler component is not installed. Try running "composer require symfony/scheduler".');
        }
        $loader->load('scheduler.php');
        if (!$this->has_console()) {
            $container->remove_definition('console.command.scheduler_debug');
        }
    }
    private function register_messenger_configuration(array $config, Container_Builder $container, Php_File_Loader $loader, bool $validation_enabled, bool $lock_enabled): void
    {
        if (!interface_exists(Message_Bus_Interface::class)) {
            throw new LogicException('Messenger support cannot be enabled as the Messenger component is not installed. Try running "composer require symfony/messenger".');
        }
        if (!$this->has_console()) {
            $container->remove_definition('console.command.messenger_stats');
        }
        $loader->load('messenger.php');
        if (!interface_exists(Denormalizer_Interface::class)) {
            $container->remove_definition('serializer.normalizer.flatten_exception');
        }
        if (Container_Builder::will_be_available('symfony/amqp-messenger', Messenger_Bridge\Amqp\Transport\Amqp_Transport_Factory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->get_definition('messenger.transport.amqp.factory')->add_tag('messenger.transport_factory');
        }
        if (Container_Builder::will_be_available('symfony/redis-messenger', Messenger_Bridge\Redis\Transport\Redis_Transport_Factory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->get_definition('messenger.transport.redis.factory')->add_tag('messenger.transport_factory');
        }
        if (Container_Builder::will_be_available('symfony/amazon-sqs-messenger', Messenger_Bridge\Amazon_Sqs\Transport\Amazon_Sqs_Transport_Factory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->get_definition('messenger.transport.sqs.factory')->add_tag('messenger.transport_factory');
        }
        if (Container_Builder::will_be_available('symfony/beanstalkd-messenger', Messenger_Bridge\Beanstalkd\Transport\Beanstalkd_Transport_Factory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->get_definition('messenger.transport.beanstalkd.factory')->add_tag('messenger.transport_factory');
        }
        if ($config['stop_worker_on_signals'] && $this->has_console()) {
            $container->get_definition('console.command.messenger_consume_messages')->replace_argument(8, $config['stop_worker_on_signals']);
            $container->get_definition('console.command.messenger_failed_messages_retry')->replace_argument(6, $config['stop_worker_on_signals']);
        }
        if (null === $config['default_bus'] && 1 === \count($config['buses'])) {
            $config['default_bus'] = key($config['buses']);
        }
        $default_middleware = ['before' => [['id' => 'add_default_stamps_middleware'], ['id' => 'add_bus_name_stamp_middleware'], ['id' => 'reject_redelivered_message_middleware'], ['id' => 'dispatch_after_current_bus'], ...class_exists(Decode_Failed_Message_Middleware::class) ? [['id' => 'decode_failed_message_middleware']] : [], ['id' => 'failed_message_processing_middleware']], 'after' => [['id' => 'send_message'], ['id' => 'handle_message']]];
        if ($lock_enabled && class_exists(Lock_Factory::class)) {
            $default_middleware['before'][] = ['id' => 'deduplicate_middleware'];
        } else {
            $container->remove_definition('messenger.middleware.deduplicate_middleware');
        }
        foreach ($config['buses'] as $bus_id => $bus) {
            $middleware = $bus['middleware'];
            if ($bus['default_middleware']['enabled']) {
                $default_middleware['after'][0]['arguments'] = [$bus['default_middleware']['allow_no_senders']];
                $default_middleware['after'][1]['arguments'] = [$bus['default_middleware']['allow_no_handlers']];
                $middleware = array_merge($default_middleware['before'], $middleware, $default_middleware['after']);
            }
            foreach ($middleware as $key => $middleware_item) {
                if (!$validation_enabled && \in_array($middleware_item['id'], ['validation', 'messenger.middleware.validation'], true)) {
                    throw new LogicException('The Validation middleware is only available when the Validator component is installed and enabled. Try running "composer require symfony/validator".');
                }
                // argument to add_bus_name_stamp_middleware
                if ('add_bus_name_stamp_middleware' === $middleware_item['id']) {
                    $middleware[$key]['arguments'] = [$bus_id];
                }
            }
            if ($container->get_parameter('kernel.debug') && class_exists(Stopwatch::class)) {
                array_unshift($middleware, ['id' => 'traceable', 'arguments' => [$bus_id]]);
            }
            $container->set_parameter($bus_id . '.middleware', $middleware);
            $container->register($bus_id, Message_Bus::class)->add_argument([])->add_tag('messenger.bus');
            if ($bus_id === $config['default_bus']) {
                $container->set_alias('messenger.default_bus', $bus_id)->set_public(true);
                $container->set_alias(Message_Bus_Interface::class, $bus_id);
            } else {
                $container->register_alias_for_argument($bus_id, Message_Bus_Interface::class);
            }
        }
        if (empty($config['transports'])) {
            $container->remove_definition('messenger.transport.symfony_serializer');
            $container->remove_definition('messenger.transport.amqp.factory');
            $container->remove_definition('messenger.transport.redis.factory');
            $container->remove_definition('messenger.transport.sqs.factory');
            $container->remove_definition('messenger.transport.beanstalkd.factory');
            $container->remove_alias(Serializer_Interface::class);
        } else {
            $container->get_definition('messenger.transport.symfony_serializer')->replace_argument(1, $config['serializer']['symfony_serializer']['format'])->replace_argument(2, $config['serializer']['symfony_serializer']['context']);
            $container->set_alias('messenger.default_serializer', $config['serializer']['default_serializer']);
        }
        $failure_transports = [];
        if ($config['failure_transport']) {
            if (!isset($config['transports'][$config['failure_transport']])) {
                throw new LogicException(\sprintf('Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.', $config['failure_transport']));
            }
            $container->set_alias('messenger.failure_transports.default', 'messenger.transport.' . $config['failure_transport']);
            $failure_transports[] = $config['failure_transport'];
        }
        $failure_transports_by_name = [];
        foreach ($config['transports'] as $name => $transport) {
            if ($transport['failure_transport']) {
                $failure_transports[] = $transport['failure_transport'];
                $failure_transports_by_name[$name] = $transport['failure_transport'];
            } elseif ($config['failure_transport']) {
                $failure_transports_by_name[$name] = $config['failure_transport'];
            }
        }
        $sender_aliases = [];
        $transport_retry_references = [];
        $transport_rate_limiter_references = [];
        $serializer_references_by_transport = [];
        $serializer_ids = [];
        foreach ($config['transports'] as $name => $transport) {
            $serializer_id = $transport['serializer'] ?? 'messenger.default_serializer';
            $tags = ['alias' => $name, 'is_failure_transport' => \in_array($name, $failure_transports, true)];
            $serializer_references_by_transport[$name] = new Reference($serializer_id);
            if (str_starts_with((string) $transport['dsn'], 'sync://')) {
                $tags['is_consumable'] = false;
            }
            $transport_definition = (new Definition(Transport_Interface::class))->set_factory([new Reference('messenger.transport_factory'), 'createTransport'])->set_arguments([$transport['dsn'], $transport['options'] + ['transport_name' => $name], new Reference($serializer_id)])->add_tag('messenger.receiver', $tags);
            $container->set_definition($transport_id = 'messenger.transport.' . $name, $transport_definition);
            $sender_aliases[$name] = $transport_id;
            $serializer_ids[$transport_id] = $serializer_id;
            if (null !== $transport['retry_strategy']['service']) {
                $transport_retry_references[$name] = new Reference($transport['retry_strategy']['service']);
            } else {
                $retry_service_id = \sprintf('messenger.retry.multiplier_retry_strategy.%s', $name);
                $retry_definition = new Child_Definition('messenger.retry.abstract_multiplier_retry_strategy');
                $retry_definition->replace_argument(0, $transport['retry_strategy']['max_retries'])->replace_argument(1, $transport['retry_strategy']['delay'])->replace_argument(2, $transport['retry_strategy']['multiplier'])->replace_argument(3, $transport['retry_strategy']['max_delay'])->replace_argument(4, $transport['retry_strategy']['jitter']);
                $container->set_definition($retry_service_id, $retry_definition);
                $transport_retry_references[$name] = new Reference($retry_service_id);
            }
            if ($transport['rate_limiter']) {
                if (!interface_exists(Limiter_Interface::class)) {
                    throw new LogicException('Rate limiter cannot be used within Messenger as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
                }
                $transport_rate_limiter_references[$name] = new Reference('limiter.' . $transport['rate_limiter']);
            }
        }
        if (class_exists(Decode_Failed_Message_Middleware::class)) {
            $container->get_definition('messenger.transport.serializer_locator')->replace_argument(0, $serializer_references_by_transport);
        } else {
            $container->remove_definition('messenger.middleware.decode_failed_message_middleware');
        }
        $sender_references = [];
        foreach ($sender_aliases as $alias => $transport_id) {
            $sender_references[$alias] = new Reference($transport_id);
        }
        foreach ($sender_aliases as $transport_id) {
            $sender_references[$transport_id] = new Reference($transport_id);
        }
        foreach ($config['transports'] as $transport) {
            if (!$transport['failure_transport']) {
                continue;
            }
            if (isset($sender_references[$transport['failure_transport']])) {
                continue;
            }
            throw new LogicException(\sprintf('Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.', $transport['failure_transport']));
        }
        $failure_transport_references_by_transport_name = array_map(static fn($failure_transport_name): \Symfony\Component\Dependency_Injection\Reference => $sender_references[$failure_transport_name], $failure_transports_by_name);
        $message_to_senders_mapping = [];
        foreach ($config['routing'] as $message => $message_configuration) {
            if ('*' !== $message && !class_exists($message) && !interface_exists($message, false) && !preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+\\\\)++\*$/', (string) $message)) {
                if (str_contains((string) $message, '*')) {
                    throw new LogicException(\sprintf('Invalid Messenger routing configuration: invalid namespace "%s" wildcard.', $message));
                }
                throw new LogicException(\sprintf('Invalid Messenger routing configuration: class or interface "%s" not found.', $message));
            }
            // make sure senderAliases contains all senders
            foreach ($message_configuration['senders'] as $sender) {
                if (!isset($sender_references[$sender])) {
                    throw new LogicException(\sprintf('Invalid Messenger routing configuration: the "%s" class is being routed to a sender called "%s". This is not a valid transport or service id.', $message, $sender));
                }
            }
            $message_to_senders_mapping[$message] = $message_configuration['senders'];
        }
        $senders_service_locator = Service_Locator_Tag_Pass::register($container, $sender_references);
        $container->get_definition('messenger.senders_locator')->replace_argument(0, $message_to_senders_mapping)->replace_argument(1, $senders_service_locator);
        $message_to_serializers_mapping = [];
        foreach ($message_to_senders_mapping as $message => $senders) {
            foreach ($senders as $sender) {
                $serializer_id = $serializer_ids[$sender_aliases[$sender] ?? $sender];
                $message_to_serializers_mapping[$message][$serializer_id] = $serializer_id;
            }
            $message_to_serializers_mapping[$message] = array_keys($message_to_serializers_mapping[$message]);
        }
        $container->get_definition('messenger.signing_serializer')->replace_argument(2, $message_to_serializers_mapping);
        $container->get_definition('messenger.retry.send_failed_message_for_retry_listener')->replace_argument(0, $senders_service_locator);
        $container->get_definition('messenger.retry_strategy_locator')->replace_argument(0, $transport_retry_references);
        if (!$transport_rate_limiter_references) {
            $container->remove_definition('messenger.rate_limiter_locator');
        } else {
            $container->get_definition('messenger.rate_limiter_locator')->replace_argument(0, $transport_rate_limiter_references);
        }
        if (\count($failure_transports) > 0) {
            if ($this->has_console()) {
                $container->get_definition('console.command.messenger_failed_messages_retry')->replace_argument(0, $config['failure_transport']);
                $container->get_definition('console.command.messenger_failed_messages_show')->replace_argument(0, $config['failure_transport']);
                $container->get_definition('console.command.messenger_failed_messages_remove')->replace_argument(0, $config['failure_transport']);
            }
            $failure_transports_by_transport_name_service_locator = Service_Locator_Tag_Pass::register($container, $failure_transport_references_by_transport_name);
            $container->get_definition('messenger.failure.send_failed_message_to_failure_transport_listener')->replace_argument(0, $failure_transports_by_transport_name_service_locator)->replace_argument(2, $failure_transports_by_name);
        } else {
            $container->remove_definition('messenger.failure.send_failed_message_to_failure_transport_listener');
            $container->remove_definition('console.command.messenger_failed_messages_retry');
            $container->remove_definition('console.command.messenger_failed_messages_show');
            $container->remove_definition('console.command.messenger_failed_messages_remove');
        }
        if (!$container->has_definition('console.command.messenger_consume_messages')) {
            $container->remove_definition('messenger.listener.reset_services');
        }
    }
    private function register_cache_configuration(array $config, Container_Builder $container): void
    {
        $version = new Parameter('container.build_id');
        $container->get_definition('cache.adapter.apcu')->replace_argument(2, $version);
        $container->get_definition('cache.adapter.system')->replace_argument(2, $version);
        $container->get_definition('cache.adapter.filesystem')->replace_argument(2, $config['directory']);
        if (isset($config['prefix_seed'])) {
            $container->set_parameter('cache.prefix.seed', $config['prefix_seed']);
        }
        if ($container->has_parameter('cache.prefix.seed')) {
            // Inline any env vars referenced in the parameter
            $container->set_parameter('cache.prefix.seed', $container->resolve_env_placeholders($container->get_parameter('cache.prefix.seed'), true));
        }
        foreach (['psr6', 'redis', 'valkey', 'memcached', 'doctrine_dbal', 'pdo'] as $name) {
            if (isset($config[$name = 'default_' . $name . '_provider'])) {
                $container->set_alias('cache.' . $name, new Alias(Cache_Pool_Pass::get_service_provider($container, $config[$name]), false));
            }
        }
        foreach (['app', 'system'] as $name) {
            $config['pools']['cache.' . $name] = ['adapters' => [$config[$name]], 'public' => true, 'tags' => false];
        }
        $redis_tag_aware_adapters = [['cache.adapter.redis_tag_aware'], ['cache.adapter.valkey_tag_aware']];
        foreach ($config['pools'] as $name => $pool) {
            $pool['adapters'] = $pool['adapters'] ?: ['cache.app'];
            $is_redis_tag_aware = \in_array($pool['adapters'], $redis_tag_aware_adapters, true);
            foreach ($pool['adapters'] as $provider => $adapter) {
                if (\in_array($config['pools'][$adapter]['adapters'] ?? null, $redis_tag_aware_adapters, true)) {
                    $is_redis_tag_aware = true;
                } elseif ($config['pools'][$adapter]['tags'] ?? false) {
                    $pool['adapters'][$provider] = $adapter = '.' . $adapter . '.inner';
                }
            }
            if (1 === \count($pool['adapters'])) {
                if (!isset($pool['provider']) && !\is_int($provider)) {
                    $pool['provider'] = $provider;
                }
                $definition = new Child_Definition($adapter);
            } else {
                $definition = new Definition(Chain_Adapter::class, [$pool['adapters'], 0]);
                $pool['reset'] = 'reset';
            }
            if ($is_redis_tag_aware && 'cache.app' === $name) {
                $container->set_alias('cache.app.taggable', $name);
                $definition->add_tag('cache.taggable', ['pool' => $name]);
            } elseif ($is_redis_tag_aware) {
                $tag_aware_id = $name;
                $container->set_alias('.' . $name . '.inner', $name);
                $definition->add_tag('cache.taggable', ['pool' => $name]);
            } elseif ($pool['tags']) {
                if (true !== $pool['tags'] && ($config['pools'][$pool['tags']]['tags'] ?? false)) {
                    $pool['tags'] = '.' . $pool['tags'] . '.inner';
                }
                $container->register($name, Tag_Aware_Adapter::class)->add_argument(new Reference('.' . $name . '.inner'))->add_argument(true !== $pool['tags'] ? new Reference($pool['tags']) : null)->add_method_call('setLogger', [new Reference('logger', Container_Interface::IGNORE_ON_INVALID_REFERENCE)])->set_public($pool['public'])->add_tag('cache.taggable', ['pool' => $name])->add_tag('monolog.logger', ['channel' => 'cache']);
                $pool['name'] = $tag_aware_id = $name;
                $pool['public'] = false;
                $name = '.' . $name . '.inner';
            } elseif (!\in_array($name, ['cache.app', 'cache.system'], true)) {
                $tag_aware_id = '.' . $name . '.taggable';
                $container->register($tag_aware_id, Tag_Aware_Adapter::class)->add_argument(new Reference($name))->add_tag('cache.taggable', ['pool' => $name]);
            }
            if (!\in_array($name, ['cache.app', 'cache.system'], true)) {
                $container->register_alias_for_argument($tag_aware_id, Tag_Aware_Cache_Interface::class, $pool['name'] ?? $name);
                $container->register_alias_for_argument($name, Cache_Interface::class, $pool['name'] ?? $name);
                $container->register_alias_for_argument($name, Cache_Item_Pool_Interface::class, $pool['name'] ?? $name);
                $container->register_alias_for_argument($name, Namespaced_Pool_Interface::class, $pool['name'] ?? $name);
            }
            $definition->set_public($pool['public']);
            unset($pool['adapters'], $pool['public'], $pool['tags']);
            $definition->add_tag('cache.pool', $pool);
            $container->set_definition($name, $definition);
        }
        if (class_exists(Property_Accessor::class)) {
            $property_access_definition = $container->register('cache.property_access', Adapter_Interface::class);
            if (!$container->get_parameter('kernel.debug')) {
                $property_access_definition->set_factory(Property_Accessor::create_cache(...));
                $property_access_definition->set_arguments(['', 0, $version, new Reference('logger', Container_Interface::IGNORE_ON_INVALID_REFERENCE)]);
                $property_access_definition->add_tag('cache.pool', ['clearer' => 'cache.system_clearer']);
                $property_access_definition->add_tag('monolog.logger', ['channel' => 'cache']);
            } else {
                $property_access_definition->set_class(Array_Adapter::class);
                $property_access_definition->set_arguments([0, false]);
            }
        }
    }
    private function register_http_client_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        $loader->load('http_client.php');
        $options = $config['default_options'] ?? [];
        $caching_options = $options['caching'] ?? ['enabled' => false];
        unset($options['caching']);
        $rate_limiter = $options['rate_limiter'] ?? null;
        unset($options['rate_limiter']);
        $retry_options = $options['retry_failed'] ?? ['enabled' => false];
        unset($options['retry_failed']);
        $default_uri_template_vars = $options['vars'] ?? [];
        unset($options['vars']);
        $container->get_definition('http_client.transport')->set_arguments([$options, $config['max_host_connections'] ?? 6]);
        if (!$has_psr18 = Container_Builder::will_be_available('psr/http-client', Client_Interface::class, ['symfony/framework-bundle', 'symfony/http-client'])) {
            $container->remove_definition('psr18.http_client');
            $container->remove_alias(Client_Interface::class);
        }
        if (!$has_httplug = Container_Builder::will_be_available('php-http/httplug', Http_Async_Client::class, ['symfony/framework-bundle', 'symfony/http-client'])) {
            $container->remove_definition('httplug.http_client');
            $container->remove_alias(Http_Async_Client::class);
            $container->remove_alias(Http_Client::class);
        }
        if ($this->read_config_enabled('http_client.caching', $container, $caching_options)) {
            $this->register_caching_http_client($caching_options, $options, 'http_client', $container);
        }
        if (null !== $rate_limiter) {
            $this->register_throttling_http_client($rate_limiter, 'http_client', $container);
        }
        if ($this->read_config_enabled('http_client.retry_failed', $container, $retry_options)) {
            $this->register_retryable_http_client($retry_options, 'http_client', $container);
        }
        if (Container_Builder::will_be_available('guzzlehttp/uri-template', \Guzzle_Http\Uri_Template\Uri_Template::class, [])) {
            $container->set_alias('http_client.uri_template_expander', 'http_client.uri_template_expander.guzzle');
        } elseif (Container_Builder::will_be_available('rize/uri-template', \Rize\Uri_Template::class, [])) {
            $container->set_alias('http_client.uri_template_expander', 'http_client.uri_template_expander.rize');
        }
        $container->get_definition('http_client.uri_template')->set_argument(2, $default_uri_template_vars);
        if (!$default_mock_response_factory = $config['mock_response_factory'] ?? null) {
            $default_transport_id = 'http_client.transport';
        } elseif (\is_string($default_mock_response_factory)) {
            $default_transport_id = '.http_client.mock_transport.' . $default_mock_response_factory;
            $container->register($default_transport_id, Mock_Http_Client::class)->set_arguments([new Reference($default_mock_response_factory)])->add_tag('kernel.reset', ['method' => 'reset']);
        } else {
            $default_transport_id = 'http_client.mock_transport';
        }
        foreach ($config['scoped_clients'] as $name => $scope_config) {
            if ($container->has($name)) {
                throw new InvalidArgumentException(\sprintf('Invalid scope name: "%s" is reserved.', $name));
            }
            $scope = $scope_config['scope'] ?? null;
            unset($scope_config['scope']);
            $caching_options = $scope_config['caching'] ?? ['enabled' => false];
            unset($scope_config['caching']);
            $rate_limiter = $scope_config['rate_limiter'] ?? null;
            unset($scope_config['rate_limiter']);
            $retry_options = $scope_config['retry_failed'] ?? ['enabled' => false];
            unset($scope_config['retry_failed']);
            if (false === $mock_response_factory = $scope_config['mock_response_factory'] ?? $default_mock_response_factory) {
                $transport_id = 'http_client.transport';
            } elseif ($mock_response_factory === $default_mock_response_factory) {
                $transport_id = $default_transport_id;
            } elseif (\is_string($mock_response_factory)) {
                $transport_id = '.http_client.mock_transport.' . $mock_response_factory;
                $container->register($transport_id, Mock_Http_Client::class)->set_arguments([new Reference($mock_response_factory)])->add_tag('kernel.reset', ['method' => 'reset']);
            } else {
                $transport_id = 'http_client.mock_transport';
            }
            unset($scope_config['mock_response_factory']);
            // This "transport" service is decorated in the following order:
            // 1. ThrottlingHttpClient (5) -> throttles requests
            // 2. UriTemplateHttpClient (10) -> expands URI templates
            // 3. ScopingHttpClient (15) -> resolves relative URLs and applies scope configuration
            // 4. CachingHttpClient (20) -> caches responses
            // 5. RetryableHttpClient (25) -> retries requests
            // 6. TraceableHttpClient (100) -> traces requests
            $container->register($name, Http_Client_Interface::class)->set_factory('current')->set_arguments([[new Reference($transport_id)]])->add_tag('http_client.client');
            $scoping_definition = $container->register($name . '.scoping', Scoping_Http_Client::class)->set_decorated_service($name, null, 15)->add_tag('kernel.reset', ['method' => 'reset', 'on_invalid' => 'ignore']);
            if (null === $scope) {
                $base_uri = $scope_config['base_uri'];
                unset($scope_config['base_uri']);
                $scoping_definition->set_factory([Scoping_Http_Client::class, 'forBaseUri'])->set_arguments([new Reference('.inner'), $base_uri, $scope_config]);
            } else {
                $scoping_definition->set_arguments([new Reference('.inner'), [$scope => $scope_config], $scope]);
            }
            if ($this->read_config_enabled('http_client.scoped_clients.' . $name . '.caching', $container, $caching_options)) {
                $this->register_caching_http_client($caching_options, $scope_config, $name, $container);
            }
            if (null !== $rate_limiter) {
                $this->register_throttling_http_client($rate_limiter, $name, $container);
            }
            if ($this->read_config_enabled('http_client.scoped_clients.' . $name . '.retry_failed', $container, $retry_options)) {
                $this->register_retryable_http_client($retry_options, $name, $container);
            }
            $container->register($name . '.uri_template', Uri_Template_Http_Client::class)->set_decorated_service($name, null, 10)->set_arguments([new Reference('.inner'), new Reference('http_client.uri_template_expander', Container_Interface::NULL_ON_INVALID_REFERENCE), $default_uri_template_vars]);
            $container->register_alias_for_argument($name, Http_Client_Interface::class);
            if ($has_psr18) {
                $container->set_definition('psr18.' . $name, new Child_Definition('psr18.http_client'))->replace_argument(0, new Reference($name));
                $container->register_alias_for_argument('psr18.' . $name, Client_Interface::class, $name);
            }
            if ($has_httplug) {
                $container->set_definition('httplug.' . $name, new Child_Definition('httplug.http_client'))->replace_argument(0, new Reference($name));
                $container->register_alias_for_argument('httplug.' . $name, Http_Async_Client::class, $name);
            }
        }
    }
    private function register_caching_http_client(array $options, array $default_options, string $name, Container_Builder $container): void
    {
        if (!class_exists(Chunk_Cache_Item_Not_Found_Exception::class)) {
            throw new LogicException('Caching cannot be enabled as version 7.4+ of the HttpClient component is required.');
        }
        $container->register($name . '.caching', Caching_Http_Client::class)->set_decorated_service($name, null, 20)->set_arguments([new Reference('.inner'), new Reference($options['cache_pool']), $default_options, $options['shared'], $options['max_ttl']]);
    }
    private function register_throttling_http_client(string $rate_limiter, string $name, Container_Builder $container): void
    {
        if (!$this->is_initialized_config_enabled('rate_limiter')) {
            throw new LogicException('Rate limiter cannot be used within HttpClient as the RateLimiter component is not enabled.');
        }
        $container->register($name . '.throttling.limiter', Limiter_Interface::class)->set_factory([new Reference('limiter.' . $rate_limiter), 'create']);
        $container->register($name . '.throttling', Throttling_Http_Client::class)->set_decorated_service($name, null, 5)->set_arguments([new Reference('.inner'), new Reference($name . '.throttling.limiter')]);
    }
    private function register_retryable_http_client(array $options, string $name, Container_Builder $container): void
    {
        if (null !== $options['retry_strategy']) {
            $retry_strategy = new Reference($options['retry_strategy']);
        } else {
            $retry_strategy = new Child_Definition('http_client.abstract_retry_strategy');
            $codes = [];
            foreach ($options['http_codes'] as $code => $code_options) {
                if ($code_options['methods']) {
                    $codes[$code] = $code_options['methods'];
                } else {
                    $codes[] = $code;
                }
            }
            $retry_strategy->replace_argument(0, $codes ?: Generic_Retry_Strategy::DEFAULT_RETRY_STATUS_CODES)->replace_argument(1, $options['delay'])->replace_argument(2, $options['multiplier'])->replace_argument(3, $options['max_delay'])->replace_argument(4, $options['jitter']);
            $container->set_definition($name . '.retry_strategy', $retry_strategy);
            $retry_strategy = new Reference($name . '.retry_strategy');
        }
        $container->register($name . '.retryable', Retryable_Http_Client::class)->set_decorated_service($name, null, 25)->set_arguments([new Reference('.inner'), $retry_strategy, $options['max_retries'], new Reference('logger')])->add_tag('monolog.logger', ['channel' => 'http_client']);
    }
    private function register_mailer_configuration(array $config, Container_Builder $container, Php_File_Loader $loader, bool $webhook_enabled): void
    {
        if (!class_exists(Mailer::class)) {
            throw new LogicException('Mailer support cannot be enabled as the component is not installed. Try running "composer require symfony/mailer".');
        }
        $loader->load('mailer.php');
        $loader->load('mailer_transports.php');
        if (!\count($config['transports']) && null === $config['dsn']) {
            $config['dsn'] = 'smtp://null';
        }
        $transports = $config['dsn'] ? ['main' => $config['dsn']] : $config['transports'];
        $container->get_definition('mailer.transports')->set_argument(0, $transports);
        $mailer = $container->get_definition('mailer.mailer');
        if (false === $message_bus = $config['message_bus']) {
            $mailer->replace_argument(1, null);
        } else {
            $mailer->replace_argument(1, $message_bus ? new Reference($message_bus) : new Reference('messenger.default_bus', Container_Interface::NULL_ON_INVALID_REFERENCE));
        }
        $class_to_services = [Mailer_Bridge\Aha_Send\Transport\Aha_Send_Transport_Factory::class => 'mailer.transport_factory.ahasend', Mailer_Bridge\Azure\Transport\Azure_Transport_Factory::class => 'mailer.transport_factory.azure', Mailer_Bridge\Brevo\Transport\Brevo_Transport_Factory::class => 'mailer.transport_factory.brevo', Mailer_Bridge\Google\Transport\Gmail_Transport_Factory::class => 'mailer.transport_factory.gmail', Mailer_Bridge\Infobip\Transport\Infobip_Transport_Factory::class => 'mailer.transport_factory.infobip', Mailer_Bridge\Mailer_Send\Transport\Mailer_Send_Transport_Factory::class => 'mailer.transport_factory.mailersend', Mailer_Bridge\Mailgun\Transport\Mailgun_Transport_Factory::class => 'mailer.transport_factory.mailgun', Mailer_Bridge\Mailjet\Transport\Mailjet_Transport_Factory::class => 'mailer.transport_factory.mailjet', Mailer_Bridge\Mailomat\Transport\Mailomat_Transport_Factory::class => 'mailer.transport_factory.mailomat', Mailer_Bridge\Mail_Pace\Transport\Mail_Pace_Transport_Factory::class => 'mailer.transport_factory.mailpace', Mailer_Bridge\Mailchimp\Transport\Mandrill_Transport_Factory::class => 'mailer.transport_factory.mailchimp', Mailer_Bridge\Microsoft_Graph\Transport\Microsoft_Graph_Transport_Factory::class => 'mailer.transport_factory.microsoftgraph', Mailer_Bridge\Postal\Transport\Postal_Transport_Factory::class => 'mailer.transport_factory.postal', Mailer_Bridge\Postmark\Transport\Postmark_Transport_Factory::class => 'mailer.transport_factory.postmark', Mailer_Bridge\Mailtrap\Transport\Mailtrap_Transport_Factory::class => 'mailer.transport_factory.mailtrap', Mailer_Bridge\Resend\Transport\Resend_Transport_Factory::class => 'mailer.transport_factory.resend', Mailer_Bridge\Scaleway\Transport\Scaleway_Transport_Factory::class => 'mailer.transport_factory.scaleway', Mailer_Bridge\Sendgrid\Transport\Sendgrid_Transport_Factory::class => 'mailer.transport_factory.sendgrid', Mailer_Bridge\Amazon\Transport\Ses_Transport_Factory::class => 'mailer.transport_factory.amazon', Mailer_Bridge\Sweego\Transport\Sweego_Transport_Factory::class => 'mailer.transport_factory.sweego'];
        foreach ($class_to_services as $class => $service) {
            $package = substr($service, \strlen('mailer.transport_factory.'));
            if (!Container_Builder::will_be_available(\sprintf('symfony/%s-mailer', 'gmail' === $package ? 'google' : $package), $class, ['symfony/framework-bundle', 'symfony/mailer'])) {
                $container->remove_definition($service);
            }
        }
        if ($webhook_enabled) {
            $webhook_request_parsers = [Mailer_Bridge\Aha_Send\Webhook\Aha_Send_Request_Parser::class => 'mailer.webhook.request_parser.ahasend', Mailer_Bridge\Brevo\Webhook\Brevo_Request_Parser::class => 'mailer.webhook.request_parser.brevo', Mailer_Bridge\Mailer_Send\Webhook\Mailer_Send_Request_Parser::class => 'mailer.webhook.request_parser.mailersend', Mailer_Bridge\Mailchimp\Webhook\Mailchimp_Request_Parser::class => 'mailer.webhook.request_parser.mailchimp', Mailer_Bridge\Mailgun\Webhook\Mailgun_Request_Parser::class => 'mailer.webhook.request_parser.mailgun', Mailer_Bridge\Mailjet\Webhook\Mailjet_Request_Parser::class => 'mailer.webhook.request_parser.mailjet', Mailer_Bridge\Mailomat\Webhook\Mailomat_Request_Parser::class => 'mailer.webhook.request_parser.mailomat', Mailer_Bridge\Postmark\Webhook\Postmark_Request_Parser::class => 'mailer.webhook.request_parser.postmark', Mailer_Bridge\Mailtrap\Webhook\Mailtrap_Request_Parser::class => 'mailer.webhook.request_parser.mailtrap', Mailer_Bridge\Resend\Webhook\Resend_Request_Parser::class => 'mailer.webhook.request_parser.resend', Mailer_Bridge\Sendgrid\Webhook\Sendgrid_Request_Parser::class => 'mailer.webhook.request_parser.sendgrid', Mailer_Bridge\Sweego\Webhook\Sweego_Request_Parser::class => 'mailer.webhook.request_parser.sweego'];
            foreach ($webhook_request_parsers as $class => $service) {
                $package = substr($service, \strlen('mailer.webhook.request_parser.'));
                if (!Container_Builder::will_be_available(\sprintf('symfony/%s-mailer', 'gmail' === $package ? 'google' : $package), $class, ['symfony/framework-bundle', 'symfony/mailer'])) {
                    $container->remove_definition($service);
                }
            }
        }
        $envelope_listener = $container->get_definition('mailer.envelope_listener');
        $envelope_listener->set_argument(0, $config['envelope']['sender'] ?? null);
        $envelope_listener->set_argument(1, $config['envelope']['recipients'] ?? null);
        $envelope_listener->set_argument(2, $config['envelope']['allowed_recipients'] ?? []);
        if ($config['headers']) {
            $headers = new Definition(Headers::class);
            foreach ($config['headers'] as $name => $data) {
                $value = $data['value'];
                if (\in_array(strtolower((string) $name), ['from', 'to', 'cc', 'bcc', 'reply-to'], true)) {
                    $value = (array) $value;
                }
                $headers->add_method_call('addHeader', [$name, $value]);
            }
            $message_listener = $container->get_definition('mailer.message_listener');
            $message_listener->set_argument(0, $headers);
        } else {
            $container->remove_definition('mailer.message_listener');
        }
        if ($config['dkim_signer']['enabled']) {
            $dkim_signer = $container->get_definition('mailer.dkim_signer');
            $dkim_signer->set_argument(0, $config['dkim_signer']['key']);
            $dkim_signer->set_argument(1, $config['dkim_signer']['domain']);
            $dkim_signer->set_argument(2, $config['dkim_signer']['select']);
            $dkim_signer->set_argument(3, $config['dkim_signer']['options']);
            $dkim_signer->set_argument(4, $config['dkim_signer']['passphrase']);
        } else {
            $container->remove_definition('mailer.dkim_signer');
            $container->remove_definition('mailer.dkim_signer.listener');
        }
        if ($config['smime_signer']['enabled']) {
            $smime_signer = $container->get_definition('mailer.smime_signer');
            $smime_signer->set_argument(0, $config['smime_signer']['certificate']);
            $smime_signer->set_argument(1, $config['smime_signer']['key']);
            $smime_signer->set_argument(2, $config['smime_signer']['passphrase']);
            $smime_signer->set_argument(3, $config['smime_signer']['extra_certificates']);
            $smime_signer->set_argument(4, $config['smime_signer']['sign_options']);
        } else {
            $container->remove_definition('mailer.smime_signer');
            $container->remove_definition('mailer.smime_signer.listener');
        }
        if ($config['smime_encrypter']['enabled']) {
            $container->set_alias('mailer.smime_encrypter.repository', $config['smime_encrypter']['repository']);
            $container->set_parameter('mailer.smime_encrypter.cipher', $config['smime_encrypter']['cipher']);
        } else {
            $container->remove_definition('mailer.smime_encrypter.listener');
        }
        if ($webhook_enabled) {
            $loader->load('mailer_webhook.php');
        }
    }
    private function register_notifier_configuration(array $config, Container_Builder $container, Php_File_Loader $loader, bool $webhook_enabled): void
    {
        if (!class_exists(Notifier::class)) {
            throw new LogicException('Notifier support cannot be enabled as the component is not installed. Try running "composer require symfony/notifier".');
        }
        $loader->load('notifier.php');
        $loader->load('notifier_transports.php');
        if ($config['chatter_transports']) {
            $container->get_definition('chatter.transports')->set_argument(0, $config['chatter_transports']);
        } else {
            $container->remove_definition('chatter');
            $container->remove_alias(Chatter_Interface::class);
        }
        if ($config['texter_transports']) {
            $container->get_definition('texter.transports')->set_argument(0, $config['texter_transports']);
        } else {
            $container->remove_definition('texter');
            $container->remove_alias(Texter_Interface::class);
        }
        if ($this->is_initialized_config_enabled('mailer')) {
            $sender = $container->get_definition('mailer.envelope_listener')->get_argument(0);
            $container->get_definition('notifier.channel.email')->set_argument(2, $sender);
        } else {
            $container->remove_definition('notifier.channel.email');
        }
        foreach (['texter', 'chatter', 'notifier.channel.chat', 'notifier.channel.email', 'notifier.channel.sms', 'notifier.channel.push', 'notifier.channel.desktop'] as $service_id) {
            if (!$container->has_definition($service_id)) {
                continue;
            }
            if (false === $message_bus = $config['message_bus']) {
                $container->get_definition($service_id)->replace_argument(1, null);
            } else {
                $container->get_definition($service_id)->replace_argument(1, $message_bus ? new Reference($message_bus) : new Reference('messenger.default_bus', Container_Interface::NULL_ON_INVALID_REFERENCE));
            }
        }
        if ($this->is_initialized_config_enabled('messenger')) {
            if ($config['notification_on_failed_messages']) {
                $container->get_definition('notifier.failed_message_listener')->add_tag('kernel.event_subscriber');
            }
            // as we have a bus, the channels don't need the transports
            $container->get_definition('notifier.channel.chat')->set_argument(0, null);
            if ($container->has_definition('notifier.channel.email')) {
                $container->get_definition('notifier.channel.email')->set_argument(0, null);
            }
            $container->get_definition('notifier.channel.sms')->set_argument(0, null);
            $container->get_definition('notifier.channel.push')->set_argument(0, null);
            $container->get_definition('notifier.channel.desktop')->set_argument(0, null);
        }
        $container->get_definition('notifier.channel_policy')->set_argument(0, $config['channel_policy']);
        $container->register_for_autoconfiguration(Notifier_Transport_Factory_Interface::class)->add_tag('chatter.transport_factory');
        $container->register_for_autoconfiguration(Notifier_Transport_Factory_Interface::class)->add_tag('texter.transport_factory');
        $class_to_services = [Notifier_Bridge\All_My_Sms\All_My_Sms_Transport_Factory::class => 'notifier.transport_factory.all-my-sms', Notifier_Bridge\Amazon_Sns\Amazon_Sns_Transport_Factory::class => 'notifier.transport_factory.amazon-sns', Notifier_Bridge\Bandwidth\Bandwidth_Transport_Factory::class => 'notifier.transport_factory.bandwidth', Notifier_Bridge\Bluesky\Bluesky_Transport_Factory::class => 'notifier.transport_factory.bluesky', Notifier_Bridge\Brevo\Brevo_Transport_Factory::class => 'notifier.transport_factory.brevo', Notifier_Bridge\Chatwork\Chatwork_Transport_Factory::class => 'notifier.transport_factory.chatwork', Notifier_Bridge\Clickatell\Clickatell_Transport_Factory::class => 'notifier.transport_factory.clickatell', Notifier_Bridge\Click_Send\Click_Send_Transport_Factory::class => 'notifier.transport_factory.click-send', Notifier_Bridge\Contact_Everyone\Contact_Everyone_Transport_Factory::class => 'notifier.transport_factory.contact-everyone', Notifier_Bridge\Discord\Discord_Transport_Factory::class => 'notifier.transport_factory.discord', Notifier_Bridge\Engagespot\Engagespot_Transport_Factory::class => 'notifier.transport_factory.engagespot', Notifier_Bridge\Esendex\Esendex_Transport_Factory::class => 'notifier.transport_factory.esendex', Notifier_Bridge\Expo\Expo_Transport_Factory::class => 'notifier.transport_factory.expo', Notifier_Bridge\Firebase\Firebase_Transport_Factory::class => 'notifier.transport_factory.firebase', Notifier_Bridge\Forty_Six_Elks\Forty_Six_Elks_Transport_Factory::class => 'notifier.transport_factory.forty-six-elks', Notifier_Bridge\Free_Mobile\Free_Mobile_Transport_Factory::class => 'notifier.transport_factory.free-mobile', Notifier_Bridge\Gateway_Api\Gateway_Api_Transport_Factory::class => 'notifier.transport_factory.gateway-api', Notifier_Bridge\Go_Ip\Go_Ip_Transport_Factory::class => 'notifier.transport_factory.go-ip', Notifier_Bridge\Google_Chat\Google_Chat_Transport_Factory::class => 'notifier.transport_factory.google-chat', Notifier_Bridge\Infobip\Infobip_Transport_Factory::class => 'notifier.transport_factory.infobip', Notifier_Bridge\Iqsms\Iqsms_Transport_Factory::class => 'notifier.transport_factory.iqsms', Notifier_Bridge\Isendpro\Isendpro_Transport_Factory::class => 'notifier.transport_factory.isendpro', Notifier_Bridge\Joli_Notif\Joli_Notif_Transport_Factory::class => 'notifier.transport_factory.joli-notif', Notifier_Bridge\Kaz_Info_Teh\Kaz_Info_Teh_Transport_Factory::class => 'notifier.transport_factory.kaz-info-teh', Notifier_Bridge\Light_Sms\Light_Sms_Transport_Factory::class => 'notifier.transport_factory.light-sms', Notifier_Bridge\Line_Bot\Line_Bot_Transport_Factory::class => 'notifier.transport_factory.line-bot', Notifier_Bridge\Line_Notify\Line_Notify_Transport_Factory::class => 'notifier.transport_factory.line-notify', Notifier_Bridge\Linked_In\Linked_In_Transport_Factory::class => 'notifier.transport_factory.linked-in', Notifier_Bridge\Lox24\Lox24transport_Factory::class => 'notifier.transport_factory.lox24', Notifier_Bridge\Mailjet\Mailjet_Transport_Factory::class => 'notifier.transport_factory.mailjet', Notifier_Bridge\Mastodon\Mastodon_Transport_Factory::class => 'notifier.transport_factory.mastodon', Notifier_Bridge\Matrix\Matrix_Transport_Factory::class => 'notifier.transport_factory.matrix', Notifier_Bridge\Mattermost\Mattermost_Transport_Factory::class => 'notifier.transport_factory.mattermost', Notifier_Bridge\Mercure\Mercure_Transport_Factory::class => 'notifier.transport_factory.mercure', Notifier_Bridge\Message_Bird\Message_Bird_Transport_Factory::class => 'notifier.transport_factory.message-bird', Notifier_Bridge\Message_Media\Message_Media_Transport_Factory::class => 'notifier.transport_factory.message-media', Notifier_Bridge\Microsoft_Teams\Microsoft_Teams_Transport_Factory::class => 'notifier.transport_factory.microsoft-teams', Notifier_Bridge\Mobyt\Mobyt_Transport_Factory::class => 'notifier.transport_factory.mobyt', Notifier_Bridge\Novu\Novu_Transport_Factory::class => 'notifier.transport_factory.novu', Notifier_Bridge\Ntfy\Ntfy_Transport_Factory::class => 'notifier.transport_factory.ntfy', Notifier_Bridge\Octopush\Octopush_Transport_Factory::class => 'notifier.transport_factory.octopush', Notifier_Bridge\One_Signal\One_Signal_Transport_Factory::class => 'notifier.transport_factory.one-signal', Notifier_Bridge\Orange_Sms\Orange_Sms_Transport_Factory::class => 'notifier.transport_factory.orange-sms', Notifier_Bridge\Ovh_Cloud\Ovh_Cloud_Transport_Factory::class => 'notifier.transport_factory.ovh-cloud', Notifier_Bridge\Pager_Duty\Pager_Duty_Transport_Factory::class => 'notifier.transport_factory.pager-duty', Notifier_Bridge\Plivo\Plivo_Transport_Factory::class => 'notifier.transport_factory.plivo', Notifier_Bridge\Primotexto\Primotexto_Transport_Factory::class => 'notifier.transport_factory.primotexto', Notifier_Bridge\Pushover\Pushover_Transport_Factory::class => 'notifier.transport_factory.pushover', Notifier_Bridge\Pushy\Pushy_Transport_Factory::class => 'notifier.transport_factory.pushy', Notifier_Bridge\Redlink\Redlink_Transport_Factory::class => 'notifier.transport_factory.redlink', Notifier_Bridge\Ring_Central\Ring_Central_Transport_Factory::class => 'notifier.transport_factory.ring-central', Notifier_Bridge\Rocket_Chat\Rocket_Chat_Transport_Factory::class => 'notifier.transport_factory.rocket-chat', Notifier_Bridge\Sendberry\Sendberry_Transport_Factory::class => 'notifier.transport_factory.sendberry', Notifier_Bridge\Sipgate\Sipgate_Transport_Factory::class => 'notifier.transport_factory.sipgate', Notifier_Bridge\Simple_Textin\Simple_Textin_Transport_Factory::class => 'notifier.transport_factory.simple-textin', Notifier_Bridge\Sevenio\Seven_Io_Transport_Factory::class => 'notifier.transport_factory.sevenio', Notifier_Bridge\Sinch\Sinch_Transport_Factory::class => 'notifier.transport_factory.sinch', Notifier_Bridge\Slack\Slack_Transport_Factory::class => 'notifier.transport_factory.slack', Notifier_Bridge\Smsapi\Smsapi_Transport_Factory::class => 'notifier.transport_factory.smsapi', Notifier_Bridge\Sms_Biuras\Sms_Biuras_Transport_Factory::class => 'notifier.transport_factory.sms-biuras', Notifier_Bridge\Smsbox\Smsbox_Transport_Factory::class => 'notifier.transport_factory.smsbox', Notifier_Bridge\Smsc\Smsc_Transport_Factory::class => 'notifier.transport_factory.smsc', Notifier_Bridge\Sms_Factor\Sms_Factor_Transport_Factory::class => 'notifier.transport_factory.sms-factor', Notifier_Bridge\Smsmode\Smsmode_Transport_Factory::class => 'notifier.transport_factory.smsmode', Notifier_Bridge\Sms_Sluzba\Sms_Sluzba_Transport_Factory::class => 'notifier.transport_factory.sms-sluzba', Notifier_Bridge\Smsense\Smsense_Transport_Factory::class => 'notifier.transport_factory.smsense', Notifier_Bridge\Spot_Hit\Spot_Hit_Transport_Factory::class => 'notifier.transport_factory.spot-hit', Notifier_Bridge\Sweego\Sweego_Transport_Factory::class => 'notifier.transport_factory.sweego', Notifier_Bridge\Telegram\Telegram_Transport_Factory::class => 'notifier.transport_factory.telegram', Notifier_Bridge\Telnyx\Telnyx_Transport_Factory::class => 'notifier.transport_factory.telnyx', Notifier_Bridge\Termii\Termii_Transport_Factory::class => 'notifier.transport_factory.termii', Notifier_Bridge\Turbo_Sms\Turbo_Sms_Transport_Factory::class => 'notifier.transport_factory.turbo-sms', Notifier_Bridge\Twilio\Twilio_Transport_Factory::class => 'notifier.transport_factory.twilio', Notifier_Bridge\Twitter\Twitter_Transport_Factory::class => 'notifier.transport_factory.twitter', Notifier_Bridge\Unifonic\Unifonic_Transport_Factory::class => 'notifier.transport_factory.unifonic', Notifier_Bridge\Vonage\Vonage_Transport_Factory::class => 'notifier.transport_factory.vonage', Notifier_Bridge\Yunpian\Yunpian_Transport_Factory::class => 'notifier.transport_factory.yunpian', Notifier_Bridge\Zendesk\Zendesk_Transport_Factory::class => 'notifier.transport_factory.zendesk', Notifier_Bridge\Zulip\Zulip_Transport_Factory::class => 'notifier.transport_factory.zulip'];
        $parent_packages = ['symfony/framework-bundle', 'symfony/notifier'];
        foreach ($class_to_services as $class => $service) {
            $package = substr($service, \strlen('notifier.transport_factory.'));
            if (!Container_Builder::will_be_available(\sprintf('symfony/%s-notifier', $package), $class, $parent_packages)) {
                $container->remove_definition($service);
            }
        }
        if (Container_Builder::will_be_available('symfony/mercure-notifier', Notifier_Bridge\Mercure\Mercure_Transport_Factory::class, $parent_packages) && Container_Builder::will_be_available('symfony/mercure-bundle', Mercure_Bundle::class, $parent_packages) && \in_array(Mercure_Bundle::class, $container->get_parameter('kernel.bundles'), true)) {
            $container->get_definition($class_to_services[Notifier_Bridge\Mercure\Mercure_Transport_Factory::class])->replace_argument(0, new Reference(Hub_Registry::class))->replace_argument(1, new Reference('event_dispatcher', Container_Builder::NULL_ON_INVALID_REFERENCE))->add_argument(new Reference('http_client', Container_Builder::NULL_ON_INVALID_REFERENCE));
        } elseif (Container_Builder::will_be_available('symfony/mercure-notifier', Notifier_Bridge\Mercure\Mercure_Transport_Factory::class, $parent_packages)) {
            $container->remove_definition($class_to_services[Notifier_Bridge\Mercure\Mercure_Transport_Factory::class]);
        }
        // don't use ContainerBuilder::willBeAvailable() as these are not needed in production
        if (class_exists(Fake_Chat_Transport_Factory::class)) {
            $container->get_definition('notifier.transport_factory.fake-chat')->replace_argument(0, new Reference('mailer', Container_Builder::NULL_ON_INVALID_REFERENCE))->replace_argument(1, new Reference('logger', Container_Builder::NULL_ON_INVALID_REFERENCE))->add_argument(new Reference('event_dispatcher', Container_Builder::NULL_ON_INVALID_REFERENCE))->add_argument(new Reference('http_client', Container_Builder::NULL_ON_INVALID_REFERENCE));
        } else {
            $container->remove_definition('notifier.transport_factory.fake-chat');
        }
        // don't use ContainerBuilder::willBeAvailable() as these are not needed in production
        if (class_exists(Fake_Sms_Transport_Factory::class)) {
            $container->get_definition('notifier.transport_factory.fake-sms')->replace_argument(0, new Reference('mailer', Container_Builder::NULL_ON_INVALID_REFERENCE))->replace_argument(1, new Reference('logger', Container_Builder::NULL_ON_INVALID_REFERENCE))->add_argument(new Reference('event_dispatcher', Container_Builder::NULL_ON_INVALID_REFERENCE))->add_argument(new Reference('http_client', Container_Builder::NULL_ON_INVALID_REFERENCE));
        } else {
            $container->remove_definition('notifier.transport_factory.fake-sms');
        }
        if (Container_Builder::will_be_available('symfony/bluesky-notifier', Notifier_Bridge\Bluesky\Bluesky_Transport_Factory::class, ['symfony/framework-bundle', 'symfony/notifier'])) {
            $container->get_definition($class_to_services[Notifier_Bridge\Bluesky\Bluesky_Transport_Factory::class])->add_argument(new Reference('logger'))->add_argument(new Reference('clock', Container_Builder::NULL_ON_INVALID_REFERENCE));
        }
        if (isset($config['admin_recipients'])) {
            $notifier = $container->get_definition('notifier');
            foreach ($config['admin_recipients'] as $i => $recipient) {
                $id = 'notifier.admin_recipient.' . $i;
                $container->set_definition($id, new Definition(Recipient::class, [$recipient['email'], $recipient['phone']]));
                $notifier->add_method_call('addAdminRecipient', [new Reference($id)]);
            }
        }
        if ($webhook_enabled) {
            $loader->load('notifier_webhook.php');
            $webhook_request_parsers = [Notifier_Bridge\Lox24\Webhook\Lox24request_Parser::class => 'notifier.webhook.request_parser.lox24', Notifier_Bridge\Smsbox\Webhook\Smsbox_Request_Parser::class => 'notifier.webhook.request_parser.smsbox', Notifier_Bridge\Sweego\Webhook\Sweego_Request_Parser::class => 'notifier.webhook.request_parser.sweego', Notifier_Bridge\Twilio\Webhook\Twilio_Request_Parser::class => 'notifier.webhook.request_parser.twilio', Notifier_Bridge\Vonage\Webhook\Vonage_Request_Parser::class => 'notifier.webhook.request_parser.vonage'];
            foreach ($webhook_request_parsers as $class => $service) {
                $package = substr($service, \strlen('notifier.webhook.request_parser.'));
                if (!Container_Builder::will_be_available(\sprintf('symfony/%s-notifier', $package), $class, ['symfony/framework-bundle', 'symfony/notifier'])) {
                    $container->remove_definition($service);
                }
            }
        }
    }
    private function register_webhook_configuration(array $config, Container_Builder $container, Php_File_Loader $loader, bool $serializer_enabled): void
    {
        if (!class_exists(Webhook_Controller::class)) {
            throw new LogicException('Webhook support cannot be enabled as the component is not installed. Try running "composer require symfony/webhook".');
        }
        $loader->load('webhook.php');
        $parsers = [];
        foreach ($config['routing'] as $type => $cfg) {
            $parsers[$type] = ['parser' => new Reference($cfg['service']), 'secret' => $cfg['secret']];
        }
        $controller = $container->get_definition('webhook.controller');
        $controller->replace_argument(0, $parsers);
        $controller->replace_argument(1, new Reference($config['message_bus']));
        $json_body_configurator = $container->get_definition('webhook.body_configurator.json');
        $json_body_configurator->replace_argument(0, new Reference($serializer_enabled ? 'webhook.payload_serializer.serializer' : 'webhook.payload_serializer.json'));
    }
    private function register_remote_event_configuration(Php_File_Loader $loader): void
    {
        if (!class_exists(Remote_Event::class)) {
            throw new LogicException('RemoteEvent support cannot be enabled as the component is not installed. Try running "composer require symfony/remote-event".');
        }
        $loader->load('remote_event.php');
    }
    private function register_rate_limiter_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        $loader->load('rate_limiter.php');
        $limiters = [];
        $compound_limiters = [];
        foreach ($config['limiters'] as $name => $limiter_config) {
            if ('compound' === $limiter_config['policy']) {
                $compound_limiters[$name] = $limiter_config;
                continue;
            }
            unset($limiter_config['limiters']);
            $limiters[] = $name;
            // default configuration (when used by other DI extensions)
            $limiter_config += ['lock_factory' => 'lock.factory', 'cache_pool' => 'cache.rate_limiter'];
            $limiter = $container->set_definition($limiter_id = 'limiter.' . $name, new Child_Definition('limiter'))->add_tag('rate_limiter', ['name' => $name]);
            if ('auto' === $limiter_config['lock_factory']) {
                $limiter_config['lock_factory'] = $this->is_initialized_config_enabled('lock') ? 'lock.factory' : null;
            }
            if (null !== $limiter_config['lock_factory']) {
                if (!interface_exists(Lock_Interface::class)) {
                    throw new LogicException(\sprintf('Rate limiter "%s" requires the Lock component to be installed. Try running "composer require symfony/lock".', $name));
                }
                if (!$this->is_initialized_config_enabled('lock')) {
                    throw new LogicException(\sprintf('Rate limiter "%s" requires the Lock component to be configured.', $name));
                }
                $limiter->replace_argument(2, new Reference($limiter_config['lock_factory']));
            }
            unset($limiter_config['lock_factory']);
            if (null === $storage_id = $limiter_config['storage_service'] ?? null) {
                $container->register($storage_id = 'limiter.storage.' . $name, Cache_Storage::class)->add_argument(new Reference($limiter_config['cache_pool']));
            }
            $limiter->replace_argument(1, new Reference($storage_id));
            unset($limiter_config['storage_service'], $limiter_config['cache_pool']);
            $limiter_config['id'] = $name;
            $limiter->replace_argument(0, $limiter_config);
            $container->register_alias_for_argument($limiter_id, Rate_Limiter_Factory_Interface::class, $name . '.limiter', $name);
        }
        foreach ($compound_limiters as $name => $limiter_config) {
            if (!$limiter_config['limiters']) {
                throw new LogicException(\sprintf('Compound rate limiter "%s" requires at least one sub-limiter.', $name));
            }
            if (array_diff($limiter_config['limiters'], $limiters)) {
                throw new LogicException(\sprintf('Compound rate limiter "%s" requires at least one sub-limiter to be configured.', $name));
            }
            $container->register($limiter_id = 'limiter.' . $name, Compound_Rate_Limiter_Factory::class)->add_tag('rate_limiter', ['name' => $name])->add_argument(new Iterator_Argument(array_map(static fn(string $name): \Symfony\Component\Dependency_Injection\Reference => new Reference('limiter.' . $name), $limiter_config['limiters'])));
            $container->register_alias_for_argument($limiter_id, Rate_Limiter_Factory_Interface::class, $name . '.limiter', $name);
        }
    }
    private function register_uid_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        $loader->load('uid.php');
        $container->get_definition('uuid.factory')->set_arguments([$config['default_uuid_version'], $config['time_based_uuid_version'], $config['name_based_uuid_version'], Uuid_V4::class, $config['time_based_uuid_node'] ?? null, $config['name_based_uuid_namespace'] ?? null]);
        if (isset($config['name_based_uuid_namespace'])) {
            $container->get_definition('name_based_uuid.factory')->set_arguments([$config['name_based_uuid_namespace']]);
        }
    }
    private function register_html_sanitizer_configuration(array $config, Container_Builder $container, Php_File_Loader $loader): void
    {
        $loader->load('html_sanitizer.php');
        foreach ($config['sanitizers'] as $sanitizer_name => $sanitizer_config) {
            $config_id = 'html_sanitizer.config.' . $sanitizer_name;
            $def = $container->register($config_id, Html_Sanitizer_Config::class);
            // Base
            if ($sanitizer_config['default_action'] ?? false) {
                $def->add_method_call('defaultAction', [Html_Sanitizer_Action::from($sanitizer_config['default_action'])], true);
            }
            if ($sanitizer_config['allow_safe_elements']) {
                $def->add_method_call('allowSafeElements', [], true);
            }
            if ($sanitizer_config['allow_static_elements']) {
                $def->add_method_call('allowStaticElements', [], true);
            }
            // Configures elements
            foreach ($sanitizer_config['allow_elements'] as $element => $attributes) {
                $def->add_method_call('allowElement', [$element, $attributes], true);
            }
            foreach ($sanitizer_config['block_elements'] as $element) {
                $def->add_method_call('blockElement', [$element], true);
            }
            foreach ($sanitizer_config['drop_elements'] as $element) {
                $def->add_method_call('dropElement', [$element], true);
            }
            // Configures attributes
            foreach ($sanitizer_config['allow_attributes'] as $attribute => $elements) {
                $def->add_method_call('allowAttribute', [$attribute, $elements], true);
            }
            foreach ($sanitizer_config['drop_attributes'] as $attribute => $elements) {
                $def->add_method_call('dropAttribute', [$attribute, $elements], true);
            }
            // Force attributes
            foreach ($sanitizer_config['force_attributes'] as $element => $attributes) {
                foreach ($attributes as $attr_name => $attr_value) {
                    $def->add_method_call('forceAttribute', [$element, $attr_name, $attr_value], true);
                }
            }
            // Settings
            $def->add_method_call('forceHttpsUrls', [$sanitizer_config['force_https_urls']], true);
            if ($sanitizer_config['allowed_link_schemes']) {
                $def->add_method_call('allowLinkSchemes', [$sanitizer_config['allowed_link_schemes']], true);
            }
            $def->add_method_call('allowLinkHosts', [$sanitizer_config['allowed_link_hosts']], true);
            $def->add_method_call('allowRelativeLinks', [$sanitizer_config['allow_relative_links']], true);
            if ($sanitizer_config['allowed_media_schemes']) {
                $def->add_method_call('allowMediaSchemes', [$sanitizer_config['allowed_media_schemes']], true);
            }
            $def->add_method_call('allowMediaHosts', [$sanitizer_config['allowed_media_hosts']], true);
            $def->add_method_call('allowRelativeMedias', [$sanitizer_config['allow_relative_medias']], true);
            // Custom attribute sanitizers
            foreach ($sanitizer_config['with_attribute_sanitizers'] as $service_name) {
                $def->add_method_call('withAttributeSanitizer', [new Reference($service_name)], true);
            }
            foreach ($sanitizer_config['without_attribute_sanitizers'] as $service_name) {
                $def->add_method_call('withoutAttributeSanitizer', [new Reference($service_name)], true);
            }
            if ($sanitizer_config['max_input_length']) {
                $def->add_method_call('withMaxInputLength', [$sanitizer_config['max_input_length']], true);
            }
            // Create the sanitizer and link its config
            $sanitizer_id = 'html_sanitizer.sanitizer.' . $sanitizer_name;
            $container->register($sanitizer_id, Html_Sanitizer::class)->add_tag('html_sanitizer', ['sanitizer' => $sanitizer_name])->add_argument(new Reference($config_id));
            if ('default' !== $sanitizer_name) {
                $container->register_alias_for_argument($sanitizer_id, Html_Sanitizer_Interface::class, $sanitizer_name);
            }
        }
    }
    protected function is_config_enabled(Container_Builder $container, array $config): bool
    {
        throw new \LogicException('To prevent using outdated configuration, you must use the "readConfigEnabled" method instead.');
    }
    private function is_initialized_config_enabled(string $path): bool
    {
        if (isset($this->configs_enabled[$path])) {
            return $this->configs_enabled[$path];
        }
        throw new LogicException(\sprintf('Can not read config enabled at "%s" because it has not been initialized.', $path));
    }
    private function read_config_enabled(string $path, Container_Builder $container, array $config): bool
    {
        return $this->configs_enabled[$path] ??= parent::is_config_enabled($container, $config);
    }
    private function write_config_enabled(string $path, bool $value, array &$config): void
    {
        if (isset($this->configs_enabled[$path])) {
            throw new LogicException('Can not change config enabled because it has already been read.');
        }
        $this->configs_enabled[$path] = $value;
        $config['enabled'] = $value;
    }
    private function get_public_directory(Container_Builder $container): string
    {
        $project_dir = $container->get_parameter('kernel.project_dir');
        $default_public_dir = $project_dir . '/public';
        $composer_file_path = $project_dir . '/composer.json';
        if (!file_exists($composer_file_path)) {
            return $default_public_dir;
        }
        $container->add_resource(new File_Resource($composer_file_path));
        $composer_config = json_decode((new Filesystem())->read_file($composer_file_path), true, flags: \JSON_THROW_ON_ERROR);
        return isset($composer_config['extra']['public-dir']) ? $project_dir . '/' . $composer_config['extra']['public-dir'] : $default_public_dir;
    }
}