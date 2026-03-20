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

use Doctrine\DBAL\Connection;
use Psr\Log\Log_Level;
use Seld\Json_Lint\Json_Parser;
use Symfony\Bundle\Full_Stack;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset_Mapper\Asset_Mapper;
use Symfony\Component\Asset_Mapper\Compressor\Compressor_Interface;
use Symfony\Component\Cache\Adapter\Doctrine_Adapter;
use Symfony\Component\Config\Definition\Builder\Array_Node_Definition;
use Symfony\Component\Config\Definition\Builder\Node_Builder;
use Symfony\Component\Config\Definition\Builder\Tree_Builder;
use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Config\Definition\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Finder\Glob;
use Symfony\Component\Form\Form;
use Symfony\Component\Html_Sanitizer\Html_Sanitizer_Interface;
use Symfony\Component\Http_Client\Http_Client;
use Symfony\Component\Http_Foundation\Cookie;
use Symfony\Component\Http_Foundation\Ip_Utils;
use Symfony\Component\Json_Streamer\Stream_Writer_Interface;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\Semaphore_Store;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\Message_Bus_Interface;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Property_Access\Property_Accessor;
use Symfony\Component\Property_Info\Property_Info_Extractor_Interface;
use Symfony\Component\Rate_Limiter\Policy\Token_Bucket_Limiter;
use Symfony\Component\Remote_Event\Remote_Event;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Semaphore\Semaphore;
use Symfony\Component\Serializer\Encoder\Json_Decode;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Type_Info\Type;
use Symfony\Component\Uid\Factory\Uuid_Factory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Webhook\Controller\Webhook_Controller;
use Symfony\Component\Web_Link\Http_Header_Serializer;
use Symfony\Component\Workflow\Validator\Definition_Validator_Interface;
use Symfony\Component\Workflow\Workflow_Events;
/**
 * FrameworkExtension configuration structure.
 */
class Configuration implements Configuration_Interface
{
    /**
     * @param bool $debug Whether debugging is enabled or not
     */
    public function __construct(private readonly bool $debug)
    {
    }
    /**
     * Generates the configuration tree builder.
     */
    public function get_config_tree_builder(): Tree_Builder
    {
        $tree_builder = new Tree_Builder('framework');
        $root_node = $tree_builder->get_root_node();
        $root_node->doc_url('https://symfony.com/doc/{version:major}.{version:minor}/reference/configuration/framework.html', 'symfony/framework-bundle')->before_normalization()->if_array()->then(static function (array $v): array {
            if (isset($v['templating']) && class_exists(Package::class)) {
                $v['assets'] ??= [];
            }
            return $v;
        })->end()->children()->scalar_node('secret')->end()->boolean_node('http_method_override')->info("Set true to enable support for the '_method' request parameter to determine the intended HTTP method on POST requests.")->default_false()->end()->array_node('allowed_http_method_override')->info('Sets the list of HTTP methods that can be overridden. Set to null to allow all methods to be overridden (default). Set to an empty array to disallow overrides entirely. Otherwise, provide the list of uppercased method names that are allowed.')->string_prototype()->end()->default_null()->validate()->if_true(static fn($v): array => array_intersect($v, ['GET', 'HEAD', 'CONNECT', 'TRACE']))->then_invalid('The HTTP methods "GET", "HEAD", "CONNECT", and "TRACE" cannot be overridden.')->end()->end()->scalar_node('trust_x_sendfile_type_header')->info('Set true to enable support for xsendfile in binary file responses.')->default_value('%env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)%')->end()->scalar_node('ide')->default_value($this->debug ? '%env(default::SYMFONY_IDE)%' : null)->end()->boolean_node('test')->end()->scalar_node('default_locale')->default_value('en')->end()->boolean_node('set_locale_from_accept_language')->info('Whether to use the Accept-Language HTTP header to set the Request locale (only when the "_locale" request attribute is not passed).')->default_false()->end()->boolean_node('set_content_language_from_locale')->info('Whether to set the Content-Language HTTP header on the Response using the Request locale.')->default_false()->end()->array_node('enabled_locales', 'enabled_locale')->info('Defines the possible locales for the application. This list is used for generating translations files, but also to restrict which locales are allowed when it is set from Accept-Language header (using "set_locale_from_accept_language").')->prototype('scalar')->end()->end()->array_node('trusted_hosts')->before_normalization()->if_string()->then(static fn($v): array => $v ? [$v] : [])->end()->prototype('scalar')->end()->default_value(['%env(default::SYMFONY_TRUSTED_HOSTS)%'])->end()->variable_node('trusted_proxies')->before_normalization()->if_true(static fn($v): bool => 'private_ranges' === $v || 'PRIVATE_SUBNETS' === $v)->then(static fn(): array => Ip_Utils::PRIVATE_SUBNETS)->end()->default_value(['%env(default::SYMFONY_TRUSTED_PROXIES)%'])->end()->array_node('trusted_headers', 'trusted_header')->perform_no_deep_merging()->before_normalization()->if_string()->then(static fn($v): array => $v ? [$v] : [])->end()->prototype('scalar')->end()->default_value(['%env(default::SYMFONY_TRUSTED_HEADERS)%'])->end()->scalar_node('error_controller')->default_value('error_controller')->end()->boolean_node('handle_all_throwables')->info('HttpKernel will handle all kinds of \Throwable.')->default_true()->end()->end();
        $will_be_available = static function (string $package, string $class, ?string $parent_package = null): bool {
            $parent_packages = (array) $parent_package;
            $parent_packages[] = 'symfony/framework-bundle';
            return Container_Builder::will_be_available($package, $class, $parent_packages);
        };
        $enable_if_standalone = static fn(string $package, string $class): string => !class_exists(Full_Stack::class) && $will_be_available($package, $class) ? 'canBeDisabled' : 'canBeEnabled';
        $this->add_csrf_section($root_node);
        $this->add_form_section($root_node, $enable_if_standalone);
        $this->add_http_cache_section($root_node);
        $this->add_esi_section($root_node);
        $this->add_ssi_section($root_node);
        $this->add_fragments_section($root_node);
        $this->add_profiler_section($root_node);
        $this->add_workflow_section($root_node);
        $this->add_router_section($root_node);
        $this->add_session_section($root_node);
        $this->add_request_section($root_node);
        $this->add_assets_section($root_node, $enable_if_standalone);
        $this->add_asset_mapper_section($root_node, $enable_if_standalone);
        $this->add_translator_section($root_node, $enable_if_standalone);
        $this->add_validation_section($root_node, $enable_if_standalone);
        $this->add_serializer_section($root_node, $enable_if_standalone);
        $this->add_property_access_section($root_node, $will_be_available);
        $this->add_type_info_section($root_node, $enable_if_standalone);
        $this->add_property_info_section($root_node, $enable_if_standalone);
        $this->add_cache_section($root_node, $will_be_available);
        $this->add_php_errors_section($root_node);
        $this->add_exceptions_section($root_node);
        $this->add_web_link_section($root_node, $enable_if_standalone);
        $this->add_lock_section($root_node, $enable_if_standalone);
        $this->add_semaphore_section($root_node, $enable_if_standalone);
        $this->add_messenger_section($root_node, $enable_if_standalone);
        $this->add_scheduler_section($root_node, $enable_if_standalone);
        $this->add_robots_index_section($root_node);
        $this->add_http_client_section($root_node, $enable_if_standalone);
        $this->add_mailer_section($root_node, $enable_if_standalone);
        $this->add_secrets_section($root_node);
        $this->add_notifier_section($root_node, $enable_if_standalone);
        $this->add_rate_limiter_section($root_node, $enable_if_standalone);
        $this->add_uid_section($root_node, $enable_if_standalone);
        $this->add_html_sanitizer_section($root_node, $enable_if_standalone);
        $this->add_webhook_section($root_node, $enable_if_standalone);
        $this->add_remote_event_section($root_node, $enable_if_standalone);
        $this->add_json_streamer_section($root_node, $enable_if_standalone);
        return $tree_builder;
    }
    private function add_secrets_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('secrets')->can_be_disabled()->children()->scalar_node('vault_directory')->default_value('%kernel.project_dir%/config/secrets/%kernel.runtime_environment%')->cannot_be_empty()->end()->scalar_node('local_dotenv_file')->default_value('%kernel.project_dir%/.env.%kernel.environment%.local')->end()->scalar_node('decryption_env_var')->default_value('base64:default::SYMFONY_DECRYPTION_SECRET')->end()->end()->end()->end();
    }
    private function add_csrf_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('csrf_protection')->treat_false_like(['enabled' => false])->treat_true_like(['enabled' => true])->treat_null_like(['enabled' => true])->add_defaults_if_not_set()->children()->scalar_node('enabled')->default_null()->end()->array_node('stateless_token_ids', 'stateless_token_id')->scalar_prototype()->end()->info('Enable headers/cookies-based CSRF validation for the listed token ids.')->end()->scalar_node('check_header')->default_false()->info('Whether to check the CSRF token in a header in addition to a cookie when using stateless protection.')->end()->scalar_node('cookie_name')->default_value('csrf-token')->info('The name of the cookie to use when using stateless protection.')->end()->end()->end()->end();
    }
    private function add_form_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('form')->info('Form configuration')->{$enable_if_standalone('symfony/form', Form::class)}()->children()->array_node('csrf_protection')->treat_false_like(['enabled' => false])->treat_true_like(['enabled' => true])->treat_null_like(['enabled' => true])->add_defaults_if_not_set()->children()->scalar_node('enabled')->default_null()->end()->scalar_node('token_id')->default_null()->end()->scalar_node('field_name')->default_value('_token')->end()->array_node('field_attr')->perform_no_deep_merging()->normalize_keys(false)->use_attribute_as_key('name')->scalar_prototype()->end()->default_value(['data-controller' => 'csrf-protection'])->end()->end()->end()->end()->end()->end();
    }
    private function add_http_cache_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('http_cache')->info('HTTP cache configuration')->can_be_enabled()->children()->boolean_node('debug')->default_value('%kernel.debug%')->end()->enum_node('trace_level')->values(['none', 'short', 'full'])->end()->scalar_node('trace_header')->end()->integer_node('default_ttl')->end()->array_node('private_headers', 'private_header')->perform_no_deep_merging()->scalar_prototype()->end()->end()->array_node('skip_response_headers', 'skip_response_header')->perform_no_deep_merging()->scalar_prototype()->end()->end()->boolean_node('allow_reload')->end()->boolean_node('allow_revalidate')->end()->integer_node('stale_while_revalidate')->end()->integer_node('stale_if_error')->end()->boolean_node('terminate_on_cache_hit')->set_deprecated('symfony/framework-bundle', '8.1', 'Setting the "%path%.%node%" configuration option is deprecated. It will be removed in version 9.0.')->end()->end()->end()->end();
    }
    private function add_esi_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('esi')->info('ESI configuration')->can_be_enabled()->end()->end();
    }
    private function add_ssi_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('ssi')->info('SSI configuration')->can_be_enabled()->end()->end();
    }
    private function add_fragments_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('fragments')->info('Fragments configuration')->can_be_enabled()->children()->scalar_node('hinclude_default_template')->default_null()->end()->scalar_node('path')->default_value('/_fragment')->end()->end()->end()->end();
    }
    private function add_profiler_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('profiler')->info('Profiler configuration')->can_be_enabled()->children()->boolean_node('collect')->default_true()->end()->scalar_node('collect_parameter')->default_null()->info('The name of the parameter to use to enable or disable collection on a per request basis.')->end()->boolean_node('only_exceptions')->default_false()->end()->boolean_node('only_main_requests')->default_false()->end()->scalar_node('dsn')->default_value('file:%kernel.cache_dir%/profiler')->end()->enum_node('collect_serializer_data')->values([true])->default_true()->set_deprecated('symfony/framework-bundle', '8.1', 'Setting the "%path%.%node%" configuration option is deprecated. It will be removed in version 9.0.')->end()->end()->end()->end();
    }
    private function add_workflow_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('workflows', 'workflow')->can_be_enabled()->before_normalization()->if_array()->then(static function (array $v): array {
            if (true === $v['enabled']) {
                $workflows = $v;
                unset($workflows['enabled']);
                if (1 === \count($workflows) && isset($workflows[0]['enabled']) && 1 === \count($workflows[0])) {
                    $workflows = [];
                }
                if (1 === \count($workflows) && isset($workflows['workflows']) && !array_is_list($workflows['workflows']) && array_diff_key($workflows['workflows'], ['audit_trail' => 1, 'type' => 1, 'marking_store' => 1, 'supports' => 1, 'support_strategy' => 1, 'initial_marking' => 1, 'places' => 1, 'transitions' => 1])) {
                    $workflows = $workflows['workflows'];
                }
                foreach ($workflows as $key => $workflow) {
                    if (isset($workflow['enabled']) && false === $workflow['enabled']) {
                        throw new LogicException(\sprintf('Cannot disable a single workflow. Remove the configuration for the workflow "%s" instead.', $key));
                    }
                    unset($workflows[$key]['enabled']);
                }
                $v = ['enabled' => true, 'workflows' => $workflows];
            }
            return $v;
        })->end()->children()->array_node('workflows', 'workflow')->use_attribute_as_key('name')->prototype('array')->children()->array_node('audit_trail')->can_be_enabled()->end()->enum_node('type')->values(['workflow', 'state_machine'])->default_value('state_machine')->end()->array_node('marking_store')->children()->enum_node('type')->values(['method'])->end()->scalar_node('property')->cannot_be_empty()->end()->scalar_node('service')->cannot_be_empty()->end()->end()->end()->array_node('supports', 'support')->accept_and_wrap(['string'])->prototype('scalar')->cannot_be_empty()->validate()->if_true(static fn($v): bool => !class_exists($v) && !interface_exists($v, false))->then_invalid('The supported class or interface "%s" does not exist.')->end()->end()->end()->array_node('definition_validators', 'definition_validator')->prototype('scalar')->cannot_be_empty()->validate()->if_true(static fn($v): bool => !class_exists($v))->then_invalid('The validation class %s does not exist.')->end()->validate()->if_true(static fn($v): bool => !is_a($v, Definition_Validator_Interface::class, true))->then_invalid(\sprintf('The validation class %%s is not an instance of "%s".', Definition_Validator_Interface::class))->end()->validate()->if_true(static fn($v): bool => 1 <= (new \ReflectionClass($v))->get_constructor()?->get_number_of_required_parameters())->then_invalid('The %s validation class constructor must not have any arguments.')->end()->end()->end()->scalar_node('support_strategy')->cannot_be_empty()->end()->array_node('initial_marking')->accept_and_wrap(['backed-enum', 'string'])->default_value([])->before_normalization()->if_array()->then(static function ($markings): array {
            $normalized_markings = [];
            foreach ($markings as $marking) {
                $normalized_markings[] = $marking instanceof \Backed_Enum ? $marking->value : $marking;
            }
            return $normalized_markings;
        })->end()->prototype('scalar')->end()->end()->array_node('events_to_dispatch', 'event_to_dispatch')->default_null()->string_prototype()->end()->validate()->if_true(static function ($v): bool {
            if (!class_exists(Workflow_Events::class)) {
                return false;
            }
            foreach ($v as $value) {
                if (!\in_array($value, Workflow_Events::ALIASES, true)) {
                    return true;
                }
            }
            return false;
        })->then_invalid('The value must be "null" or an array of workflow events (like ["workflow.enter"]).')->end()->info('Select which Transition events should be dispatched for this Workflow.')->example(['workflow.enter', 'workflow.transition'])->end()->array_node('places', 'place')->before_normalization()->if_string()->then(static function ($places): array {
            if (2 !== \count($places = explode('::', $places, 2))) {
                throw new Invalid_Configuration_Exception('The "places" option must be a "FQCN::glob" pattern in workflow configuration.');
            }
            [$class, $pattern] = $places;
            if (!class_exists($class) && !interface_exists($class, false)) {
                throw new Invalid_Configuration_Exception(\sprintf('The "places" option must be a "FQCN::glob" pattern in workflow configuration, but class "%s" is not found.', $class));
            }
            $places = [];
            $regex = Glob::to_regex($pattern, false);
            foreach ((new \ReflectionClass($class))->get_constants() as $name => $value) {
                if (preg_match($regex, $name)) {
                    $places[] = $value;
                }
            }
            return $places ?: throw new Invalid_Configuration_Exception(\sprintf('No places found for pattern "%s::%s" in workflow configuration.', $class, $pattern));
        })->end()->before_normalization()->if_array()->then(static function ($places): array {
            $normalized_places = [];
            foreach ($places as $key => $value) {
                if ($value instanceof \Backed_Enum) {
                    $value = ['name' => $value->value];
                } elseif (!\is_array($value)) {
                    $value = ['name' => $value];
                }
                $value['name'] ??= $key;
                $normalized_places[] = $value;
            }
            return $normalized_places;
        })->end()->prototype('array')->children()->scalar_node('name')->is_required()->cannot_be_empty()->end()->array_node('metadata')->use_attribute_as_key('key')->normalize_keys(false)->default_value([])->example(['color' => 'blue', 'description' => 'Workflow to manage article.'])->prototype('variable')->end()->end()->end()->end()->end()->array_node('transitions', 'transition')->before_normalization()->if_array()->then(static function ($transitions): array {
            $normalized_transitions = [];
            foreach ($transitions as $key => $transition) {
                if (\is_array($transition)) {
                    if (\is_string($key = $transition['key'] ?? $key)) {
                        $transition['name'] ??= $key;
                    }
                    if (!($transition['name'] ?? false)) {
                        throw new Invalid_Configuration_Exception('The "name" option is required for each transition in workflow configuration.');
                    }
                    unset($transition['key']);
                }
                $normalized_transitions[$key] = $transition;
            }
            return $normalized_transitions;
        })->end()->is_required()->requires_at_least_one_element()->prototype('array')->children()->string_node('name')->is_required()->cannot_be_empty()->end()->string_node('guard')->cannot_be_empty()->info('An expression to block the transition.')->example('is_fully_authenticated() and is_granted(\'ROLE_JOURNALIST\') and subject.getTitle() == \'My first article\'')->end()->array_node('from')->perform_no_deep_merging()->accept_and_wrap(['backed-enum', 'string'])->before_normalization()->if_array()->then($workflow_normalize_arcs = static function (array $arcs): array {
            // Fix XML parsing, when only one arc is defined
            if (\array_key_exists('value', $arcs) && \array_key_exists('weight', $arcs)) {
                $arcs = [['place' => $arcs['value'], 'weight' => $arcs['weight']]];
            } elseif (\array_key_exists('place', $arcs)) {
                $arcs = [$arcs];
            }
            $normalized_arcs = [];
            foreach ($arcs as $arc) {
                if (\is_string($arc) || $arc instanceof \Backed_Enum) {
                    $arc = ['place' => $arc];
                } elseif (!\is_array($arc)) {
                    throw new Invalid_Configuration_Exception('The "from" arcs must be a list of strings or arrays in workflow configuration.');
                } elseif (\array_key_exists('value', $arc) && \array_key_exists('weight', $arc)) {
                    // Fix XML parsing
                    $arc = ['place' => $arc['value'], 'weight' => $arc['weight']];
                }
                if (($arc['place'] ?? null) instanceof \Backed_Enum) {
                    $arc['place'] = $arc['place']->value;
                }
                $normalized_arcs[] = $arc;
            }
            return $normalized_arcs;
        })->end()->requires_at_least_one_element()->prototype('array')->children()->string_node('place')->is_required()->cannot_be_empty()->end()->integer_node('weight')->default_value(1)->min(1)->end()->end()->end()->end()->array_node('to')->perform_no_deep_merging()->accept_and_wrap(['backed-enum', 'string'])->before_normalization()->if_array()->then($workflow_normalize_arcs)->end()->requires_at_least_one_element()->prototype('array')->children()->string_node('place')->is_required()->cannot_be_empty()->end()->integer_node('weight')->default_value(1)->min(1)->end()->end()->end()->end()->integer_node('weight')->default_value(1)->validate()->if_true(static fn($v): bool => $v < 1)->then_invalid('The weight must be greater than 0.')->end()->end()->array_node('metadata')->use_attribute_as_key('key')->normalize_keys(false)->default_value([])->example(['color' => 'blue', 'description' => 'Workflow to manage article.'])->prototype('variable')->end()->end()->end()->end()->end()->array_node('metadata')->use_attribute_as_key('key')->normalize_keys(false)->default_value([])->example(['color' => 'blue', 'description' => 'Workflow to manage article.'])->prototype('variable')->end()->end()->end()->validate()->if_true(static fn($v): bool => $v['supports'] && isset($v['support_strategy']))->then_invalid('"supports" and "support_strategy" cannot be used together.')->end()->validate()->if_true(static fn($v): bool => !$v['supports'] && !isset($v['support_strategy']))->then_invalid('"supports" or "support_strategy" should be configured.')->end()->before_normalization()->if_array()->then(static function (array $values): array {
            // Special case to deal with XML when the user wants an empty array
            if (\array_key_exists('event_to_dispatch', $values) && null === $values['event_to_dispatch']) {
                $values['events_to_dispatch'] = [];
                unset($values['event_to_dispatch']);
            }
            return $values;
        })->end()->end()->end()->end()->end()->end();
    }
    private function add_router_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('router')->info('Router configuration')->can_be_enabled()->children()->scalar_node('resource')->is_required()->end()->scalar_node('type')->end()->scalar_node('default_uri')->info('The default URI used to generate URLs in a non-HTTP context.')->default_null()->end()->scalar_node('http_port')->default_value(80)->end()->scalar_node('https_port')->default_value(443)->end()->scalar_node('strict_requirements')->info("set to true to throw an exception when a parameter does not match the requirements\n" . "set to false to disable exceptions when a parameter does not match the requirements (and return null instead)\n" . "set to null to disable parameter checks against requirements\n" . "'true' is the preferred configuration in development mode, while 'false' or 'null' might be preferred in production")->default_true()->end()->boolean_node('utf8')->default_true()->end()->end()->end()->end();
    }
    private function add_session_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('session')->info('Session configuration')->can_be_enabled()->children()->scalar_node('storage_factory_id')->default_value('session.storage.factory.native')->end()->scalar_node('handler_id')->info('Defaults to using the native session handler, or to the native *file* session handler if "save_path" is not null.')->end()->scalar_node('name')->validate()->if_true(static function ($v): bool {
            parse_str($v, $parsed);
            return implode('&', array_keys($parsed)) !== (string) $v;
        })->then_invalid('Session name %s contains illegal character(s)')->end()->end()->scalar_node('cookie_lifetime')->end()->scalar_node('cookie_path')->end()->scalar_node('cookie_domain')->end()->enum_node('cookie_secure')->values([true, false, 'auto'])->default_value('auto')->end()->boolean_node('cookie_httponly')->default_true()->end()->enum_node('cookie_samesite')->values([null, Cookie::SAMESITE_LAX, Cookie::SAMESITE_STRICT, Cookie::SAMESITE_NONE])->default_value('lax')->end()->boolean_node('use_cookies')->end()->scalar_node('gc_divisor')->end()->scalar_node('gc_probability')->end()->scalar_node('gc_maxlifetime')->end()->scalar_node('save_path')->info('Defaults to "%kernel.cache_dir%/sessions" if the "handler_id" option is not null.')->end()->integer_node('metadata_update_threshold')->default_value(0)->info('Seconds to wait between 2 session metadata updates.')->end()->end()->end()->end();
    }
    private function add_request_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('request')->info('Request configuration')->can_be_enabled()->children()->array_node('formats', 'format')->use_attribute_as_key('name')->prototype('array')->accept_and_wrap(['string'])->before_normalization()->if_array()->then(static fn($v): array => (array) ($v['mime_type'] ?? $v))->end()->prototype('scalar')->end()->end()->end()->end()->end()->end();
    }
    private function add_assets_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('assets')->info('Assets configuration')->{$enable_if_standalone('symfony/asset', Package::class)}()->children()->boolean_node('strict_mode')->info('Throw an exception if an entry is missing from the manifest.json.')->default_false()->end()->scalar_node('version_strategy')->default_null()->end()->scalar_node('version')->default_null()->end()->scalar_node('version_format')->default_value('%%s?%%s')->end()->scalar_node('json_manifest_path')->default_null()->end()->scalar_node('base_path')->default_value('')->end()->array_node('base_urls', 'base_url')->requires_at_least_one_element()->accept_and_wrap(['string'])->prototype('scalar')->end()->end()->end()->validate()->if_true(static fn($v): bool => isset($v['version_strategy']) && isset($v['version']))->then_invalid('You cannot use both "version_strategy" and "version" at the same time under "assets".')->end()->validate()->if_true(static fn($v): bool => isset($v['version_strategy']) && isset($v['json_manifest_path']))->then_invalid('You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets".')->end()->validate()->if_true(static fn($v): bool => isset($v['version']) && isset($v['json_manifest_path']))->then_invalid('You cannot use both "version" and "json_manifest_path" at the same time under "assets".')->end()->children()->array_node('packages', 'package')->normalize_keys(false)->use_attribute_as_key('name')->prototype('array')->children()->boolean_node('strict_mode')->info('Throw an exception if an entry is missing from the manifest.json.')->default_false()->end()->scalar_node('version_strategy')->default_null()->end()->scalar_node('version')->before_normalization()->if_string()->then(static fn($v) => '' === $v ? null : $v)->end()->end()->scalar_node('version_format')->default_null()->end()->scalar_node('json_manifest_path')->default_null()->end()->scalar_node('base_path')->default_value('')->end()->array_node('base_urls', 'base_url')->requires_at_least_one_element()->accept_and_wrap(['string'])->prototype('scalar')->end()->end()->end()->validate()->if_true(static fn($v): bool => isset($v['version_strategy']) && isset($v['version']))->then_invalid('You cannot use both "version_strategy" and "version" at the same time under "assets" packages.')->end()->validate()->if_true(static fn($v): bool => isset($v['version_strategy']) && isset($v['json_manifest_path']))->then_invalid('You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets" packages.')->end()->validate()->if_true(static fn($v): bool => isset($v['version']) && isset($v['json_manifest_path']))->then_invalid('You cannot use both "version" and "json_manifest_path" at the same time under "assets" packages.')->end()->end()->end()->end()->end()->end();
    }
    private function add_asset_mapper_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('asset_mapper')->info('Asset Mapper configuration')->{$enable_if_standalone('symfony/asset-mapper', Asset_Mapper::class)}()->children()->array_node('paths', 'path')->info('Directories that hold assets that should be in the mapper. Can be a simple array of an array of ["path/to/assets": "namespace"].')->example(['assets/'])->normalize_keys(false)->use_attribute_as_key('namespace')->accept_and_wrap(['string'])->before_normalization()->if_array()->then(static function ($v): array {
            $result = [];
            foreach ($v as $key => $item) {
                // "dir" => "namespace"
                if (\is_string($key)) {
                    $result[$key] = $item;
                    continue;
                }
                if (\is_array($item)) {
                    // $item = ["namespace" => "the/namespace", "value" => "the/dir"]
                    $result[$item['value']] = $item['namespace'] ?? '';
                } else {
                    // $item = "the/dir"
                    $result[$item] = '';
                }
            }
            return $result;
        })->end()->prototype('scalar')->end()->end()->array_node('excluded_patterns', 'excluded_pattern')->info('Array of glob patterns of asset file paths that should not be in the asset mapper.')->prototype('scalar')->end()->example(['*/assets/build/*', '*/*_.scss'])->end()->boolean_node('exclude_dotfiles')->info('If true, any files starting with "." will be excluded from the asset mapper.')->default_true()->end()->boolean_node('server')->info('If true, a "dev server" will return the assets from the public directory (true in "debug" mode only by default).')->default_value($this->debug)->end()->scalar_node('public_prefix')->info('The public path where the assets will be written to (and served from when "server" is true).')->default_value('/assets/')->end()->enum_node('missing_import_mode')->values(['strict', 'warn', 'ignore'])->info('Behavior if an asset cannot be found when imported from JavaScript or CSS files - e.g. "import \'./non-existent.js\'". "strict" means an exception is thrown, "warn" means a warning is logged, "ignore" means the import is left as-is.')->default_value('warn')->end()->array_node('extensions', 'extension')->info('Key-value pair of file extensions set to their mime type.')->normalize_keys(false)->use_attribute_as_key('extension')->example(['.zip' => 'application/zip'])->prototype('scalar')->end()->end()->scalar_node('importmap_path')->info('The path of the importmap.php file.')->default_value('%kernel.project_dir%/importmap.php')->end()->scalar_node('importmap_polyfill')->info('The importmap name that will be used to load the polyfill. Set to false to disable.')->validate()->if_true()->then_invalid('Invalid "importmap_polyfill" value. Must be either an importmap name or false.')->end()->default_value('es-module-shims')->end()->array_node('importmap_script_attributes', 'importmap_script_attribute')->info('Key-value pair of attributes to add to script tags output for the importmap.')->normalize_keys(false)->use_attribute_as_key('key')->example(['data-turbo-track' => 'reload'])->prototype('scalar')->end()->end()->scalar_node('vendor_dir')->info('The directory to store JavaScript vendors.')->default_value('%kernel.project_dir%/assets/vendor')->end()->array_node('precompress')->info('Precompress assets with Brotli, Zstandard and gzip.')->can_be_enabled()->children()->array_node('formats', 'format')->info('Array of formats to enable. "brotli", "zstandard" and "gzip" are supported. Defaults to all formats supported by the system. The entire list must be provided.')->prototype('scalar')->end()->perform_no_deep_merging()->validate()->if_true(static fn($v): array => array_diff($v, ['brotli', 'zstandard', 'gzip']))->then_invalid('Unsupported format: "brotli", "zstandard" and "gzip" are supported.')->end()->end()->array_node('extensions', 'extension')->info('Array of extensions to compress. The entire list must be provided, no merging occurs.')->prototype('scalar')->end()->perform_no_deep_merging()->default_value(interface_exists(Compressor_Interface::class) ? Compressor_Interface::DEFAULT_EXTENSIONS : [])->end()->end()->end()->end()->end()->end();
    }
    private function add_translator_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('translator')->info('Translator configuration')->{$enable_if_standalone('symfony/translation', Translator::class)}()->children()->array_node('fallbacks', 'fallback')->info('Defaults to the value of "default_locale".')->accept_and_wrap(['string'])->prototype('scalar')->end()->default_value([])->end()->boolean_node('logging')->default_false()->end()->scalar_node('formatter')->default_value('translator.formatter.default')->end()->scalar_node('cache_dir')->default_value('%kernel.cache_dir%/translations')->end()->scalar_node('default_path')->info('The default path used to load translations.')->default_value('%kernel.project_dir%/translations')->end()->array_node('paths', 'path')->prototype('scalar')->end()->end()->array_node('pseudo_localization')->can_be_enabled()->children()->boolean_node('accents')->default_true()->end()->float_node('expansion_factor')->min(1.0)->default_value(1.0)->end()->boolean_node('brackets')->default_true()->end()->boolean_node('parse_html')->default_false()->end()->array_node('localizable_html_attributes', 'localizable_html_attribute')->prototype('scalar')->end()->end()->end()->end()->array_node('providers', 'provider')->info('Translation providers you can read/write your translations from.')->use_attribute_as_key('name')->prototype('array')->children()->scalar_node('dsn')->end()->array_node('domains', 'domain')->prototype('scalar')->end()->default_value([])->end()->array_node('locales', 'locale')->prototype('scalar')->end()->default_value([])->info('If not set, all locales listed under framework.enabled_locales are used.')->end()->end()->end()->default_value([])->end()->array_node('globals', 'global')->info('Global parameters.')->example(['app_version' => 3.14])->normalize_keys(false)->use_attribute_as_key('name')->array_prototype()->accept_and_wrap(['string'], 'value')->children()->variable_node('value')->end()->string_node('message')->end()->array_node('parameters', 'parameter')->normalize_keys(false)->use_attribute_as_key('name')->scalar_prototype()->end()->end()->string_node('domain')->end()->end()->validate()->if_true(static fn($v): bool => !(isset($v['value']) xor isset($v['message'])))->then_invalid('The "globals" parameter should be either a string or an array with a "value" or a "message" key')->end()->end()->end()->end()->end()->end();
    }
    private function add_validation_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('validation')->info('Validation configuration')->{$enable_if_standalone('symfony/validator', Validation::class)}()->children()->boolean_node('enable_attributes')->{class_exists(Full_Stack::class) ? 'defaultFalse' : 'defaultTrue'}()->end()->array_node('static_method')->accept_and_wrap(['string'])->default_value(['loadValidatorMetadata'])->prototype('scalar')->end()->treat_false_like([])->end()->scalar_node('translation_domain')->default_value('validators')->end()->enum_node('email_validation_mode')->values(['html5', 'html5-allow-no-tld', 'strict'])->default_value('html5')->end()->array_node('mapping')->add_defaults_if_not_set()->children()->array_node('paths', 'path')->prototype('scalar')->end()->end()->end()->end()->array_node('not_compromised_password')->can_be_disabled('When disabled, compromised passwords will be accepted as valid.')->children()->scalar_node('endpoint')->default_null()->info('API endpoint for the NotCompromisedPassword Validator.')->end()->end()->end()->boolean_node('disable_translation')->default_false()->end()->array_node('auto_mapping')->info('A collection of namespaces for which auto-mapping will be enabled by default, or null to opt-in with the EnableAutoMapping constraint.')->example(['App\Entity\\' => [], 'App\WithSpecificLoaders\\' => ['validator.property_info_loader']])->use_attribute_as_key('namespace')->normalize_keys(false)->before_normalization()->if_array()->then(static function (array $values): array {
            foreach ($values as $k => $v) {
                if (isset($v['service'])) {
                    continue;
                }
                if (isset($v['namespace'])) {
                    $values[$k]['services'] = [];
                    continue;
                }
                if (!\is_array($v)) {
                    $values[$v]['services'] = [];
                    unset($values[$k]);
                    continue;
                }
                $tmp = $v;
                unset($values[$k]);
                $values[$k]['services'] = $tmp;
            }
            return $values;
        })->end()->array_prototype()->children()->array_node('services', 'service')->prototype('scalar')->end()->end()->end()->end()->end()->end()->end()->end();
    }
    private function add_serializer_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $default_context_node = fn() => (new Node_Builder())->array_node('default_context')->use_attribute_as_key('key')->normalize_keys(false)->validate()->if_true(fn(): bool => $this->debug && class_exists(Json_Parser::class))->then(static fn(array $v): array => $v + [Json_Decode::DETAILED_ERROR_MESSAGES => true])->end()->default_value([])->prototype('variable')->end();
        $root_node->children()->array_node('serializer')->info('Serializer configuration')->{$enable_if_standalone('symfony/serializer', Serializer::class)}()->children()->boolean_node('enable_attributes')->{class_exists(Full_Stack::class) ? 'defaultFalse' : 'defaultTrue'}()->end()->scalar_node('name_converter')->end()->scalar_node('circular_reference_handler')->end()->scalar_node('max_depth_handler')->end()->array_node('mapping')->add_defaults_if_not_set()->children()->array_node('paths', 'path')->prototype('scalar')->end()->end()->end()->end()->append($default_context_node())->array_node('named_serializers', 'named_serializer')->use_attribute_as_key('name')->array_prototype()->children()->scalar_node('name_converter')->end()->append($default_context_node())->boolean_node('include_built_in_normalizers')->info('Whether to include the built-in normalizers')->default_true()->end()->boolean_node('include_built_in_encoders')->info('Whether to include the built-in encoders')->default_true()->end()->end()->end()->validate()->if_true(static fn($v): bool => isset($v['default']))->then_invalid('"default" is a reserved name.')->end()->end()->end()->validate()->if_true(fn($v): bool => $this->debug && class_exists(Json_Parser::class) && !isset($v['default_context'][Json_Decode::DETAILED_ERROR_MESSAGES]))->then(static function (array $v): array {
            $v['default_context'][Json_Decode::DETAILED_ERROR_MESSAGES] = true;
            return $v;
        })->end()->end()->end();
    }
    private function add_property_access_section(Array_Node_Definition $root_node, callable $will_be_available): void
    {
        $root_node->children()->array_node('property_access')->add_defaults_if_not_set()->info('Property access configuration')->{$will_be_available('symfony/property-access', Property_Accessor::class) ? 'canBeDisabled' : 'canBeEnabled'}()->children()->boolean_node('magic_call')->default_false()->end()->boolean_node('magic_get')->default_true()->end()->boolean_node('magic_set')->default_true()->end()->boolean_node('throw_exception_on_invalid_index')->default_false()->end()->boolean_node('throw_exception_on_invalid_property_path')->default_true()->end()->end()->end()->end();
    }
    private function add_property_info_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('property_info')->info('Property info configuration')->{$enable_if_standalone('symfony/property-info', Property_Info_Extractor_Interface::class)}()->children()->boolean_node('with_constructor_extractor')->info('Registers the constructor extractor.')->default_true()->end()->end()->end()->end();
    }
    private function add_type_info_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('type_info')->info('Type info configuration')->{$enable_if_standalone('symfony/type-info', Type::class)}()->add_defaults_if_not_set()->children()->array_node('aliases', 'alias')->info('Additional type aliases to be used during type context creation.')->default_value([])->normalize_keys(false)->use_attribute_as_key('name')->scalar_prototype()->end()->end()->end()->end()->end();
    }
    private function add_cache_section(Array_Node_Definition $root_node, callable $will_be_available): void
    {
        $root_node->children()->array_node('cache')->info('Cache configuration')->add_defaults_if_not_set()->children()->scalar_node('prefix_seed')->info('Used to namespace cache keys when using several apps with the same shared backend.')->default_value('_%kernel.project_dir%.%kernel.container_class%')->example('my-application-name/%kernel.environment%')->end()->scalar_node('app')->info('App related cache pools configuration.')->default_value('cache.adapter.filesystem')->end()->scalar_node('system')->info('System related cache pools configuration.')->default_value('cache.adapter.system')->end()->scalar_node('directory')->default_value('%kernel.share_dir%/pools/app')->end()->scalar_node('default_psr6_provider')->end()->scalar_node('default_redis_provider')->default_value('redis://localhost')->end()->scalar_node('default_valkey_provider')->default_value('valkey://localhost')->end()->scalar_node('default_memcached_provider')->default_value('memcached://localhost')->end()->scalar_node('default_doctrine_dbal_provider')->default_value('database_connection')->end()->scalar_node('default_pdo_provider')->default_value($will_be_available('doctrine/dbal', Connection::class) && class_exists(Doctrine_Adapter::class) ? 'database_connection' : null)->end()->array_node('pools', 'pool')->use_attribute_as_key('name')->prototype('array')->validate()->if_true(static fn($v): bool => isset($v['provider']) && 1 < \count($v['adapters']))->then_invalid('Pool cannot have a "provider" while more than one adapter is defined')->end()->children()->array_node('adapters', 'adapter')->perform_no_deep_merging()->info('One or more adapters to chain for creating the pool, defaults to "cache.app".')->accept_and_wrap(['string'])->before_normalization()->if_array()->then(static function ($values): array {
            if ([0] === array_keys($values) && \is_array($values[0])) {
                return $values[0];
            }
            $adapters = [];
            foreach ($values as $k => $v) {
                if (\is_int($k) && \is_string($v)) {
                    $adapters[] = $v;
                } elseif (!\is_array($v)) {
                    $adapters[$k] = $v;
                } elseif (isset($v['provider'])) {
                    $adapters[$v['provider']] = $v['name'] ?? $v;
                } else {
                    $adapters[] = $v['name'] ?? $v;
                }
            }
            return $adapters;
        })->end()->prototype('scalar')->end()->end()->scalar_node('tags')->default_null()->end()->boolean_node('public')->default_false()->end()->scalar_node('default_lifetime')->info('Default lifetime of the pool.')->example('"300" for 5 minutes expressed in seconds, "PT5M" for five minutes expressed as ISO 8601 time interval, or "5 minutes" as a date expression')->end()->scalar_node('provider')->info('Overwrite the setting from the default provider for this adapter.')->end()->scalar_node('early_expiration_message_bus')->example('"messenger.default_bus" to send early expiration events to the default Messenger bus.')->end()->scalar_node('clearer')->end()->scalar_node('marshaller')->info('The marshaller service to use for this pool.')->end()->end()->end()->validate()->if_true(static fn($v): bool => isset($v['cache.app']) || isset($v['cache.system']))->then_invalid('"cache.app" and "cache.system" are reserved names')->end()->end()->end()->end()->end();
    }
    private function add_php_errors_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->array_node('php_errors')->info('PHP errors handling configuration')->add_defaults_if_not_set()->children()->variable_node('log')->info('Use the application logger instead of the PHP logger for logging PHP errors.')->example('"true" to use the default configuration: log all errors. "false" to disable. An integer bit field of E_* constants, or an array mapping E_* constants to log levels.')->treat_null_like($this->debug)->default_true()->before_normalization()->if_array()->then(static function (array $v): array {
            if (!($v[0]['type'] ?? false)) {
                return $v;
            }
            // Fix XML normalization
            $ret = [];
            foreach ($v as ['type' => $type, 'logLevel' => $log_level]) {
                $ret[$type] = $log_level;
            }
            return $ret;
        })->end()->validate()->if_true(static fn($v): bool => !(\is_int($v) || \is_bool($v) || \is_array($v)))->then_invalid('The "php_errors.log" parameter should be either an integer, a boolean, or an array')->end()->end()->boolean_node('throw')->info('Throw PHP errors as \ErrorException instances.')->default_value($this->debug)->treat_null_like($this->debug)->end()->end()->end()->end();
    }
    private function add_exceptions_section(Array_Node_Definition $root_node): void
    {
        $log_levels = (new \ReflectionClass(Log_Level::class))->get_constants();
        $root_node->children()->array_node('exceptions', 'exception')->info('Exception handling configuration')->use_attribute_as_key('class')->prototype('array')->children()->scalar_node('log_level')->info('The level of log message. Null to let Symfony decide.')->validate()->if_true(static fn($v): bool => null !== $v && !\in_array($v, $log_levels, true))->then_invalid(\sprintf('The log level is not valid. Pick one among "%s".', implode('", "', $log_levels)))->end()->default_null()->end()->scalar_node('status_code')->info('The status code of the response. Null or 0 to let Symfony decide.')->before_normalization()->if_true(static fn($v): bool => 0 === $v)->then(static fn($v): null => null)->end()->validate()->if_true(static fn($v): bool => null !== $v && ($v < 100 || $v > 599))->then_invalid('The status code is not valid. Pick a value between 100 and 599.')->end()->default_null()->end()->scalar_node('log_channel')->info('The channel of log message. Null to let Symfony decide.')->default_null()->end()->end()->end()->end()->end();
    }
    private function add_lock_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('lock')->info('Lock configuration')->accept_and_wrap(['string'], 'resources')->{$enable_if_standalone('symfony/lock', Lock::class)}()->before_normalization()->if_array()->then(static function (array $v): array {
            if (!isset($v['resources']) && !isset($v['resource'])) {
                $v = ['resources' => $v];
                if (\array_key_exists('enabled', $v['resources'])) {
                    $v['enabled'] = $v['resources']['enabled'];
                    unset($v['resources']['enabled']);
                }
            }
            return $v;
        })->end()->add_defaults_if_not_set()->validate()->if_true(static fn($v): bool => $v['enabled'] && !$v['resources'])->then_invalid('At least one resource must be defined.')->end()->children()->array_node('resources', 'resource')->normalize_keys(false)->use_attribute_as_key('name')->default_value(['default' => [class_exists(Semaphore_Store::class) && Semaphore_Store::is_supported() ? 'semaphore' : 'flock']])->accept_and_wrap(['string'], 'default')->before_normalization()->if_array()->then(static function ($v) {
            if (!array_is_list($v)) {
                return $v;
            }
            $resources = [];
            foreach ($v as $resource) {
                $resources[] = \is_array($resource) && isset($resource['name']) ? [$resource['name'] => $resource['value']] : ['default' => $resource];
            }
            return array_merge_recursive([], ...$resources);
        })->end()->prototype('array')->perform_no_deep_merging()->accept_and_wrap(['string'])->before_normalization()->if_null()->then(static fn(): array => ['null'])->end()->prototype('scalar')->end()->end()->end()->end()->end()->end();
    }
    private function add_semaphore_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('semaphore')->info('Semaphore configuration')->accept_and_wrap(['string'], 'resources')->{$enable_if_standalone('symfony/semaphore', Semaphore::class)}()->before_normalization()->if_array()->then(static function (array $v): array {
            if (!isset($v['resources']) && !isset($v['resource'])) {
                $v = ['resources' => $v];
                if (\array_key_exists('enabled', $v['resources'])) {
                    $v['enabled'] = $v['resources']['enabled'];
                    unset($v['resources']['enabled']);
                }
            }
            return $v;
        })->end()->add_defaults_if_not_set()->children()->array_node('resources', 'resource')->normalize_keys(false)->use_attribute_as_key('name')->requires_at_least_one_element()->accept_and_wrap(['string'], 'default')->before_normalization()->if_array()->then(static function ($v) {
            if (!array_is_list($v)) {
                return $v;
            }
            $resources = [];
            foreach ($v as $resource) {
                $resources[] = \is_array($resource) && isset($resource['name']) ? [$resource['name'] => $resource['value']] : ['default' => $resource];
            }
            return array_merge_recursive([], ...$resources);
        })->end()->prototype('scalar')->end()->end()->end()->end()->end();
    }
    private function add_web_link_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('web_link')->info('Web links configuration')->{$enable_if_standalone('symfony/weblink', Http_Header_Serializer::class)}()->end()->end();
    }
    private function add_messenger_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('messenger')->info('Messenger configuration')->{$enable_if_standalone('symfony/messenger', Message_Bus_Interface::class)}()->validate()->if_true(static fn($v): bool => isset($v['buses']) && \count($v['buses']) > 1 && null === $v['default_bus'])->then_invalid('You must specify the "default_bus" if you define more than one bus.')->end()->validate()->if_true(static fn($v): bool => isset($v['buses']) && null !== $v['default_bus'] && !isset($v['buses'][$v['default_bus']]))->then(static fn($v) => throw new Invalid_Configuration_Exception(\sprintf('The specified default bus "%s" is not configured. Available buses are "%s".', $v['default_bus'], implode('", "', array_keys($v['buses'])))))->end()->children()->array_node('routing')->normalize_keys(false)->use_attribute_as_key('message_class')->before_normalization()->if_array()->then(static function ($config): array {
            $new_config = [];
            foreach ($config as $k => $v) {
                $new_config[$k] = ['senders' => $v['senders'] ?? (\is_array($v) ? array_values($v) : [$v])];
            }
            return $new_config;
        })->end()->prototype('array')->accept_and_wrap(['string'], 'senders')->perform_no_deep_merging()->children()->array_node('senders')->requires_at_least_one_element()->prototype('scalar')->end()->end()->end()->end()->end()->array_node('serializer')->add_defaults_if_not_set()->children()->scalar_node('default_serializer')->default_value('messenger.transport.native_php_serializer')->info('Service id to use as the default serializer for the transports.')->end()->array_node('symfony_serializer')->add_defaults_if_not_set()->children()->scalar_node('format')->default_value('json')->info('Serialization format for the messenger.transport.symfony_serializer service (which is not the serializer used by default).')->end()->array_node('context')->normalize_keys(false)->use_attribute_as_key('name')->default_value([])->info('Context array for the messenger.transport.symfony_serializer service (which is not the serializer used by default).')->prototype('variable')->end()->end()->end()->end()->end()->end()->array_node('transports', 'transport')->normalize_keys(false)->use_attribute_as_key('name')->array_prototype()->accept_and_wrap(['string'], 'dsn')->children()->scalar_node('dsn')->end()->scalar_node('serializer')->default_null()->info('Service id of a custom serializer to use.')->end()->array_node('options', 'option')->use_attribute_as_key('key')->normalize_keys(false)->default_value([])->prototype('variable')->end()->end()->scalar_node('failure_transport')->default_null()->info('Transport name to send failed messages to (after all retries have failed).')->end()->array_node('retry_strategy')->add_defaults_if_not_set()->accept_and_wrap(['string'], 'service')->before_normalization()->if_array()->then(static function (array $v): array {
            if (isset($v['service']) && (isset($v['max_retries']) || isset($v['delay']) || isset($v['multiplier']) || isset($v['max_delay']))) {
                throw new \InvalidArgumentException('The "service" cannot be used along with the other "retry_strategy" options.');
            }
            return $v;
        })->end()->children()->scalar_node('service')->default_null()->info('Service id to override the retry strategy entirely.')->end()->integer_node('max_retries')->default_value(3)->min(0)->end()->integer_node('delay')->default_value(1000)->min(0)->info('Time in ms to delay (or the initial value when multiplier is used).')->end()->float_node('multiplier')->default_value(2)->min(1)->info('If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries)).')->end()->integer_node('max_delay')->default_value(0)->min(0)->info('Max time in ms that a retry should ever be delayed (0 = infinite).')->end()->float_node('jitter')->default_value(0.1)->min(0)->max(1)->info('Randomness to apply to the delay (between 0 and 1).')->end()->end()->end()->scalar_node('rate_limiter')->default_null()->info('Rate limiter name to use when processing messages.')->end()->end()->end()->end()->scalar_node('failure_transport')->default_null()->info('Transport name to send failed messages to (after all retries have failed).')->end()->array_node('stop_worker_on_signals', 'stop_worker_on_signal')->default_value([])->info('A list of signals that should stop the worker; defaults to SIGTERM and SIGINT.')->accept_and_wrap(['int', 'string'])->before_normalization()->if_array()->then(static fn($signals) => array_map(static function ($v) {
            if (\is_string($v) && str_starts_with($v, 'SIG') && \array_key_exists($v, get_defined_constants(true)['pcntl'])) {
                return \constant($v);
            }
            if (!\is_int($v)) {
                throw new Invalid_Configuration_Exception('The "stop_worker_on_signals" option must be an array of pcntl signals in messenger configuration.');
            }
            return $v;
        }, $signals))->end()->scalar_prototype()->end()->end()->scalar_node('default_bus')->default_null()->end()->array_node('buses', 'bus')->default_value(['messenger.bus.default' => ['default_middleware' => ['enabled' => true, 'allow_no_handlers' => false, 'allow_no_senders' => true], 'middleware' => []]])->normalize_keys(false)->use_attribute_as_key('name')->array_prototype()->add_defaults_if_not_set()->children()->array_node('default_middleware')->before_normalization()->if_string()->then(static fn($v): array => ['enabled' => 'allow_no_handlers' === $v, 'allow_no_handlers' => 'allow_no_handlers' === $v])->end()->before_normalization()->if_true()->then(static fn(): array => ['enabled' => true])->end()->before_normalization()->if_false()->then(static fn(): array => ['enabled' => false])->end()->can_be_disabled()->children()->boolean_node('allow_no_handlers')->default_false()->end()->boolean_node('allow_no_senders')->default_true()->end()->end()->end()->array_node('middleware')->perform_no_deep_merging()->accept_and_wrap(['string'])->before_normalization()->if_array()->then(static fn($v) => \is_string(key($v)) ? [$v] : $v)->end()->default_value([])->array_prototype()->accept_and_wrap(['string'], 'id')->before_normalization()->if_array()->then(static function (array $middleware): array {
            if (isset($middleware['id'])) {
                return $middleware;
            }
            if (1 < \count($middleware)) {
                throw new \InvalidArgumentException('Invalid middleware at path "framework.messenger": a map with a single factory id as key and its arguments as value was expected, ' . json_encode($middleware) . ' given.');
            }
            return ['id' => key($middleware), 'arguments' => current($middleware)];
        })->end()->children()->scalar_node('id')->is_required()->cannot_be_empty()->end()->array_node('arguments', 'argument')->normalize_keys(false)->default_value([])->prototype('variable')->end()->end()->end()->end()->end()->end()->end()->end()->end()->end();
    }
    private function add_scheduler_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('scheduler')->info('Scheduler configuration')->{$enable_if_standalone('symfony/scheduler', Schedule::class)}()->end()->end();
    }
    private function add_robots_index_section(Array_Node_Definition $root_node): void
    {
        $root_node->children()->boolean_node('disallow_search_engine_index')->info('Enabled by default when debug is enabled.')->default_value($this->debug)->treat_null_like($this->debug)->end()->end();
    }
    private function add_http_client_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('http_client')->info('HTTP Client configuration')->{$enable_if_standalone('symfony/http-client', Http_Client::class)}()->before_normalization()->if_array()->then(static function (array $config): array {
            if (!($config['scoped_clients'] ?? false)) {
                return $config;
            }
            $has_default_rate_limiter = isset($config['default_options']['rate_limiter']);
            $has_default_retry_failed = \is_array($config['default_options']['retry_failed'] ?? null);
            if (!$has_default_rate_limiter && !$has_default_retry_failed) {
                return $config;
            }
            foreach ($config['scoped_clients'] as &$scoped_config) {
                if ($has_default_rate_limiter) {
                    if (!isset($scoped_config['rate_limiter']) || true === $scoped_config['rate_limiter']) {
                        $scoped_config['rate_limiter'] = $config['default_options']['rate_limiter'];
                    } elseif (false === $scoped_config['rate_limiter']) {
                        $scoped_config['rate_limiter'] = null;
                    }
                }
                if ($has_default_retry_failed) {
                    if (!isset($scoped_config['retry_failed']) || true === $scoped_config['retry_failed']) {
                        $scoped_config['retry_failed'] = $config['default_options']['retry_failed'];
                    } elseif (\is_array($scoped_config['retry_failed'])) {
                        $scoped_config['retry_failed'] += $config['default_options']['retry_failed'];
                    }
                }
            }
            return $config;
        })->end()->children()->integer_node('max_host_connections')->info('The maximum number of connections to a single host.')->end()->array_node('default_options')->children()->array_node('headers', 'header')->info('Associative array: header => value(s).')->use_attribute_as_key('name')->normalize_keys(false)->variable_prototype()->end()->end()->array_node('vars', 'var')->info('Associative array: the default vars used to expand the templated URI.')->use_attribute_as_key('name')->normalize_keys(false)->variable_prototype()->end()->end()->integer_node('max_redirects')->info('The maximum number of redirects to follow.')->end()->scalar_node('http_version')->info('The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.')->end()->array_node('resolve')->info('Associative array: domain => IP.')->use_attribute_as_key('host')->before_normalization()->if_array()->then(static function (array $config): array {
            if (!isset($config['host'], $config['value']) || \count($config) > 2) {
                return $config;
            }
            return [$config['host'] => $config['value']];
        })->end()->normalize_keys(false)->scalar_prototype()->end()->end()->scalar_node('proxy')->info('The URL of the proxy to pass requests through or null for automatic detection.')->end()->scalar_node('no_proxy')->info('A comma separated list of hosts that do not require a proxy to be reached.')->end()->float_node('timeout')->info('The idle timeout, defaults to the "default_socket_timeout" ini parameter.')->end()->float_node('max_duration')->info('The maximum execution time for the request+response as a whole.')->end()->scalar_node('bindto')->info('A network interface name, IP address, a host name or a UNIX socket to bind to.')->end()->boolean_node('verify_peer')->info('Indicates if the peer should be verified in a TLS context.')->end()->boolean_node('verify_host')->info('Indicates if the host should exist as a certificate common name.')->end()->scalar_node('cafile')->info('A certificate authority file.')->end()->scalar_node('capath')->info('A directory that contains multiple certificate authority files.')->end()->scalar_node('local_cert')->info('A PEM formatted certificate file.')->end()->scalar_node('local_pk')->info('A private key file.')->end()->scalar_node('passphrase')->info('The passphrase used to encrypt the "local_pk" file.')->end()->scalar_node('ciphers')->info('A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...)')->end()->array_node('peer_fingerprint')->info('Associative array: hashing algorithm => hash(es).')->normalize_keys(false)->children()->variable_node('sha1')->end()->variable_node('pin-sha256')->end()->variable_node('md5')->end()->end()->end()->scalar_node('crypto_method')->info('The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.')->end()->array_node('extra')->info('Extra options for specific HTTP client.')->use_attribute_as_key('name')->normalize_keys(false)->variable_prototype()->end()->end()->scalar_node('rate_limiter')->default_null()->info('Rate limiter name to use for throttling requests.')->end()->append($this->create_http_client_caching_section())->append($this->create_http_client_retry_section())->end()->end()->scalar_node('mock_response_factory')->info('`true` to always return empty 200 responses, or the id of the service to use to generate mock responses - which should be either an invokable or an iterable.')->end()->array_node('scoped_clients', 'scoped_client')->use_attribute_as_key('name')->normalize_keys(false)->array_prototype()->accept_and_wrap(['string'], 'base_uri')->validate()->if_true(static fn(): bool => !class_exists(Http_Client::class))->then(static fn() => throw new LogicException('HttpClient support cannot be enabled as the component is not installed. Try running "composer require symfony/http-client".'))->end()->validate()->if_true(static fn($v): bool => !isset($v['scope']) && !isset($v['base_uri']))->then_invalid('Either "scope" or "base_uri" should be defined.')->end()->validate()->if_true(static fn($v): bool => !empty($v['query']) && !isset($v['base_uri']))->then_invalid('"query" applies to "base_uri" but no base URI is defined.')->end()->children()->scalar_node('scope')->info('The regular expression that the request URL must match before adding the other options. When none is provided, the base URI is used instead.')->cannot_be_empty()->end()->scalar_node('base_uri')->info('The URI to resolve relative URLs, following rules in RFC 3985, section 2.')->cannot_be_empty()->end()->scalar_node('auth_basic')->info('An HTTP Basic authentication "username:password".')->end()->scalar_node('auth_bearer')->info('A token enabling HTTP Bearer authorization.')->end()->scalar_node('auth_ntlm')->info('A "username:password" pair to use Microsoft NTLM authentication (requires the cURL extension).')->end()->array_node('query')->info('Associative array of query string values merged with the base URI.')->use_attribute_as_key('key')->before_normalization()->if_array()->then(static function (array $config): array {
            if (!isset($config['key'], $config['value']) || \count($config) > 2) {
                return $config;
            }
            return [$config['key'] => $config['value']];
        })->end()->normalize_keys(false)->scalar_prototype()->end()->end()->array_node('headers', 'header')->info('Associative array: header => value(s).')->use_attribute_as_key('name')->normalize_keys(false)->variable_prototype()->end()->end()->integer_node('max_redirects')->info('The maximum number of redirects to follow.')->end()->scalar_node('http_version')->info('The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.')->end()->array_node('resolve')->info('Associative array: domain => IP.')->use_attribute_as_key('host')->before_normalization()->if_array()->then(static function (array $config): array {
            if (!isset($config['host'], $config['value']) || \count($config) > 2) {
                return $config;
            }
            return [$config['host'] => $config['value']];
        })->end()->normalize_keys(false)->scalar_prototype()->end()->end()->scalar_node('proxy')->info('The URL of the proxy to pass requests through or null for automatic detection.')->end()->scalar_node('no_proxy')->info('A comma separated list of hosts that do not require a proxy to be reached.')->end()->float_node('timeout')->info('The idle timeout, defaults to the "default_socket_timeout" ini parameter.')->end()->float_node('max_duration')->info('The maximum execution time for the request+response as a whole.')->end()->scalar_node('bindto')->info('A network interface name, IP address, a host name or a UNIX socket to bind to.')->end()->boolean_node('verify_peer')->info('Indicates if the peer should be verified in a TLS context.')->end()->boolean_node('verify_host')->info('Indicates if the host should exist as a certificate common name.')->end()->scalar_node('cafile')->info('A certificate authority file.')->end()->scalar_node('capath')->info('A directory that contains multiple certificate authority files.')->end()->scalar_node('local_cert')->info('A PEM formatted certificate file.')->end()->scalar_node('local_pk')->info('A private key file.')->end()->scalar_node('passphrase')->info('The passphrase used to encrypt the "local_pk" file.')->end()->scalar_node('ciphers')->info('A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...).')->end()->array_node('peer_fingerprint')->info('Associative array: hashing algorithm => hash(es).')->normalize_keys(false)->children()->variable_node('sha1')->end()->variable_node('pin-sha256')->end()->variable_node('md5')->end()->end()->end()->scalar_node('crypto_method')->info('The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.')->end()->scalar_node('mock_response_factory')->info('`true` to always return empty 200 responses, `false` to disable mocking, or the id of the service to use to generate mock responses (invokable or iterable).')->end()->array_node('extra')->info('Extra options for specific HTTP client.')->use_attribute_as_key('name')->normalize_keys(false)->variable_prototype()->end()->end()->scalar_node('rate_limiter')->default_null()->info('Rate limiter name to use for throttling requests.')->end()->append($this->create_http_client_caching_section())->append($this->create_http_client_retry_section())->end()->end()->end()->end()->end()->end();
    }
    private function create_http_client_caching_section(): Array_Node_Definition
    {
        $root = new Node_Builder();
        return $root->array_node('caching')->info('Caching configuration.')->can_be_enabled()->add_defaults_if_not_set()->children()->string_node('cache_pool')->info('The taggable cache pool to use for storing the responses.')->default_value('cache.http_client')->cannot_be_empty()->end()->boolean_node('shared')->info('Indicates whether the cache is shared (public) or private.')->default_true()->end()->integer_node('max_ttl')->info('The maximum TTL (in seconds) allowed for cached responses. Null means no cap.')->default_null()->min(0)->end()->end();
    }
    private function create_http_client_retry_section(): Array_Node_Definition
    {
        $root = new Node_Builder();
        return $root->array_node('retry_failed')->can_be_enabled()->add_defaults_if_not_set()->before_normalization()->if_array()->then(static function (array $v): array {
            if (isset($v['retry_strategy']) && (isset($v['http_codes']) || isset($v['delay']) || isset($v['multiplier']) || isset($v['max_delay']) || isset($v['jitter']))) {
                throw new \InvalidArgumentException('The "retry_strategy" option cannot be used along with the "http_codes", "delay", "multiplier", "max_delay" or "jitter" options.');
            }
            return $v;
        })->end()->children()->scalar_node('retry_strategy')->default_null()->info('service id to override the retry strategy.')->end()->array_node('http_codes', 'http_code')->perform_no_deep_merging()->accept_and_wrap(['int', 'string'])->before_normalization()->if_array()->then(static function ($v): array {
            $list = [];
            foreach ($v as $key => $val) {
                if (is_numeric($val)) {
                    $list[] = ['code' => $val];
                } elseif (\is_array($val)) {
                    if (isset($val['code']) || isset($val['methods'])) {
                        $list[] = $val;
                    } else {
                        $list[] = ['code' => $key, 'methods' => $val];
                    }
                } elseif (true === $val || null === $val) {
                    $list[] = ['code' => $key];
                }
            }
            return $list;
        })->end()->use_attribute_as_key('code')->array_prototype()->children()->integer_node('code')->end()->array_node('methods', 'method')->accept_and_wrap(['string'])->before_normalization()->if_array()->then(static fn($v): array => array_map(strtoupper(...), $v))->end()->string_prototype()->end()->info('A list of HTTP methods that triggers a retry for this status code. When empty, all methods are retried.')->end()->end()->end()->info('A list of HTTP status code that triggers a retry.')->end()->integer_node('max_retries')->default_value(3)->min(0)->end()->integer_node('delay')->default_value(1000)->min(0)->info('Time in ms to delay (or the initial value when multiplier is used).')->end()->float_node('multiplier')->default_value(2)->min(1)->info('If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries).')->end()->integer_node('max_delay')->default_value(0)->min(0)->info('Max time in ms that a retry should ever be delayed (0 = infinite).')->end()->float_node('jitter')->default_value(0.1)->min(0)->max(1)->info('Randomness in percent (between 0 and 1) to apply to the delay.')->end()->end();
    }
    private function add_mailer_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('mailer')->info('Mailer configuration')->{$enable_if_standalone('symfony/mailer', Mailer::class)}()->validate()->if_true(static fn($v): bool => isset($v['dsn']) && \count($v['transports']))->then_invalid('"dsn" and "transports" cannot be used together.')->end()->children()->scalar_node('message_bus')->default_null()->info('The message bus to use. Defaults to the default bus if the Messenger component is installed.')->end()->scalar_node('dsn')->default_null()->end()->array_node('transports', 'transport')->use_attribute_as_key('name')->prototype('scalar')->end()->end()->array_node('envelope')->info('Mailer Envelope configuration')->children()->scalar_node('sender')->end()->array_node('recipients', 'recipient')->perform_no_deep_merging()->accept_and_wrap(['string'])->before_normalization()->if_array()->then(static fn($v): array => array_values(array_filter($v)))->end()->prototype('scalar')->end()->end()->array_node('allowed_recipients', 'allowed_recipient')->info('A list of regular expressions that allow recipients when "recipients" option is defined.')->example(['.*@example\.com'])->perform_no_deep_merging()->accept_and_wrap(['string'])->before_normalization()->if_array()->then(static fn($v): array => array_values(array_filter($v)))->end()->prototype('scalar')->end()->end()->end()->end()->array_node('headers', 'header')->normalize_keys(false)->use_attribute_as_key('name')->prototype('array')->normalize_keys(false)->accept_and_wrap(['string'], 'value')->before_normalization()->if_array()->then(static fn($v) => array_keys($v) !== ['value'] ? ['value' => $v] : $v)->end()->children()->variable_node('value')->end()->end()->end()->end()->array_node('dkim_signer')->add_defaults_if_not_set()->can_be_enabled()->info('DKIM signer configuration')->children()->scalar_node('key')->info('Key content, or path to key (in PEM format with the `file://` prefix)')->default_value('')->cannot_be_empty()->end()->scalar_node('domain')->default_value('')->end()->scalar_node('select')->default_value('')->end()->scalar_node('passphrase')->info('The private key passphrase')->default_value('')->end()->array_node('options', 'option')->perform_no_deep_merging()->normalize_keys(false)->use_attribute_as_key('name')->prototype('variable')->end()->end()->end()->end()->array_node('smime_signer')->add_defaults_if_not_set()->can_be_enabled()->info('S/MIME signer configuration')->children()->scalar_node('key')->info('Path to key (in PEM format)')->default_value('')->cannot_be_empty()->end()->scalar_node('certificate')->info('Path to certificate (in PEM format without the `file://` prefix)')->default_value('')->cannot_be_empty()->end()->scalar_node('passphrase')->info('The private key passphrase')->default_null()->end()->scalar_node('extra_certificates')->default_null()->end()->integer_node('sign_options')->default_null()->end()->end()->end()->array_node('smime_encrypter')->add_defaults_if_not_set()->can_be_enabled()->info('S/MIME encrypter configuration')->children()->scalar_node('repository')->info('S/MIME certificate repository service. This service shall implement the `Symfony\Component\Mailer\EventListener\SmimeCertificateRepositoryInterface`.')->default_value('')->cannot_be_empty()->end()->integer_node('cipher')->info('A set of algorithms used to encrypt the message')->default_null()->before_normalization()->if_string()->then(static function (string $v): ?int {
            if (\defined('OPENSSL_CIPHER_' . $v)) {
                return \constant('OPENSSL_CIPHER_' . $v);
            }
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid OPENSSL cipher.', $v));
        })->end()->validate()->if_true(static fn($v): bool => \extension_loaded('openssl') && null !== $v && !\defined('OPENSSL_CIPHER_' . $v))->then_invalid('You must provide a valid cipher.')->end()->end()->end()->end()->end()->end()->end();
    }
    private function add_notifier_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('notifier')->info('Notifier configuration')->{$enable_if_standalone('symfony/notifier', Notifier::class)}()->children()->scalar_node('message_bus')->default_null()->info('The message bus to use. Defaults to the default bus if the Messenger component is installed.')->end()->array_node('chatter_transports', 'chatter_transport')->use_attribute_as_key('name')->prototype('scalar')->end()->end()->array_node('texter_transports', 'texter_transport')->use_attribute_as_key('name')->prototype('scalar')->end()->end()->boolean_node('notification_on_failed_messages')->default_false()->end()->array_node('channel_policy')->use_attribute_as_key('name')->prototype('array')->accept_and_wrap(['string'])->prototype('scalar')->end()->end()->end()->array_node('admin_recipients', 'admin_recipient')->prototype('array')->children()->scalar_node('email')->cannot_be_empty()->end()->scalar_node('phone')->default_value('')->end()->end()->end()->end()->end()->end()->end();
    }
    private function add_webhook_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('webhook')->info('Webhook configuration')->{$enable_if_standalone('symfony/webhook', Webhook_Controller::class)}()->children()->scalar_node('message_bus')->default_value('messenger.default_bus')->info('The message bus to use.')->end()->array_node('routing')->normalize_keys(false)->use_attribute_as_key('type')->prototype('array')->children()->scalar_node('service')->is_required()->cannot_be_empty()->end()->scalar_node('secret')->default_value('')->end()->end()->end()->end()->end()->end();
    }
    private function add_remote_event_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('remote-event')->info('RemoteEvent configuration')->{$enable_if_standalone('symfony/remote-event', Remote_Event::class)}()->end()->end();
    }
    private function add_rate_limiter_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('rate_limiter')->info('Rate limiter configuration')->{$enable_if_standalone('symfony/rate-limiter', Token_Bucket_Limiter::class)}()->before_normalization()->if_array()->then(static function (array $v): array {
            if (!isset($v['limiters']) && !isset($v['limiter'])) {
                $v = ['limiters' => $v];
                if (\array_key_exists('enabled', $v['limiters'])) {
                    $v['enabled'] = $v['limiters']['enabled'];
                    unset($v['limiters']['enabled']);
                }
            }
            return $v;
        })->end()->children()->array_node('limiters', 'limiter')->use_attribute_as_key('name')->array_prototype()->children()->scalar_node('lock_factory')->info('The service ID of the lock factory used by this limiter (or null to disable locking).')->default_value('auto')->end()->scalar_node('cache_pool')->info('The cache pool to use for storing the current limiter state.')->default_value('cache.rate_limiter')->end()->scalar_node('storage_service')->info('The service ID of a custom storage implementation, this precedes any configured "cache_pool".')->default_null()->end()->enum_node('policy')->info('The algorithm to be used by this limiter.')->is_required()->values(['fixed_window', 'token_bucket', 'sliding_window', 'compound', 'no_limit'])->end()->array_node('limiters', 'limiter')->info('The limiter names to use when using the "compound" policy.')->accept_and_wrap(['string'])->scalar_prototype()->end()->end()->integer_node('limit')->info('The maximum allowed hits in a fixed interval or burst.')->end()->scalar_node('interval')->info('Configures the fixed interval if "policy" is set to "fixed_window" or "sliding_window". The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).')->end()->array_node('rate')->info('Configures the fill rate if "policy" is set to "token_bucket".')->children()->scalar_node('interval')->info('Configures the rate interval. The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).')->end()->integer_node('amount')->info('Amount of tokens to add each interval.')->default_value(1)->end()->end()->end()->end()->validate()->if_true(static fn($v): bool => !\in_array($v['policy'], ['no_limit', 'compound'], true) && !isset($v['limit']))->then_invalid('A limit must be provided when using a policy different than "compound" or "no_limit".')->end()->end()->end()->end()->end()->end();
    }
    private function add_uid_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('uid')->info('Uid configuration')->{$enable_if_standalone('symfony/uid', Uuid_Factory::class)}()->add_defaults_if_not_set()->children()->enum_node('default_uuid_version')->values([7, 6, 4, 1])->default_value(7)->end()->enum_node('name_based_uuid_version')->default_value(5)->values([5, 3])->end()->scalar_node('name_based_uuid_namespace')->cannot_be_empty()->end()->enum_node('time_based_uuid_version')->values([7, 6, 1])->default_value(7)->end()->scalar_node('time_based_uuid_node')->cannot_be_empty()->end()->end()->end()->end();
    }
    private function add_html_sanitizer_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('html_sanitizer')->info('HtmlSanitizer configuration')->{$enable_if_standalone('symfony/html-sanitizer', Html_Sanitizer_Interface::class)}()->children()->array_node('sanitizers', 'sanitizer')->use_attribute_as_key('name')->array_prototype()->children()->enum_node('default_action')->info('Defines how the sanitizer must behave by default.')->values(['drop', 'block', 'allow'])->end()->boolean_node('allow_safe_elements')->info('Allows "safe" elements and attributes.')->default_false()->end()->boolean_node('allow_static_elements')->info('Allows all static elements and attributes from the W3C Sanitizer API standard.')->default_false()->end()->array_node('allow_elements', 'allow_element')->info('Configures the elements that the sanitizer should retain from the input. The element name is the key, the value is either a list of allowed attributes for this element or "*" to allow the default set of attributes (https://wicg.github.io/sanitizer-api/#default-configuration).')->example(['i' => '*', 'a' => ['title'], 'span' => 'class'])->normalize_keys(false)->use_attribute_as_key('name')->variable_prototype()->before_normalization()->if_array()->then(static fn($n) => $n['attribute'] ?? $n)->end()->validate()->if_true(static fn($n): bool => !\is_string($n) && !\is_array($n))->then_invalid('The value must be either a string or an array of strings.')->end()->end()->end()->array_node('block_elements', 'block_element')->info('Configures elements as blocked. Blocked elements are elements the sanitizer should remove from the input, but retain their children.')->accept_and_wrap(['string'])->string_prototype()->end()->end()->array_node('drop_elements', 'drop_element')->info('Configures elements as dropped. Dropped elements are elements the sanitizer should remove from the input, including their children.')->accept_and_wrap(['string'])->string_prototype()->end()->end()->array_node('allow_attributes', 'allow_attribute')->info('Configures attributes as allowed. Allowed attributes are attributes the sanitizer should retain from the input.')->normalize_keys(false)->use_attribute_as_key('name')->variable_prototype()->before_normalization()->if_array()->then(static fn($n) => $n['element'] ?? $n)->end()->end()->end()->array_node('drop_attributes', 'drop_attribute')->info('Configures attributes as dropped. Dropped attributes are attributes the sanitizer should remove from the input.')->normalize_keys(false)->use_attribute_as_key('name')->variable_prototype()->before_normalization()->if_array()->then(static fn($n) => $n['element'] ?? $n)->end()->end()->end()->array_node('force_attributes', 'force_attribute')->info('Forcefully set the values of certain attributes on certain elements.')->normalize_keys(false)->use_attribute_as_key('name')->array_prototype()->normalize_keys(false)->use_attribute_as_key('name')->string_prototype()->end()->end()->end()->boolean_node('force_https_urls')->info('Transforms URLs using the HTTP scheme to use the HTTPS scheme instead.')->default_false()->end()->array_node('allowed_link_schemes', 'allowed_link_scheme')->info('Allows only a given list of schemes to be used in links href attributes.')->accept_and_wrap(['string'])->string_prototype()->end()->end()->array_node('allowed_link_hosts', 'allowed_link_host')->info('Allows only a given list of hosts to be used in links href attributes.')->default_null()->accept_and_wrap(['string'])->string_prototype()->end()->end()->boolean_node('allow_relative_links')->info('Allows relative URLs to be used in links href attributes.')->default_false()->end()->array_node('allowed_media_schemes', 'allowed_media_scheme')->info('Allows only a given list of schemes to be used in media source attributes (img, audio, video, ...).')->accept_and_wrap(['string'])->string_prototype()->end()->end()->array_node('allowed_media_hosts', 'allowed_media_host')->info('Allows only a given list of hosts to be used in media source attributes (img, audio, video, ...).')->default_null()->accept_and_wrap(['string'])->string_prototype()->end()->end()->boolean_node('allow_relative_medias')->info('Allows relative URLs to be used in media source attributes (img, audio, video, ...).')->default_false()->end()->array_node('with_attribute_sanitizers', 'with_attribute_sanitizer')->info('Registers custom attribute sanitizers.')->accept_and_wrap(['string'])->string_prototype()->end()->end()->array_node('without_attribute_sanitizers', 'without_attribute_sanitizer')->info('Unregisters custom attribute sanitizers.')->accept_and_wrap(['string'])->string_prototype()->end()->end()->integer_node('max_input_length')->info('The maximum length allowed for the sanitized input.')->default_value(0)->end()->end()->end()->end()->end()->end()->end();
    }
    private function add_json_streamer_section(Array_Node_Definition $root_node, callable $enable_if_standalone): void
    {
        $root_node->children()->array_node('json_streamer')->info('JSON streamer configuration')->{$enable_if_standalone('symfony/json-streamer', Stream_Writer_Interface::class)}()->children()->array_node('default_options')->add_defaults_if_not_set()->ignore_extra_keys(false)->children()->boolean_node('include_null_properties')->info('Encode the properties with null value')->default_false()->end()->end()->end()->end()->end()->end();
    }
}