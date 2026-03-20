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

use Symfony\Bundle\Security_Bundle\Remember_Me\Firewall_Aware_Remember_Me_Handler;
use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Security\Core\Signature\Signature_Hasher;
use Symfony\Component\Security\Http\Authenticator\Remember_Me_Authenticator;
use Symfony\Component\Security\Http\Event_Listener\Check_Remember_Me_Conditions_Listener;
use Symfony\Component\Security\Http\Event_Listener\Remember_Me_Listener;
use Symfony\Component\Security\Http\Remember_Me\Persistent_Remember_Me_Handler;
use Symfony\Component\Security\Http\Remember_Me\Remember_Me_Handler_Interface;
use Symfony\Component\Security\Http\Remember_Me\Response_Listener;
use Symfony\Component\Security\Http\Remember_Me\Signature_Remember_Me_Handler;
return static function (Container_Configurator $container): void {
    $container->services()->set('security.rememberme.response_listener', Response_Listener::class)->tag('kernel.event_subscriber')->set('security.authenticator.remember_me_signature_hasher', Signature_Hasher::class)->args([service('property_accessor'), abstract_arg('signature properties'), new Parameter('kernel.secret'), null, null])->set('security.authenticator.signature_remember_me_handler', Signature_Remember_Me_Handler::class)->abstract()->args([abstract_arg('signature hasher'), abstract_arg('user provider'), service('request_stack'), abstract_arg('options'), service('logger')->null_on_invalid()])->tag('monolog.logger', ['channel' => 'security'])->set('security.authenticator.persistent_remember_me_handler', Persistent_Remember_Me_Handler::class)->abstract()->args([abstract_arg('token provider'), abstract_arg('user provider'), service('request_stack'), abstract_arg('options'), service('logger')->null_on_invalid(), abstract_arg('token verifier')])->tag('monolog.logger', ['channel' => 'security'])->set('security.authenticator.firewall_aware_remember_me_handler', Firewall_Aware_Remember_Me_Handler::class)->args([service('security.firewall.map'), tagged_locator('security.remember_me_handler', 'firewall'), service('request_stack')])->alias(Remember_Me_Handler_Interface::class, 'security.authenticator.firewall_aware_remember_me_handler')->set('security.listener.check_remember_me_conditions', Check_Remember_Me_Conditions_Listener::class)->abstract()->args([abstract_arg('options'), service('logger')->null_on_invalid()])->set('security.listener.remember_me', Remember_Me_Listener::class)->abstract()->args([abstract_arg('remember me handler'), service('logger')->null_on_invalid()])->tag('monolog.logger', ['channel' => 'security'])->set('security.authenticator.remember_me', Remember_Me_Authenticator::class)->abstract()->args([abstract_arg('remember me handler'), service('security.token_storage'), abstract_arg('options'), service('logger')->null_on_invalid()])->tag('monolog.logger', ['channel' => 'security'])->set('cache.security_token_verifier')->parent('cache.system')->private()->tag('cache.pool');
};