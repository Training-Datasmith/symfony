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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\Factory;

use Symfony\Component\Config\Definition\Builder\Node_Builder;
use Symfony\Component\Config\Definition\Builder\Node_Definition;
use Symfony\Component\Config\File_Locator;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Security\Http\Authentication\Authentication_Failure_Handler_Interface;
use Symfony\Component\Security\Http\Authentication\Authentication_Success_Handler_Interface;
/**
 * @internal
 */
class Login_Link_Factory extends Abstract_Factory
{
    public const PRIORITY = -20;
    public function add_configuration(Node_Definition $node): void
    {
        /** @var NodeBuilder $builder */
        $builder = $node->children();
        $builder->scalar_node('check_route')->is_required()->info('Route that will validate the login link - e.g. "app_login_link_verify".')->end()->scalar_node('check_post_only')->default_false()->info('If true, only HTTP POST requests to "check_route" will be handled by the authenticator.')->end()->array_node('signature_properties', 'signature_property')->is_required()->prototype('scalar')->end()->requires_at_least_one_element()->info('An array of properties on your User that are used to sign the link. If any of these change, all existing links will become invalid.')->example(['email', 'password'])->end()->integer_node('lifetime')->default_value(600)->info('The lifetime of the login link in seconds.')->end()->integer_node('max_uses')->default_null()->info('Max number of times a login link can be used - null means unlimited within lifetime.')->end()->scalar_node('used_link_cache')->info('Cache service id used to expired links of max_uses is set.')->end()->scalar_node('success_handler')->info(\sprintf('A service id that implements %s.', Authentication_Success_Handler_Interface::class))->end()->scalar_node('failure_handler')->info(\sprintf('A service id that implements %s.', Authentication_Failure_Handler_Interface::class))->end()->scalar_node('provider')->info('The user provider to load users from.')->end()->scalar_node('secret')->cannot_be_empty()->default_value('%kernel.secret%')->end();
        foreach (array_merge($this->default_success_handler_options, $this->default_failure_handler_options) as $name => $default) {
            if (\is_bool($default)) {
                $builder->boolean_node($name)->default_value($default);
            } else {
                $builder->scalar_node($name)->default_value($default);
            }
        }
    }
    public function get_key(): string
    {
        return 'login-link';
    }
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, string $user_provider_id): string
    {
        if (!$container->has_definition('security.authenticator.login_link')) {
            $loader = new Php_File_Loader($container, new File_Locator(\dirname(__DIR__) . '/../../Resources/config'));
            $loader->load('security_authenticator_login_link.php');
        }
        if (null !== $config['max_uses'] && !isset($config['used_link_cache'])) {
            $config['used_link_cache'] = 'security.authenticator.cache.expired_links';
            $default_cache_definition = $container->get_definition($config['used_link_cache']);
            if (!$default_cache_definition->has_tag('cache.pool')) {
                $default_cache_definition->add_tag('cache.pool');
            }
        }
        $expired_storage_id = null;
        if (isset($config['used_link_cache'])) {
            $expired_storage_id = 'security.authenticator.expired_login_link_storage.' . $firewall_name;
            $container->set_definition($expired_storage_id, new Child_Definition('security.authenticator.expired_login_link_storage'))->replace_argument(0, new Reference($config['used_link_cache']))->replace_argument(1, $config['lifetime']);
        }
        $signature_hasher_id = 'security.authenticator.login_link_signature_hasher.' . $firewall_name;
        $container->set_definition($signature_hasher_id, new Child_Definition('security.authenticator.abstract_login_link_signature_hasher'))->replace_argument(1, $config['signature_properties'])->replace_argument(2, $config['secret'])->replace_argument(3, $expired_storage_id ? new Reference($expired_storage_id) : null)->replace_argument(4, $config['max_uses'] ?? null);
        $linker_id = 'security.authenticator.login_link_handler.' . $firewall_name;
        $linker_options = ['route_name' => $config['check_route'], 'lifetime' => $config['lifetime']];
        $container->set_definition($linker_id, new Child_Definition('security.authenticator.abstract_login_link_handler'))->replace_argument(1, new Reference($user_provider_id))->replace_argument(2, new Reference($signature_hasher_id))->replace_argument(3, $linker_options)->add_tag('security.authenticator.login_linker', ['firewall' => $firewall_name]);
        $authenticator_id = 'security.authenticator.login_link.' . $firewall_name;
        $container->set_definition($authenticator_id, new Child_Definition('security.authenticator.login_link'))->replace_argument(0, new Reference($linker_id))->replace_argument(2, new Reference($this->create_authentication_success_handler($container, $firewall_name, $config)))->replace_argument(3, new Reference($this->create_authentication_failure_handler($container, $firewall_name, $config)))->replace_argument(4, ['check_route' => $config['check_route'], 'check_post_only' => $config['check_post_only']]);
        return $authenticator_id;
    }
    public function get_priority(): int
    {
        return self::PRIORITY;
    }
}