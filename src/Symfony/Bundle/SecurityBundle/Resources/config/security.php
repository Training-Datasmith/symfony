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

use Symfony\Bundle\Security_Bundle\Cache_Warmer\Expression_Cache_Warmer;
use Symfony\Bundle\Security_Bundle\Event_Listener\Firewall_Listener;
use Symfony\Bundle\Security_Bundle\Routing\Logout_Route_Loader;
use Symfony\Bundle\Security_Bundle\Security;
use Symfony\Bundle\Security_Bundle\Security\Firewall_Config;
use Symfony\Bundle\Security_Bundle\Security\Firewall_Context;
use Symfony\Bundle\Security_Bundle\Security\Firewall_Map;
use Symfony\Bundle\Security_Bundle\Security\Lazy_Firewall_Context;
use Symfony\Component\Dependency_Injection\Service_Locator;
use Symfony\Component\Expression_Language\Expression_Language as BaseExpressionLanguage;
use Symfony\Component\Ldap\Security\Ldap_User_Provider;
use Symfony\Component\Security\Core\Authentication\Authentication_Trust_Resolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\Token_Storage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\Token_Storage_Interface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\Usage_Tracking_Token_Storage;
use Symfony\Component\Security\Core\Authorization\Access_Decision_Manager;
use Symfony\Component\Security\Core\Authorization\Access_Decision_Manager_Interface;
use Symfony\Component\Security\Core\Authorization\Authorization_Checker;
use Symfony\Component\Security\Core\Authorization\Authorization_Checker_Interface;
use Symfony\Component\Security\Core\Authorization\Expression_Language;
use Symfony\Component\Security\Core\Authorization\User_Authorization_Checker_Interface;
use Symfony\Component\Security\Core\Authorization\Voter\Authenticated_Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Closure_Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Expression_Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Role_Hierarchy_Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Role_Voter;
use Symfony\Component\Security\Core\Role\Role_Hierarchy;
use Symfony\Component\Security\Core\Role\Role_Hierarchy_Interface;
use Symfony\Component\Security\Core\User\Chain_User_Provider;
use Symfony\Component\Security\Core\User\In_Memory_User_Checker;
use Symfony\Component\Security\Core\User\In_Memory_User_Provider;
use Symfony\Component\Security\Core\User\Missing_User_Provider;
use Symfony\Component\Security\Core\Validator\Constraints\User_Password_Validator;
use Symfony\Component\Security\Http\Authentication\Authentication_Utils;
use Symfony\Component\Security\Http\Controller\Security_Token_Value_Resolver;
use Symfony\Component\Security\Http\Controller\User_Value_Resolver;
use Symfony\Component\Security\Http\Event_Listener\Is_Granted_Attribute_Listener;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\Firewall_Map_Interface;
use Symfony\Component\Security\Http\Http_Utils;
use Symfony\Component\Security\Http\Impersonate\Impersonate_Url_Generator;
use Symfony\Component\Security\Http\Logout\Logout_Url_Generator;
use Symfony\Component\Security\Http\Session\Session_Authentication_Strategy;
use Symfony\Component\Security\Http\Session\Session_Authentication_Strategy_Interface;
return static function (Container_Configurator $container): void {
    $container->parameters()->set('security.role_hierarchy.roles', []);
    $container->services()->set('security.authorization_checker', Authorization_Checker::class)->args([service('security.token_storage'), service('security.access.decision_manager')])->alias(Authorization_Checker_Interface::class, 'security.authorization_checker')->alias(User_Authorization_Checker_Interface::class, 'security.authorization_checker')->set('security.token_storage', Usage_Tracking_Token_Storage::class)->args([service('security.untracked_token_storage'), service_locator(['request_stack' => service('request_stack')])])->tag('kernel.reset', ['method' => 'disableUsageTracking'])->tag('kernel.reset', ['method' => 'setToken'])->alias(Token_Storage_Interface::class, 'security.token_storage')->set('security.untracked_token_storage', Token_Storage::class)->set('security.helper', Security::class)->args([service_locator(['security.token_storage' => service('security.token_storage'), 'security.authorization_checker' => service('security.authorization_checker'), 'security.user_authorization_checker' => service('security.authorization_checker'), 'security.authenticator.managers_locator' => service('security.authenticator.managers_locator')->ignore_on_invalid(), 'request_stack' => service('request_stack'), 'security.firewall.map' => service('security.firewall.map'), 'security.user_checker_locator' => service('security.user_checker_locator'), 'security.firewall.event_dispatcher_locator' => service('security.firewall.event_dispatcher_locator'), 'security.csrf.token_manager' => service('security.csrf.token_manager')->ignore_on_invalid()]), abstract_arg('authenticators')])->alias(Security::class, 'security.helper')->set('security.user_value_resolver', User_Value_Resolver::class)->args([service('security.token_storage')])->tag('controller.argument_value_resolver', ['priority' => 120, 'name' => User_Value_Resolver::class])->set('security.security_token_value_resolver', Security_Token_Value_Resolver::class)->args([service('security.token_storage')])->tag('controller.argument_value_resolver', ['priority' => 120, 'name' => Security_Token_Value_Resolver::class])->set('security.authentication.trust_resolver', Authentication_Trust_Resolver::class)->set('security.authentication.session_strategy', Session_Authentication_Strategy::class)->args([param('security.authentication.session_strategy.strategy'), service('security.csrf.token_storage')->ignore_on_invalid()])->alias(Session_Authentication_Strategy_Interface::class, 'security.authentication.session_strategy')->set('security.authentication.session_strategy_noop', Session_Authentication_Strategy::class)->args(['none'])->set('security.user_checker', In_Memory_User_Checker::class)->set('security.user_checker_locator', Service_Locator::class)->args([[]])->set('security.expression_language', Expression_Language::class)->args([service('cache.security_expression_language')->null_on_invalid()])->set('security.authentication_utils', Authentication_Utils::class)->args([service('request_stack')])->alias(Authentication_Utils::class, 'security.authentication_utils')->set('security.access.decision_manager', Access_Decision_Manager::class)->args([[]])->alias(Access_Decision_Manager_Interface::class, 'security.access.decision_manager')->set('security.role_hierarchy', Role_Hierarchy::class)->args([param('security.role_hierarchy.roles')])->alias(Role_Hierarchy_Interface::class, 'security.role_hierarchy')->set('security.access.simple_role_voter', Role_Voter::class)->tag('security.voter', ['priority' => 245])->set('security.access.authenticated_voter', Authenticated_Voter::class)->args([service('security.authentication.trust_resolver')])->tag('security.voter', ['priority' => 250])->set('security.access.role_hierarchy_voter', Role_Hierarchy_Voter::class)->args([service('security.role_hierarchy')])->tag('security.voter', ['priority' => 245])->set('security.access.expression_voter', Expression_Voter::class)->args([service('security.expression_language'), service('security.authentication.trust_resolver'), service('security.authorization_checker'), service('security.role_hierarchy')->null_on_invalid()])->tag('security.voter', ['priority' => 245])->set('security.access.closure_voter', Closure_Voter::class)->args([service('security.authorization_checker')])->tag('security.voter', ['priority' => 245])->set('security.impersonate_url_generator', Impersonate_Url_Generator::class)->args([service('request_stack'), service('security.firewall.map'), service('security.token_storage')])->set('security.firewall', Firewall_Listener::class)->args([service('security.firewall.map'), service('event_dispatcher'), service('security.logout_url_generator')])->tag('kernel.event_subscriber')->alias(Firewall::class, 'security.firewall')->set('security.firewall.map', Firewall_Map::class)->args([abstract_arg('Firewall context locator'), abstract_arg('Request matchers')])->alias(Firewall_Map_Interface::class, 'security.firewall.map')->set('security.firewall.context', Firewall_Context::class)->abstract()->args([[], service('security.exception_listener'), abstract_arg('LogoutListener'), abstract_arg('FirewallConfig')])->set('security.firewall.lazy_context', Lazy_Firewall_Context::class)->abstract()->args([[], service('security.exception_listener'), abstract_arg('LogoutListener'), abstract_arg('FirewallConfig'), service('security.untracked_token_storage')])->set('security.firewall.config', Firewall_Config::class)->abstract()->args([
        abstract_arg('name'),
        abstract_arg('user_checker'),
        abstract_arg('request_matcher'),
        false,
        // security enabled
        false,
        // stateless
        null,
        null,
        null,
        null,
        null,
        [],
        // listeners
        null,
        // switch_user
        null,
    ])->set('security.logout_url_generator', Logout_Url_Generator::class)->args([service('request_stack')->null_on_invalid(), service('router')->null_on_invalid(), service('security.token_storage')->null_on_invalid()])->set('security.route_loader.logout', Logout_Route_Loader::class)->args(['%security.logout_uris%', 'security.logout_uris'])->tag('routing.route_loader')->set('security.user.provider.missing', Missing_User_Provider::class)->abstract()->args([abstract_arg('firewall')])->set('security.user.provider.in_memory', In_Memory_User_Provider::class)->abstract()->set('security.user.provider.ldap', Ldap_User_Provider::class)->abstract()->args([abstract_arg('security.ldap.ldap'), abstract_arg('base dn'), abstract_arg('search dn'), abstract_arg('search password'), abstract_arg('default_roles'), abstract_arg('uid key'), abstract_arg('filter'), abstract_arg('password_attribute'), abstract_arg('extra_fields (email etc)')])->set('security.user.provider.chain', Chain_User_Provider::class)->abstract()->set('security.http_utils', Http_Utils::class)->args([service('router')->null_on_invalid(), service('router')->null_on_invalid()])->alias(Http_Utils::class, 'security.http_utils')->set('security.validator.user_password', User_Password_Validator::class)->args([service('security.token_storage'), service('security.password_hasher_factory')])->tag('validator.constraint_validator', ['alias' => 'security.validator.user_password'])->set('cache.security_expression_language')->parent('cache.system')->private()->tag('cache.pool')->set('security.cache_warmer.expression', Expression_Cache_Warmer::class)->args([[], service('security.expression_language')])->tag('kernel.cache_warmer')->set('controller.is_granted_attribute_listener', Is_Granted_Attribute_Listener::class)->args([service('security.authorization_checker'), service('security.is_granted_attribute_expression_language')->null_on_invalid()])->tag('kernel.event_subscriber')->set('security.is_granted_attribute_expression_language', Base_Expression_Language::class)->args([service('cache.security_is_granted_attribute_expression_language')->null_on_invalid()])->set('cache.security_is_granted_attribute_expression_language')->parent('cache.system')->private()->tag('cache.pool')->set('security.is_csrf_token_valid_attribute_expression_language', Base_Expression_Language::class)->args([service('cache.security_is_csrf_token_valid_attribute_expression_language')->null_on_invalid()])->set('cache.security_is_csrf_token_valid_attribute_expression_language')->parent('cache.system')->private()->tag('cache.pool');
};