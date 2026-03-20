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

use Symfony\Bundle\Security_Bundle\Security\User_Authenticator;
use Symfony\Component\Dependency_Injection\Service_Locator;
use Symfony\Component\Security\Http\Authentication\Authenticator_Manager;
use Symfony\Component\Security\Http\Authentication\User_Authenticator_Interface;
use Symfony\Component\Security\Http\Authenticator\Form_Login_Authenticator;
use Symfony\Component\Security\Http\Authenticator\Http_Basic_Authenticator;
use Symfony\Component\Security\Http\Authenticator\Json_Login_Authenticator;
use Symfony\Component\Security\Http\Authenticator\Remote_User_Authenticator;
use Symfony\Component\Security\Http\Authenticator\X509Authenticator;
use Symfony\Component\Security\Http\Event\Check_Passport_Event;
use Symfony\Component\Security\Http\Event_Listener\Check_Credentials_Listener;
use Symfony\Component\Security\Http\Event_Listener\Login_Throttling_Listener;
use Symfony\Component\Security\Http\Event_Listener\Password_Migrating_Listener;
use Symfony\Component\Security\Http\Event_Listener\Session_Strategy_Listener;
use Symfony\Component\Security\Http\Event_Listener\User_Checker_Listener;
use Symfony\Component\Security\Http\Event_Listener\User_Provider_Listener;
use Symfony\Component\Security\Http\Firewall\Authenticator_Manager_Listener;
return static function (Container_Configurator $container): void {
    $container->services()->set('security.authenticator.manager', Authenticator_Manager::class)->abstract()->args([abstract_arg('authenticators'), service('security.token_storage'), service('event_dispatcher'), abstract_arg('provider key'), service('logger')->null_on_invalid(), param('security.authentication.manager.erase_credentials'), param('.security.authentication.expose_security_errors'), abstract_arg('required badges')])->tag('monolog.logger', ['channel' => 'security'])->set('security.authenticator.managers_locator', Service_Locator::class)->args([[]])->set('security.user_authenticator', User_Authenticator::class)->args([service('security.firewall.map'), service('security.authenticator.managers_locator'), service('request_stack')])->alias(User_Authenticator_Interface::class, 'security.user_authenticator')->set('security.firewall.authenticator', Authenticator_Manager_Listener::class)->abstract()->args([abstract_arg('authenticator manager')])->set('security.listener.check_authenticator_credentials', Check_Credentials_Listener::class)->args([service('security.password_hasher_factory')])->tag('kernel.event_subscriber')->set('security.listener.user_provider', User_Provider_Listener::class)->args([service('security.user_providers')])->tag('kernel.event_listener', ['event' => Check_Passport_Event::class, 'priority' => 1024, 'method' => 'checkPassport'])->set('security.listener.user_provider.abstract', User_Provider_Listener::class)->abstract()->args([abstract_arg('user provider')])->set('security.listener.password_migrating', Password_Migrating_Listener::class)->args([service('security.password_hasher_factory')])->tag('kernel.event_subscriber')->set('security.listener.user_checker', User_Checker_Listener::class)->abstract()->args([abstract_arg('user checker')])->set('security.listener.session', Session_Strategy_Listener::class)->abstract()->args([service('security.authentication.session_strategy')])->set('security.listener.login_throttling', Login_Throttling_Listener::class)->abstract()->args([service('request_stack'), abstract_arg('request rate limiter')])->set('security.authenticator.http_basic', Http_Basic_Authenticator::class)->abstract()->args([abstract_arg('realm name'), abstract_arg('user provider'), service('logger')->null_on_invalid()])->tag('monolog.logger', ['channel' => 'security'])->set('security.authenticator.form_login', Form_Login_Authenticator::class)->abstract()->args([service('security.http_utils'), abstract_arg('user provider'), abstract_arg('authentication success handler'), abstract_arg('authentication failure handler'), abstract_arg('options')])->set('security.authenticator.json_login', Json_Login_Authenticator::class)->abstract()->args([service('security.http_utils'), abstract_arg('user provider'), abstract_arg('authentication success handler'), abstract_arg('authentication failure handler'), abstract_arg('options'), service('property_accessor')->null_on_invalid()])->call('setTranslator', [service('translator')->ignore_on_invalid()])->set('security.authenticator.x509', X509Authenticator::class)->abstract()->args([abstract_arg('user provider'), service('security.token_storage'), abstract_arg('firewall name'), abstract_arg('user key'), abstract_arg('credentials key'), service('logger')->null_on_invalid(), abstract_arg('credentials user identifier')])->tag('monolog.logger', ['channel' => 'security'])->set('security.authenticator.remote_user', Remote_User_Authenticator::class)->abstract()->args([abstract_arg('user provider'), service('security.token_storage'), abstract_arg('firewall name'), abstract_arg('user key'), service('logger')->null_on_invalid()])->tag('monolog.logger', ['channel' => 'security']);
};