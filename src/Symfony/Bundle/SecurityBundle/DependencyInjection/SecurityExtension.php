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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection;

use Composer\Installed_Versions;
use Symfony\Bridge\Twig\Extension\Logout_Url_Extension;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Authenticator_Factory_Interface;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Firewall_Listener_Factory_Interface;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory\Stateless_Authenticator_Factory_Interface;
use Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\User_Provider\User_Provider_Factory_Interface;
use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Config\Definition\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Config\File_Locator;
use Symfony\Component\Console\Application;
use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Compiler\Service_Locator_Tag_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Extension\Extension;
use Symfony\Component\Dependency_Injection\Extension\Prepend_Extension_Interface;
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Expression_Language\Expression_Language;
use Symfony\Component\Form\Extension\Password_Hasher\Password_Hasher_Extension;
use Symfony\Component\Http_Foundation\Chain_Request_Matcher;
use Symfony\Component\Http_Foundation\Request_Matcher\Attributes_Request_Matcher;
use Symfony\Component\Http_Foundation\Request_Matcher\Host_Request_Matcher;
use Symfony\Component\Http_Foundation\Request_Matcher\Ips_Request_Matcher;
use Symfony\Component\Http_Foundation\Request_Matcher\Method_Request_Matcher;
use Symfony\Component\Http_Foundation\Request_Matcher\Path_Request_Matcher;
use Symfony\Component\Http_Foundation\Request_Matcher\Port_Request_Matcher;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Password_Hasher\Hasher\Native_Password_Hasher;
use Symfony\Component\Password_Hasher\Hasher\Pbkdf2password_Hasher;
use Symfony\Component\Password_Hasher\Hasher\Plaintext_Password_Hasher;
use Symfony\Component\Password_Hasher\Hasher\Sodium_Password_Hasher;
use Symfony\Component\Password_Hasher\Password_Hasher_Interface;
use Symfony\Component\Routing\Loader\Container_Loader;
use Symfony\Component\Security\Core\Authorization\Strategy\Affirmative_Strategy;
use Symfony\Component\Security\Core\Authorization\Strategy\Consensus_Strategy;
use Symfony\Component\Security\Core\Authorization\Strategy\Priority_Strategy;
use Symfony\Component\Security\Core\Authorization\Strategy\Unanimous_Strategy;
use Symfony\Component\Security\Core\Authorization\Voter\Voter_Interface;
use Symfony\Component\Security\Core\User\Chain_User_Checker;
use Symfony\Component\Security\Core\User\Chain_User_Provider;
use Symfony\Component\Security\Core\User\User_Checker_Interface;
use Symfony\Component\Security\Core\User\User_Provider_Interface;
use Symfony\Component\Security\Http\Authenticator\Debug\Traceable_Authenticator;
use Symfony\Component\Security\Http\Authenticator\Debug\Traceable_Authenticator_Manager_Listener;
use Symfony\Component\Security\Http\Event\Check_Passport_Event;
/**
 * SecurityExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Security_Extension extends Extension implements Prepend_Extension_Interface
{
    private array $request_matchers = [];
    private array $expressions = [];
    private array $context_listeners = [];
    /** @var list<array{int, AuthenticatorFactoryInterface}> */
    private array $factories = [];
    /** @var AuthenticatorFactoryInterface[] */
    private array $sorted_factories = [];
    private array $user_provider_factories = [];
    public function prepend(Container_Builder $container): void
    {
        foreach ($this->get_sorted_factories() as $factory) {
            if ($factory instanceof Prepend_Extension_Interface) {
                $factory->prepend($container);
            }
        }
    }
    public function load(array $configs, Container_Builder $container): void
    {
        if (!array_filter($configs)) {
            $hint = class_exists(Installed_Versions::class) && Installed_Versions::is_installed('symfony/flex') ? 'Try running "composer symfony:recipes:install symfony/security-bundle".' : 'Please define your settings for the "security" config section.';
            throw new Invalid_Configuration_Exception('The SecurityBundle is enabled but is not configured. ' . $hint);
        }
        $main_config = $this->get_configuration($configs, $container);
        $config = $this->process_configuration($main_config, $configs);
        // load services
        $loader = new Php_File_Loader($container, new File_Locator(\dirname(__DIR__) . '/Resources/config'));
        $loader->load('security.php');
        $loader->load('password_hasher.php');
        $loader->load('security_listeners.php');
        $loader->load('security_authenticator.php');
        $loader->load('security_authenticator_access_token.php');
        if ($container::will_be_available('symfony/twig-bridge', Logout_Url_Extension::class, ['symfony/security-bundle'])) {
            $loader->load('templating_twig.php');
        }
        $loader->load('collectors.php');
        if ($container->has_parameter('kernel.debug') && $container->get_parameter('kernel.debug')) {
            $loader->load('security_debug.php');
        }
        if (!$container::will_be_available('symfony/expression-language', Expression_Language::class, ['symfony/security-bundle'])) {
            $container->remove_definition('security.expression_language');
            $container->remove_definition('security.access.expression_voter');
            $container->remove_definition('security.is_granted_attribute_expression_language');
            $container->remove_definition('security.is_csrf_token_valid_attribute_expression_language');
        }
        if (!class_exists(Password_Hasher_Extension::class)) {
            $container->remove_definition('form.listener.password_hasher');
            $container->remove_definition('form.type_extension.form.password_hasher');
            $container->remove_definition('form.type_extension.password.password_hasher');
        }
        // set some global scalars
        $container->set_parameter('security.access.denied_url', $config['access_denied_url']);
        $container->set_parameter('security.authentication.manager.erase_credentials', $config['erase_credentials']);
        $container->set_parameter('security.authentication.session_strategy.strategy', $config['session_fixation_strategy']);
        if (isset($config['access_decision_manager']['service'])) {
            $container->set_alias('security.access.decision_manager', $config['access_decision_manager']['service']);
        } elseif (isset($config['access_decision_manager']['strategy_service'])) {
            $container->get_definition('security.access.decision_manager')->add_argument(new Reference($config['access_decision_manager']['strategy_service']));
        } else {
            $container->get_definition('security.access.decision_manager')->add_argument($this->create_strategy_definition($config['access_decision_manager']['strategy'] ?? Main_Configuration::STRATEGY_AFFIRMATIVE, $config['access_decision_manager']['allow_if_all_abstain'], $config['access_decision_manager']['allow_if_equal_granted_denied']));
        }
        $container->set_parameter('.security.authentication.expose_security_errors', $config['expose_security_errors']);
        if (class_exists(Application::class)) {
            $loader->load('debug_console.php');
        }
        $this->create_firewalls($config, $container);
        if ($container::will_be_available('symfony/routing', Container_Loader::class, ['symfony/security-bundle'])) {
            $this->create_logout_uris_parameter($config['firewalls'] ?? [], $container);
        } else {
            $container->remove_definition('security.route_loader.logout');
        }
        $this->create_authorization($config, $container);
        $this->create_role_hierarchy($config, $container);
        if ($config['password_hashers']) {
            $this->create_hashers($config['password_hashers'], $container);
        }
        if (class_exists(Application::class)) {
            $loader->load('console.php');
            $container->get_definition('security.command.user_password_hash')->replace_argument(1, array_keys($config['password_hashers']));
        }
        if ($container->has_definition('security.role_hierarchy')) {
            $loader->load('security_role_hierarchy_dump_command.php');
        }
        $container->register_for_autoconfiguration(Voter_Interface::class)->add_tag('security.voter');
    }
    private function create_strategy_definition(string $strategy, bool $allow_if_all_abstain_decisions, bool $allow_if_equal_granted_denied_decisions): Definition
    {
        return match ($strategy) {
            Main_Configuration::STRATEGY_AFFIRMATIVE => new Definition(Affirmative_Strategy::class, [$allow_if_all_abstain_decisions]),
            Main_Configuration::STRATEGY_CONSENSUS => new Definition(Consensus_Strategy::class, [$allow_if_all_abstain_decisions, $allow_if_equal_granted_denied_decisions]),
            Main_Configuration::STRATEGY_UNANIMOUS => new Definition(Unanimous_Strategy::class, [$allow_if_all_abstain_decisions]),
            Main_Configuration::STRATEGY_PRIORITY => new Definition(Priority_Strategy::class, [$allow_if_all_abstain_decisions]),
            default => throw new Invalid_Configuration_Exception(\sprintf('The strategy "%s" is not supported.', $strategy)),
        };
    }
    private function create_role_hierarchy(array $config, Container_Builder $container): void
    {
        if (!isset($config['role_hierarchy']) || 0 === \count($config['role_hierarchy'])) {
            $container->remove_definition('security.access.role_hierarchy_voter');
            return;
        }
        $container->set_parameter('security.role_hierarchy.roles', $config['role_hierarchy']);
        $container->remove_definition('security.access.simple_role_voter');
    }
    private function create_authorization(array $config, Container_Builder $container): void
    {
        foreach ($config['access_control'] as $access) {
            if (isset($access['request_matcher'])) {
                if ($access['path'] || $access['host'] || $access['port'] || $access['ips'] || $access['methods'] || $access['attributes'] || $access['route']) {
                    throw new Invalid_Configuration_Exception('The "request_matcher" option should not be specified alongside other options. Consider integrating your constraints inside your RequestMatcher directly.');
                }
                $matcher = new Reference($access['request_matcher']);
            } else {
                $attributes = $access['attributes'];
                if ($access['route']) {
                    if (\array_key_exists('_route', $attributes)) {
                        throw new Invalid_Configuration_Exception('The "route" option should not be specified alongside "attributes._route" option. Use just one of the options.');
                    }
                    $attributes['_route'] = $access['route'];
                }
                $matcher = $this->create_request_matcher($container, $access['path'], $access['host'], $access['port'], $access['methods'], $access['ips'], $attributes);
            }
            $roles = $access['roles'];
            if ($access['allow_if']) {
                $roles[] = $this->create_expression($container, $access['allow_if']);
            }
            $empty_access = 0 === \count(array_filter($access));
            if ($empty_access) {
                throw new Invalid_Configuration_Exception('One or more access control items are empty. Did you accidentally add lines only containing a "-" under "security.access_control"?');
            }
            $container->get_definition('security.access_map')->add_method_call('add', [$matcher, $roles, $access['requires_channel']]);
        }
        // allow cache warm-up for expressions
        if (\count($this->expressions)) {
            $container->get_definition('security.cache_warmer.expression')->replace_argument(0, new Iterator_Argument(array_values($this->expressions)));
        } else {
            $container->remove_definition('security.cache_warmer.expression');
        }
    }
    private function create_firewalls(array $config, Container_Builder $container): void
    {
        if (!isset($config['firewalls'])) {
            return;
        }
        $firewalls = $config['firewalls'];
        $provider_ids = $this->create_user_providers($config, $container);
        $container->set_parameter('security.firewalls', array_keys($firewalls));
        // make the ContextListener aware of the configured user providers
        $context_listener_definition = $container->get_definition('security.context_listener');
        $arguments = $context_listener_definition->get_arguments();
        $user_providers = [];
        foreach ($provider_ids as $user_provider_id) {
            $user_providers[] = new Reference($user_provider_id);
        }
        $arguments[1] = $user_provider_iterators_argument = new Iterator_Argument($user_providers);
        $context_listener_definition->set_arguments($arguments);
        $nb_user_providers = \count($user_providers);
        if ($nb_user_providers > 1) {
            $container->set_definition('security.user_providers', new Definition(Chain_User_Provider::class, [$user_provider_iterators_argument]));
        } elseif (0 === $nb_user_providers) {
            $container->remove_definition('security.listener.user_provider');
        } else {
            $container->set_alias('security.user_providers', new Alias(current($provider_ids)));
        }
        if (1 === \count($provider_ids)) {
            $container->set_alias(User_Provider_Interface::class, current($provider_ids));
        }
        $custom_user_checker = false;
        // load firewall map
        $map_def = $container->get_definition('security.firewall.map');
        $map = $authentication_providers = $context_refs = $authenticators = [];
        foreach ($firewalls as $name => $firewall) {
            if (isset($firewall['user_checker']) && 'security.user_checker' !== $firewall['user_checker']) {
                $custom_user_checker = true;
            }
            $config_id = 'security.firewall.map.config.' . $name;
            [$matcher, $listeners, $exception_listener, $logout_listener, $firewall_authenticators] = $this->create_firewall($container, $name, $firewall, $provider_ids, $config_id);
            if (!$firewall_authenticators) {
                $authenticators[$name] = null;
            } else {
                $firewall_authenticator_refs = [];
                foreach ($firewall_authenticators as $original_authenticator_id => $manager_authenticator_id) {
                    $firewall_authenticator_refs[$original_authenticator_id] = new Reference($original_authenticator_id);
                }
                $authenticators[$name] = Service_Locator_Tag_Pass::register($container, $firewall_authenticator_refs);
            }
            $context_id = 'security.firewall.map.context.' . $name;
            $is_lazy = !$firewall['stateless'] && $firewall['lazy'];
            $context = new Child_Definition($is_lazy ? 'security.firewall.lazy_context' : 'security.firewall.context');
            $context = $container->set_definition($context_id, $context);
            $context->replace_argument(0, new Iterator_Argument($listeners))->replace_argument(1, $exception_listener)->replace_argument(2, $logout_listener)->replace_argument(3, new Reference($config_id));
            $context_refs[$context_id] = new Reference($context_id);
            $map[$context_id] = $matcher;
        }
        $container->get_definition('security.helper')->replace_argument(1, $authenticators);
        $container->set_alias('security.firewall.context_locator', (string) Service_Locator_Tag_Pass::register($container, $context_refs));
        $map_def->replace_argument(0, new Reference('security.firewall.context_locator'));
        $map_def->replace_argument(1, new Iterator_Argument($map));
        // register an autowire alias for the UserCheckerInterface if no custom user checker service is configured
        if (!$custom_user_checker) {
            $container->set_alias(User_Checker_Interface::class, new Alias('security.user_checker', false));
        }
    }
    private function create_firewall(Container_Builder $container, string $id, array $firewall, array $provider_ids, string $config_id): array
    {
        $config = $container->set_definition($config_id, new Child_Definition('security.firewall.config'));
        $config->replace_argument(0, $id);
        $config->replace_argument(1, $firewall['user_checker']);
        // Matcher
        $matcher = null;
        if (isset($firewall['request_matcher'])) {
            $matcher = new Reference($firewall['request_matcher']);
        } elseif (isset($firewall['pattern']) || isset($firewall['host'])) {
            $pattern = $firewall['pattern'] ?? null;
            $host = $firewall['host'] ?? null;
            $methods = $firewall['methods'] ?? [];
            $matcher = $this->create_request_matcher($container, $pattern, $host, null, $methods);
        }
        $config->replace_argument(2, $matcher ? (string) $matcher : null);
        $config->replace_argument(3, $firewall['security']);
        // Security disabled?
        if (false === $firewall['security']) {
            return [$matcher, [], null, null, []];
        }
        $config->replace_argument(4, $firewall['stateless']);
        $firewall_event_dispatcher_id = 'security.event_dispatcher.' . $id;
        // Provider id (must be configured explicitly per firewall/authenticator if more than one provider is set)
        $default_provider = null;
        if (isset($firewall['provider'])) {
            if (!isset($provider_ids[$normalized_name = str_replace('-', '_', $firewall['provider'])])) {
                throw new Invalid_Configuration_Exception(\sprintf('Invalid firewall "%s": user provider "%s" not found.', $id, $firewall['provider']));
            }
            $default_provider = $provider_ids[$normalized_name];
            $container->set_definition('security.listener.' . $id . '.user_provider', new Child_Definition('security.listener.user_provider.abstract'))->add_tag('kernel.event_listener', ['dispatcher' => $firewall_event_dispatcher_id, 'event' => Check_Passport_Event::class, 'priority' => 2048, 'method' => 'checkPassport'])->replace_argument(0, new Reference($default_provider));
        } elseif (1 === \count($provider_ids)) {
            $default_provider = reset($provider_ids);
        }
        $config->replace_argument(5, $default_provider);
        // Register Firewall-specific event dispatcher
        $container->register($firewall_event_dispatcher_id, Event_Dispatcher::class)->add_tag('event_dispatcher.dispatcher', ['name' => $firewall_event_dispatcher_id]);
        $event_dispatcher_locator = $container->get_definition('security.firewall.event_dispatcher_locator');
        $event_dispatcher_locator->replace_argument(0, array_merge($event_dispatcher_locator->get_argument(0), [$id => new Service_Closure_Argument(new Reference($firewall_event_dispatcher_id))]));
        // Register Firewall-specific chained user checker
        $container->register('security.user_checker.chain.' . $id, Chain_User_Checker::class)->add_argument(new Tagged_Iterator_Argument('security.user_checker.' . $id));
        // Register listeners
        $listeners = [];
        $listener_keys = [];
        // Channel listener
        $listeners[] = new Reference('security.channel_listener');
        $context_key = null;
        // Context serializer listener
        if (false === $firewall['stateless']) {
            $context_key = $firewall['context'] ?? $id;
            $listeners[] = new Reference($this->create_context_listener($container, $context_key, $firewall_event_dispatcher_id));
            $session_strategy_id = 'security.authentication.session_strategy';
            $container->set_definition('security.listener.session.' . $id, new Child_Definition('security.listener.session'))->add_tag('kernel.event_subscriber', ['dispatcher' => $firewall_event_dispatcher_id]);
        } else {
            $session_strategy_id = 'security.authentication.session_strategy_noop';
        }
        $container->set_alias(new Alias('security.authentication.session_strategy.' . $id, false), $session_strategy_id);
        $config->replace_argument(6, $context_key);
        // Logout listener
        $logout_listener_id = null;
        if (isset($firewall['logout'])) {
            $logout_listener_id = 'security.logout_listener.' . $id;
            $logout_listener = $container->set_definition($logout_listener_id, new Child_Definition('security.logout_listener'));
            $logout_listener->replace_argument(2, new Reference($firewall_event_dispatcher_id));
            $logout_listener->replace_argument(3, ['csrf_parameter' => $firewall['logout']['csrf_parameter'], 'csrf_token_id' => $firewall['logout']['csrf_token_id'], 'logout_path' => $firewall['logout']['path']]);
            $container->set_definition('security.logout.listener.default.' . $id, new Child_Definition('security.logout.listener.default'))->replace_argument(1, $firewall['logout']['target'])->add_tag('kernel.event_subscriber', ['dispatcher' => $firewall_event_dispatcher_id]);
            // add CSRF provider
            if ($firewall['logout']['enable_csrf']) {
                $logout_listener->add_argument(new Reference($firewall['logout']['csrf_token_manager']));
            }
            // add session logout listener
            if (true === $firewall['logout']['invalidate_session'] && false === $firewall['stateless']) {
                $container->set_definition('security.logout.listener.session.' . $id, new Child_Definition('security.logout.listener.session'))->add_tag('kernel.event_subscriber', ['dispatcher' => $firewall_event_dispatcher_id]);
            }
            // add cookie logout listener
            if (\count($firewall['logout']['delete_cookies']) > 0) {
                $container->set_definition('security.logout.listener.cookie_clearing.' . $id, new Child_Definition('security.logout.listener.cookie_clearing'))->add_argument($firewall['logout']['delete_cookies'])->add_tag('kernel.event_subscriber', ['dispatcher' => $firewall_event_dispatcher_id]);
            }
            // add clear site data listener
            if ($firewall['logout']['clear_site_data'] ?? false) {
                $container->set_definition('security.logout.listener.clear_site_data.' . $id, new Child_Definition('security.logout.listener.clear_site_data'))->add_argument($firewall['logout']['clear_site_data'])->add_tag('kernel.event_subscriber', ['dispatcher' => $firewall_event_dispatcher_id]);
            }
            // register with LogoutUrlGenerator
            $container->get_definition('security.logout_url_generator')->add_method_call('registerListener', [$id, $firewall['logout']['path'], $firewall['logout']['csrf_token_id'], $firewall['logout']['csrf_parameter'], isset($firewall['logout']['csrf_token_manager']) ? new Reference($firewall['logout']['csrf_token_manager']) : null, false === $firewall['stateless'] && isset($firewall['context']) ? $firewall['context'] : null]);
            $config->replace_argument(12, $firewall['logout']);
        }
        // Determine default entry point
        $configured_entry_point = $firewall['entry_point'] ?? null;
        // Authentication listeners
        $firewall_authentication_providers = [];
        [$auth_listeners, $default_entry_point] = $this->create_authentication_listeners($container, $id, $firewall, $firewall_authentication_providers, $default_provider, $provider_ids, $configured_entry_point);
        // $configuredEntryPoint is resolved into a service ID and stored in $defaultEntryPoint
        $configured_entry_point = $default_entry_point;
        // authenticator manager
        $authenticators = array_map(static fn($id): \Symfony\Component\Dependency_Injection\Reference => new Reference($id), $firewall_authentication_providers, []);
        $container->set_definition($manager_id = 'security.authenticator.manager.' . $id, new Child_Definition('security.authenticator.manager'))->replace_argument(0, $authenticators)->replace_argument(2, new Reference($firewall_event_dispatcher_id))->replace_argument(3, $id)->replace_argument(7, $firewall['required_badges'] ?? [])->add_tag('monolog.logger', ['channel' => 'security']);
        $manager_locator = $container->get_definition('security.authenticator.managers_locator');
        $manager_locator->replace_argument(0, array_merge($manager_locator->get_argument(0), [$id => new Service_Closure_Argument(new Reference($manager_id))]));
        // authenticator manager listener
        $container->set_definition('security.firewall.authenticator.' . $id, new Child_Definition('security.firewall.authenticator'))->replace_argument(0, new Reference($manager_id));
        if ($container->has_definition('debug.security.firewall')) {
            $container->register('debug.security.firewall.authenticator.' . $id, Traceable_Authenticator_Manager_Listener::class)->set_decorated_service('security.firewall.authenticator.' . $id)->set_arguments([new Reference('debug.security.firewall.authenticator.' . $id . '.inner')])->add_tag('kernel.reset', ['method' => 'reset']);
        }
        // user checker listener
        $container->set_definition('security.listener.user_checker.' . $id, new Child_Definition('security.listener.user_checker'))->replace_argument(0, new Reference('security.user_checker.' . $id))->add_tag('kernel.event_subscriber', ['dispatcher' => $firewall_event_dispatcher_id]);
        $listeners[] = new Reference('security.firewall.authenticator.' . $id);
        // Add authenticators to the debug:firewall command
        if ($container->has_definition('security.command.debug_firewall')) {
            $debug_command = $container->get_definition('security.command.debug_firewall');
            $debug_command->replace_argument(3, array_merge($debug_command->get_argument(3), [$id => $authenticators]));
        }
        $config->replace_argument(7, $configured_entry_point ?: $default_entry_point);
        $listeners = array_merge($listeners, $auth_listeners);
        // Switch user listener
        if (isset($firewall['switch_user'])) {
            $listener_keys[] = 'switch_user';
            $listeners[] = new Reference($this->create_switch_user_listener($container, $id, $firewall['switch_user'], $default_provider, $firewall['stateless']));
        }
        // Access listener
        $listeners[] = new Reference('security.access_listener');
        // Exception listener
        $exception_listener = new Reference($this->create_exception_listener($container, $firewall, $id, $configured_entry_point ?: $default_entry_point, $firewall['stateless']));
        $config->replace_argument(8, $firewall['access_denied_handler'] ?? null);
        $config->replace_argument(9, $firewall['access_denied_url'] ?? null);
        $container->set_alias('security.user_checker.' . $id, new Alias($firewall['user_checker'], false));
        $user_checker_locator = $container->get_definition('security.user_checker_locator');
        $user_checker_locator->replace_argument(0, array_merge($user_checker_locator->get_argument(0), [$id => new Service_Closure_Argument(new Reference('security.user_checker.' . $id))]));
        foreach ($this->get_sorted_factories() as $factory) {
            $key = str_replace('-', '_', $factory->get_key());
            if ('custom_authenticators' !== $key && \array_key_exists($key, $firewall)) {
                $listener_keys[] = $key;
            }
        }
        if ($firewall['custom_authenticators'] ?? false) {
            foreach ($firewall['custom_authenticators'] as $custom_authenticator_id) {
                $listener_keys[] = $custom_authenticator_id;
            }
        }
        $config->replace_argument(10, $listener_keys);
        $config->replace_argument(11, $firewall['switch_user'] ?? null);
        return [$matcher, $listeners, $exception_listener, null !== $logout_listener_id ? new Reference($logout_listener_id) : null, $firewall_authentication_providers];
    }
    private function create_context_listener(Container_Builder $container, string $context_key, ?string $firewall_event_dispatcher_id): string
    {
        if (isset($this->context_listeners[$context_key])) {
            return $this->context_listeners[$context_key];
        }
        $listener_id = 'security.context_listener.' . \count($this->context_listeners);
        $listener = $container->set_definition($listener_id, new Child_Definition('security.context_listener'));
        $listener->replace_argument(2, $context_key);
        if (null !== $firewall_event_dispatcher_id) {
            $listener->replace_argument(4, new Reference($firewall_event_dispatcher_id));
            $listener->add_tag('kernel.event_listener', ['event' => Kernel_Events::RESPONSE, 'method' => 'onKernelResponse']);
        }
        return $this->context_listeners[$context_key] = $listener_id;
    }
    private function create_authentication_listeners(Container_Builder $container, string $id, array $firewall, array &$authentication_providers, ?string $default_provider, array $provider_ids, ?string $default_entry_point): array
    {
        $listeners = [];
        $entry_points = [];
        foreach ($this->get_sorted_factories() as $factory) {
            $key = str_replace('-', '_', $factory->get_key());
            if (isset($firewall[$key])) {
                $user_provider = $this->get_user_provider($container, $id, $firewall, $key, $default_provider, $provider_ids);
                if (!$factory instanceof Authenticator_Factory_Interface) {
                    throw new Invalid_Configuration_Exception(\sprintf('Authenticator factory "%s" ("%s") must implement "%s".', get_debug_type($factory), $key, Authenticator_Factory_Interface::class));
                }
                if (null === $user_provider && !$factory instanceof Stateless_Authenticator_Factory_Interface) {
                    $user_provider = $this->create_missing_user_provider($container, $id, $key);
                }
                $authenticators = $factory->create_authenticator($container, $id, $firewall[$key], $user_provider);
                if (\is_array($authenticators)) {
                    foreach ($authenticators as $authenticator) {
                        $authentication_providers[$authenticator] = $authenticator;
                        $entry_points[] = $authenticator;
                    }
                } else {
                    $authentication_providers[$authenticators] = $authenticators;
                    $entry_points[$key] = $authenticators;
                }
                if ($factory instanceof Firewall_Listener_Factory_Interface) {
                    $firewall_listener_ids = $factory->create_listeners($container, $id, $firewall[$key]);
                    foreach ($firewall_listener_ids as $firewall_listener_id) {
                        $listeners[] = new Reference($firewall_listener_id);
                    }
                }
            }
        }
        if ($container->has_definition('debug.security.firewall')) {
            foreach ($authentication_providers as &$authenticator_id) {
                $traceable_id = 'debug.' . $authenticator_id;
                $container->register($traceable_id, Traceable_Authenticator::class)->set_arguments([new Reference($authenticator_id)]);
                $authenticator_id = $traceable_id;
            }
        }
        // the actual entry point is configured by the RegisterEntryPointPass
        $container->set_parameter('security.' . $id . '._indexed_authenticators', $entry_points);
        return [$listeners, $default_entry_point];
    }
    private function get_user_provider(Container_Builder $container, string $id, array $firewall, string $factory_key, ?string $default_provider, array $provider_ids): ?string
    {
        if (isset($firewall[$factory_key]['provider'])) {
            if (!isset($provider_ids[$normalized_name = str_replace('-', '_', $firewall[$factory_key]['provider'])])) {
                throw new Invalid_Configuration_Exception(\sprintf('Invalid firewall "%s": user provider "%s" not found.', $id, $firewall[$factory_key]['provider']));
            }
            return $provider_ids[$normalized_name];
        }
        if ($default_provider) {
            return $default_provider;
        }
        if (!$provider_ids) {
            if ($firewall['stateless'] ?? false) {
                return null;
            }
            return $this->create_missing_user_provider($container, $id, $factory_key);
        }
        if ('remember_me' === $factory_key) {
            return 'security.user_providers';
        }
        throw new Invalid_Configuration_Exception(\sprintf('Not configuring explicitly the provider for the "%s" authenticator on "%s" firewall is ambiguous as there is more than one registered provider. Set the "provider" key to one of the configured providers, even if your custom authenticators don\'t use it.', $factory_key, $id));
    }
    private function create_missing_user_provider(Container_Builder $container, string $id, string $factory_key): string
    {
        $user_provider = \sprintf('security.user.provider.missing.%s', $factory_key);
        $container->set_definition($user_provider, (new Child_Definition('security.user.provider.missing'))->replace_argument(0, $id));
        return $user_provider;
    }
    private function create_hashers(array $hashers, Container_Builder $container): void
    {
        $hasher_map = [];
        foreach ($hashers as $class => $hasher) {
            $hasher_map[$class] = $this->create_hasher($hasher);
            // The key is not a class, so we register an alias for argument to
            // ease getting the hasher
            if (!class_exists($class) && !interface_exists($class, false)) {
                $id = 'security.password_hasher.' . $class;
                $container->register($id, Password_Hasher_Interface::class)->set_factory([new Reference('security.password_hasher_factory'), 'getPasswordHasher'])->set_argument(0, $class);
                $container->register_alias_for_argument($id, Password_Hasher_Interface::class, $class);
            }
        }
        $container->get_definition('security.password_hasher_factory')->set_arguments([$hasher_map]);
    }
    /**
     * @param array<string, mixed> $config
     *
     * @return Reference|array<string, mixed>
     */
    private function create_hasher(array $config): Reference|array
    {
        // a custom hasher service
        if (isset($config['id'])) {
            return $config['migrate_from'] ?? false ? ['instance' => new Reference($config['id']), 'migrate_from' => $config['migrate_from']] : new Reference($config['id']);
        }
        if ($config['migrate_from'] ?? false) {
            return $config;
        }
        // plaintext hasher
        if ('plaintext' === $config['algorithm']) {
            $arguments = [$config['ignore_case']];
            return ['class' => Plaintext_Password_Hasher::class, 'arguments' => $arguments];
        }
        // pbkdf2 hasher
        if ('pbkdf2' === $config['algorithm']) {
            return ['class' => Pbkdf2password_Hasher::class, 'arguments' => [$config['hash_algorithm'], $config['encode_as_base64'], $config['iterations'], $config['key_length']]];
        }
        // bcrypt hasher
        if ('bcrypt' === $config['algorithm']) {
            $config['algorithm'] = 'native';
            $config['native_algorithm'] = \PASSWORD_BCRYPT;
            return $this->create_hasher($config);
        }
        // Argon2i hasher
        if ('argon2i' === $config['algorithm']) {
            if (Sodium_Password_Hasher::is_supported() && !\defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) {
                $config['algorithm'] = 'sodium';
            } elseif (\defined('PASSWORD_ARGON2I')) {
                $config['algorithm'] = 'native';
                $config['native_algorithm'] = \PASSWORD_ARGON2I;
            } else {
                throw new Invalid_Configuration_Exception(\sprintf('Algorithm "argon2i" is not available; use "%s" instead.', \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13') ? 'argon2id" or "auto' : 'auto'));
            }
            return $this->create_hasher($config);
        }
        if ('argon2id' === $config['algorithm']) {
            if (($has_sodium = Sodium_Password_Hasher::is_supported()) && \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) {
                $config['algorithm'] = 'sodium';
            } elseif (\defined('PASSWORD_ARGON2ID')) {
                $config['algorithm'] = 'native';
                $config['native_algorithm'] = \PASSWORD_ARGON2ID;
            } else {
                throw new Invalid_Configuration_Exception(\sprintf('Algorithm "argon2id" is not available; use "%s" or libsodium 1.0.15+ instead.', \defined('PASSWORD_ARGON2I') || $has_sodium ? 'argon2i", "auto' : 'auto'));
            }
            return $this->create_hasher($config);
        }
        if ('native' === $config['algorithm']) {
            return ['class' => Native_Password_Hasher::class, 'arguments' => [$config['time_cost'], ($config['memory_cost'] ?? 0) << 10 ?: null, $config['cost']] + (isset($config['native_algorithm']) ? [3 => $config['native_algorithm']] : [])];
        }
        if ('sodium' === $config['algorithm']) {
            if (!Sodium_Password_Hasher::is_supported()) {
                throw new Invalid_Configuration_Exception('Libsodium is not available. Install the sodium extension or use "auto" instead.');
            }
            return ['class' => Sodium_Password_Hasher::class, 'arguments' => [$config['time_cost'], ($config['memory_cost'] ?? 0) << 10 ?: null]];
        }
        // run-time configured hasher
        return $config;
    }
    // Parses user providers and returns an array of their ids
    private function create_user_providers(array $config, Container_Builder $container): array
    {
        $provider_ids = [];
        foreach ($config['providers'] as $name => $provider) {
            $id = $this->create_user_dao_provider($name, $provider, $container);
            $provider_ids[str_replace('-', '_', $name)] = $id;
        }
        return $provider_ids;
    }
    // Parses a <provider> tag and returns the id for the related user provider service
    private function create_user_dao_provider(string $name, array $provider, Container_Builder $container): string
    {
        $name = $this->get_user_provider_id($name);
        // Doctrine Entity and In-memory DAO provider are managed by factories
        foreach ($this->user_provider_factories as $factory) {
            $key = str_replace('-', '_', $factory->get_key());
            if (!empty($provider[$key])) {
                $factory->create($container, $name, $provider[$key]);
                return $name;
            }
        }
        // Existing DAO service provider
        if (isset($provider['id'])) {
            $container->set_alias($name, new Alias($provider['id'], false));
            return $provider['id'];
        }
        // Chain provider
        if (isset($provider['chain'])) {
            $providers = [];
            foreach ($provider['chain']['providers'] as $provider_name) {
                $providers[] = new Reference($this->get_user_provider_id($provider_name));
            }
            $container->set_definition($name, new Child_Definition('security.user.provider.chain'))->add_argument(new Iterator_Argument($providers));
            return $name;
        }
        throw new Invalid_Configuration_Exception(\sprintf('Unable to create definition for "%s" user provider.', $name));
    }
    private function get_user_provider_id(string $name): string
    {
        return 'security.user.provider.concrete.' . strtolower($name);
    }
    private function create_exception_listener(Container_Builder $container, array $config, string $id, ?string $default_entry_point, bool $stateless): string
    {
        $exception_listener_id = 'security.exception_listener.' . $id;
        $listener = $container->set_definition($exception_listener_id, new Child_Definition('security.exception_listener'));
        $listener->replace_argument(3, $id);
        $listener->replace_argument(4, null === $default_entry_point ? null : new Reference($default_entry_point));
        $listener->replace_argument(8, $stateless);
        // access denied handler setup
        if (isset($config['access_denied_handler'])) {
            $listener->replace_argument(6, new Reference($config['access_denied_handler']));
        } elseif (isset($config['access_denied_url'])) {
            $listener->replace_argument(5, $config['access_denied_url']);
        }
        return $exception_listener_id;
    }
    private function create_switch_user_listener(Container_Builder $container, string $id, array $config, ?string $default_provider, bool $stateless): string
    {
        $user_provider = isset($config['provider']) ? $this->get_user_provider_id($config['provider']) : $default_provider;
        if (!$user_provider) {
            throw new Invalid_Configuration_Exception(\sprintf('Not configuring explicitly the provider for the "switch_user" listener on "%s" firewall is ambiguous as there is more than one registered provider.', $id));
        }
        if ($stateless && null !== $config['target_route']) {
            throw new Invalid_Configuration_Exception(\sprintf('Cannot set a "target_route" for the "switch_user" listener on the "%s" firewall as it is stateless.', $id));
        }
        $switch_user_listener_id = 'security.authentication.switchuser_listener.' . $id;
        $listener = $container->set_definition($switch_user_listener_id, new Child_Definition('security.authentication.switchuser_listener'));
        $listener->replace_argument(1, new Reference($user_provider));
        $listener->replace_argument(2, new Reference('security.user_checker.' . $id));
        $listener->replace_argument(3, $id);
        $listener->replace_argument(6, $config['parameter']);
        $listener->replace_argument(7, $config['role']);
        $listener->replace_argument(9, $stateless);
        $listener->replace_argument(11, $config['target_route']);
        return $switch_user_listener_id;
    }
    private function create_expression(Container_Builder $container, string $expression): Reference
    {
        if (isset($this->expressions[$id = '.security.expression.' . Container_Builder::hash($expression)])) {
            return $this->expressions[$id];
        }
        if (!$container::will_be_available('symfony/expression-language', Expression_Language::class, ['symfony/security-bundle'])) {
            throw new \RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
        }
        $container->register($id, Expression::class)->add_argument($expression);
        return $this->expressions[$id] = new Reference($id);
    }
    private function create_request_matcher(Container_Builder $container, ?string $path = null, ?string $host = null, ?int $port = null, array $methods = [], ?array $ips = null, array $attributes = []): Reference
    {
        if ($methods) {
            $methods = array_map(strtoupper(...), $methods);
        }
        if ($ips) {
            foreach ($ips as $ip) {
                $container->resolve_env_placeholders($ip, null, $used_envs);
                if (!$used_envs && !$this->is_valid_ips($ip)) {
                    throw new \LogicException(\sprintf('The given value "%s" in the "security.access_control" config option is not a valid IP address.', $ip));
                }
                $used_envs = null;
            }
        }
        $id = '.security.request_matcher.' . Container_Builder::hash([Chain_Request_Matcher::class, $path, $host, $port, $methods, $ips, $attributes]);
        if (isset($this->request_matchers[$id])) {
            return $this->request_matchers[$id];
        }
        $arguments = [];
        if ($methods) {
            if (!$container->has_definition($lid = '.security.request_matcher.' . Container_Builder::hash([Method_Request_Matcher::class, $methods]))) {
                $container->register($lid, Method_Request_Matcher::class)->set_arguments([$methods]);
            }
            $arguments[] = new Reference($lid);
        }
        if ($path) {
            if (!$container->has_definition($lid = '.security.request_matcher.' . Container_Builder::hash([Path_Request_Matcher::class, $path]))) {
                $container->register($lid, Path_Request_Matcher::class)->set_arguments([$path]);
            }
            $arguments[] = new Reference($lid);
        }
        if ($host) {
            if (!$container->has_definition($lid = '.security.request_matcher.' . Container_Builder::hash([Host_Request_Matcher::class, $host]))) {
                $container->register($lid, Host_Request_Matcher::class)->set_arguments([$host]);
            }
            $arguments[] = new Reference($lid);
        }
        if ($ips) {
            if (!$container->has_definition($lid = '.security.request_matcher.' . Container_Builder::hash([Ips_Request_Matcher::class, $ips]))) {
                $container->register($lid, Ips_Request_Matcher::class)->set_arguments([$ips]);
            }
            $arguments[] = new Reference($lid);
        }
        if ($attributes) {
            if (!$container->has_definition($lid = '.security.request_matcher.' . Container_Builder::hash([Attributes_Request_Matcher::class, $attributes]))) {
                $container->register($lid, Attributes_Request_Matcher::class)->set_arguments([$attributes]);
            }
            $arguments[] = new Reference($lid);
        }
        if ($port) {
            if (!$container->has_definition($lid = '.security.request_matcher.' . Container_Builder::hash([Port_Request_Matcher::class, $port]))) {
                $container->register($lid, Port_Request_Matcher::class)->set_arguments([$port]);
            }
            $arguments[] = new Reference($lid);
        }
        $container->register($id, Chain_Request_Matcher::class)->set_arguments([$arguments]);
        return $this->request_matchers[$id] = new Reference($id);
    }
    public function add_authenticator_factory(Authenticator_Factory_Interface $factory): void
    {
        $this->factories[] = [$factory->get_priority(), $factory];
        $this->sorted_factories = [];
    }
    public function add_user_provider_factory(User_Provider_Factory_Interface $factory): void
    {
        $this->user_provider_factories[] = $factory;
    }
    public function get_configuration(array $config, Container_Builder $container): ?Configuration_Interface
    {
        // first assemble the factories
        return new Main_Configuration($this->get_sorted_factories(), $this->user_provider_factories);
    }
    private function is_valid_ips(string|array $ips): bool
    {
        $ips_list = array_reduce((array) $ips, static fn($ips, $ip): array => array_merge($ips, preg_split('/\s*,\s*/', (string) $ip)), []);
        if (!$ips_list) {
            return false;
        }
        foreach ($ips_list as $cidr) {
            if (!$this->is_valid_ip($cidr)) {
                return false;
            }
        }
        return true;
    }
    private function is_valid_ip(string $cidr): bool
    {
        $cidr_parts = explode('/', $cidr);
        if (1 === \count($cidr_parts)) {
            return false !== filter_var($cidr_parts[0], \FILTER_VALIDATE_IP);
        }
        $ip = $cidr_parts[0];
        $netmask = $cidr_parts[1];
        if (!ctype_digit($netmask)) {
            return false;
        }
        if (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return $netmask <= 32;
        }
        if (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            return $netmask <= 128;
        }
        return false;
    }
    /**
     * @return array<int, AuthenticatorFactoryInterface>
     */
    private function get_sorted_factories(): array
    {
        if (!$this->sorted_factories) {
            $factories = [];
            foreach ($this->factories as $i => $factory) {
                $factories[] = array_merge($factory, [$i]);
            }
            usort($factories, static fn(array $a, array $b): int => $b[0] <=> $a[0] ?: $a[2] <=> $b[2]);
            $this->sorted_factories = array_column($factories, 1);
        }
        return $this->sorted_factories;
    }
    private function create_logout_uris_parameter(array $firewalls_config, Container_Builder $container): void
    {
        $logout_uris = [];
        foreach ($firewalls_config as $name => $config) {
            if (!$logout_path = $config['logout']['path'] ?? null) {
                continue;
            }
            if ('/' === $logout_path[0]) {
                $logout_uris[$name] = $logout_path;
            }
        }
        $container->set_parameter('security.logout_uris', $logout_uris);
    }
}