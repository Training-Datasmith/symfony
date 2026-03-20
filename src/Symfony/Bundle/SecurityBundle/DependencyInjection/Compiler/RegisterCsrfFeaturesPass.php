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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Security\Csrf\Token_Storage\Clearable_Token_Storage_Interface;
use Symfony\Component\Security\Http\Event_Listener\Csrf_Protection_Listener;
use Symfony\Component\Security\Http\Event_Listener\Csrf_Token_Clearing_Logout_Listener;
use Symfony\Component\Security\Http\Event_Listener\Is_Csrf_Token_Valid_Attribute_Listener;
/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
class Register_Csrf_Features_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        $this->register_csrf_protection_listener($container);
        $this->register_logout_handler($container);
    }
    private function register_csrf_protection_listener(Container_Builder $container): void
    {
        if (!$container->has_definition('cache.system')) {
            $container->remove_definition('cache.security_is_csrf_token_valid_attribute_expression_language');
        }
        if (!$container->has('security.authenticator.manager') || !$container->has('security.csrf.token_manager')) {
            return;
        }
        $container->register('security.listener.csrf_protection', Csrf_Protection_Listener::class)->add_argument(new Reference('security.csrf.token_manager'))->add_tag('kernel.event_subscriber');
        $container->register('controller.is_csrf_token_valid_attribute_listener', Is_Csrf_Token_Valid_Attribute_Listener::class)->add_argument(new Reference('security.csrf.token_manager'))->add_argument(new Reference('security.is_csrf_token_valid_attribute_expression_language', Container_Interface::NULL_ON_INVALID_REFERENCE))->add_tag('kernel.event_subscriber');
    }
    protected function register_logout_handler(Container_Builder $container): void
    {
        if (!$container->has('security.logout_listener') || !$container->has('security.csrf.token_storage')) {
            return;
        }
        $csrf_token_storage = $container->find_definition('security.csrf.token_storage');
        $csrf_token_storage_class = $container->get_parameter_bag()->resolve_value($csrf_token_storage->get_class());
        if (!is_subclass_of($csrf_token_storage_class, Clearable_Token_Storage_Interface::class)) {
            return;
        }
        $container->register('security.logout.listener.csrf_token_clearing', Csrf_Token_Clearing_Logout_Listener::class)->add_argument(new Reference('security.csrf.token_storage'))->add_tag('kernel.event_subscriber');
    }
}