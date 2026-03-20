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

use Symfony\Bridge\Twig\Extension\Csrf_Extension;
use Symfony\Bridge\Twig\Extension\Csrf_Runtime;
use Symfony\Component\Security\Csrf\Csrf_Token_Manager;
use Symfony\Component\Security\Csrf\Csrf_Token_Manager_Interface;
use Symfony\Component\Security\Csrf\Same_Origin_Csrf_Listener;
use Symfony\Component\Security\Csrf\Same_Origin_Csrf_Token_Manager;
use Symfony\Component\Security\Csrf\Token_Generator\Token_Generator_Interface;
use Symfony\Component\Security\Csrf\Token_Generator\Uri_Safe_Token_Generator;
use Symfony\Component\Security\Csrf\Token_Storage\Session_Token_Storage;
use Symfony\Component\Security\Csrf\Token_Storage\Token_Storage_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('security.csrf.token_generator', Uri_Safe_Token_Generator::class)->alias(Token_Generator_Interface::class, 'security.csrf.token_generator')->set('security.csrf.token_storage', Session_Token_Storage::class)->args([service('request_stack')])->alias(Token_Storage_Interface::class, 'security.csrf.token_storage')->set('security.csrf.token_manager', Csrf_Token_Manager::class)->args([service('security.csrf.token_generator'), service('security.csrf.token_storage'), service('request_stack')->ignore_on_invalid()])->alias(Csrf_Token_Manager_Interface::class, 'security.csrf.token_manager')->set('twig.runtime.security_csrf', Csrf_Runtime::class)->args([service('security.csrf.token_manager')])->tag('twig.runtime')->set('twig.extension.security_csrf', Csrf_Extension::class)->tag('twig.extension')->set('security.csrf.same_origin_token_manager', Same_Origin_Csrf_Token_Manager::class)->decorate('security.csrf.token_manager')->args([service('request_stack'), service('logger')->null_on_invalid(), service('.inner'), abstract_arg('framework.csrf_protection.stateless_token_ids'), abstract_arg('framework.csrf_protection.check_header'), abstract_arg('framework.csrf_protection.cookie_name')])->tag('monolog.logger', ['channel' => 'request'])->set('security.csrf.same_origin_listener', Same_Origin_Csrf_Listener::class)->args([abstract_arg('framework.csrf_protection.cookie_name')])->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);
};