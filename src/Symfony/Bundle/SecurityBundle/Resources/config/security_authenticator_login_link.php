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

use Symfony\Bundle\Security_Bundle\Login_Link\Firewall_Aware_Login_Link_Handler;
use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Security\Core\Signature\Expired_Signature_Storage;
use Symfony\Component\Security\Core\Signature\Signature_Hasher;
use Symfony\Component\Security\Http\Authenticator\Login_Link_Authenticator;
use Symfony\Component\Security\Http\Login_Link\Login_Link_Handler;
use Symfony\Component\Security\Http\Login_Link\Login_Link_Handler_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('security.authenticator.login_link', Login_Link_Authenticator::class)->abstract()->args([abstract_arg('the login link handler instance'), service('security.http_utils'), abstract_arg('authentication success handler'), abstract_arg('authentication failure handler'), abstract_arg('options')])->set('security.authenticator.abstract_login_link_handler', Login_Link_Handler::class)->abstract()->args([service('router'), abstract_arg('user provider'), abstract_arg('signature hasher'), abstract_arg('options')])->set('security.authenticator.abstract_login_link_signature_hasher', Signature_Hasher::class)->args([service('property_accessor'), abstract_arg('signature properties'), new Parameter('kernel.secret'), abstract_arg('expired signature storage'), abstract_arg('max signature uses')])->set('security.authenticator.expired_login_link_storage', Expired_Signature_Storage::class)->abstract()->args([abstract_arg('cache pool service'), abstract_arg('expired login link storage')])->set('security.authenticator.cache.expired_links')->parent('cache.app')->private()->set('security.authenticator.firewall_aware_login_link_handler', Firewall_Aware_Login_Link_Handler::class)->args([service('security.firewall.map'), tagged_locator('security.authenticator.login_linker', 'firewall'), service('request_stack')])->alias(Login_Link_Handler_Interface::class, 'security.authenticator.firewall_aware_login_link_handler');
};