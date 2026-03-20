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

use Symfony\Component\Dependency_Injection\Service_Locator;
use Symfony\Component\Security\Http\Access_Map;
use Symfony\Component\Security\Http\Authentication\Custom_Authentication_Failure_Handler;
use Symfony\Component\Security\Http\Authentication\Custom_Authentication_Success_Handler;
use Symfony\Component\Security\Http\Authentication\Default_Authentication_Failure_Handler;
use Symfony\Component\Security\Http\Authentication\Default_Authentication_Success_Handler;
use Symfony\Component\Security\Http\Event_Listener\Clear_Site_Data_Logout_Listener;
use Symfony\Component\Security\Http\Event_Listener\Cookie_Clearing_Logout_Listener;
use Symfony\Component\Security\Http\Event_Listener\Default_Logout_Listener;
use Symfony\Component\Security\Http\Event_Listener\Session_Logout_Listener;
use Symfony\Component\Security\Http\Firewall\Access_Listener;
use Symfony\Component\Security\Http\Firewall\Channel_Listener;
use Symfony\Component\Security\Http\Firewall\Context_Listener;
use Symfony\Component\Security\Http\Firewall\Exception_Listener;
use Symfony\Component\Security\Http\Firewall\Logout_Listener;
use Symfony\Component\Security\Http\Firewall\Switch_User_Listener;
return static function (Container_Configurator $container): void {
    $container->services()->set('security.channel_listener', Channel_Listener::class)->args([service('security.access_map'), service('logger')->null_on_invalid(), inline_service('int')->factory([service('router.request_context'), 'getHttpPort']), inline_service('int')->factory([service('router.request_context'), 'getHttpsPort'])])->tag('monolog.logger', ['channel' => 'security'])->set('security.access_map', Access_Map::class)->set('security.context_listener', Context_Listener::class)->args([service('security.untracked_token_storage'), [], abstract_arg('Provider Key'), service('logger')->null_on_invalid(), service('event_dispatcher')->null_on_invalid(), service('security.authentication.trust_resolver')])->tag('monolog.logger', ['channel' => 'security'])->set('security.logout_listener', Logout_Listener::class)->abstract()->args([service('security.token_storage'), service('security.http_utils'), abstract_arg('event dispatcher'), []])->set('security.logout.listener.session', Session_Logout_Listener::class)->abstract()->set('security.logout.listener.clear_site_data', Clear_Site_Data_Logout_Listener::class)->abstract()->set('security.logout.listener.cookie_clearing', Cookie_Clearing_Logout_Listener::class)->abstract()->set('security.logout.listener.default', Default_Logout_Listener::class)->abstract()->args([service('security.http_utils'), abstract_arg('target url')])->set('security.authentication.listener.abstract')->abstract()->args([service('security.token_storage'), service('security.authentication.manager'), service('security.authentication.session_strategy'), service('security.http_utils'), abstract_arg('Provider-shared Key'), service('security.authentication.success_handler'), service('security.authentication.failure_handler'), [], service('logger')->null_on_invalid(), service('event_dispatcher')->null_on_invalid()])->tag('monolog.logger', ['channel' => 'security'])->set('security.authentication.custom_success_handler', Custom_Authentication_Success_Handler::class)->abstract()->args([
        abstract_arg('The custom success handler service'),
        [],
        // Options
        abstract_arg('Provider-shared Key'),
    ])->set('security.authentication.success_handler', Default_Authentication_Success_Handler::class)->abstract()->args([
        service('security.http_utils'),
        [],
        // Options
        service('logger')->null_on_invalid(),
    ])->set('security.authentication.custom_failure_handler', Custom_Authentication_Failure_Handler::class)->abstract()->args([abstract_arg('The custom failure handler service'), []])->set('security.authentication.failure_handler', Default_Authentication_Failure_Handler::class)->abstract()->args([
        service('http_kernel'),
        service('security.http_utils'),
        [],
        // Options
        service('logger')->null_on_invalid(),
    ])->tag('monolog.logger', ['channel' => 'security'])->set('security.exception_listener', Exception_Listener::class)->abstract()->args([service('security.token_storage'), service('security.authentication.trust_resolver'), service('security.http_utils'), abstract_arg('Provider-shared Key'), service('security.authentication.entry_point')->null_on_invalid(), param('security.access.denied_url'), service('security.access.denied_handler')->null_on_invalid(), service('logger')->null_on_invalid(), false])->tag('monolog.logger', ['channel' => 'security'])->set('security.authentication.switchuser_listener', Switch_User_Listener::class)->abstract()->args([
        service('security.token_storage'),
        abstract_arg('User Provider'),
        abstract_arg('User Checker'),
        abstract_arg('Provider Key'),
        service('security.access.decision_manager'),
        service('logger')->null_on_invalid(),
        '_switch_user',
        'ROLE_ALLOWED_TO_SWITCH',
        service('event_dispatcher')->null_on_invalid(),
        false,
        // Stateless
        service('router')->null_on_invalid(),
        abstract_arg('Target Route'),
    ])->tag('monolog.logger', ['channel' => 'security'])->set('security.access_listener', Access_Listener::class)->args([service('security.token_storage'), service('security.access.decision_manager'), service('security.access_map')])->tag('monolog.logger', ['channel' => 'security'])->set('security.firewall.event_dispatcher_locator', Service_Locator::class)->args([[]]);
};