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

use Symfony\Component\Config\Definition\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Ldap\Security\Check_Ldap_Credentials_Listener;
use Symfony\Component\Ldap\Security\Ldap_Authenticator;
/**
 * A trait decorating the authenticator with LDAP functionality.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
trait Ldap_Factory_Trait
{
    public function get_key(): string
    {
        return parent::get_key() . '-ldap';
    }
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, string $user_provider_id): string
    {
        $key = str_replace('-', '_', $this->get_key());
        $authenticator_id = parent::create_authenticator($container, $firewall_name, $config, $user_provider_id);
        $container->set_definition('security.listener.' . $key . '.' . $firewall_name, new Definition(Check_Ldap_Credentials_Listener::class))->add_tag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.' . $firewall_name])->add_argument(new Reference('security.ldap_locator'));
        $ldap_authenticator_id = 'security.authenticator.' . $key . '.' . $firewall_name;
        $definition = $container->set_definition($ldap_authenticator_id, new Definition(Ldap_Authenticator::class))->set_arguments([new Reference($authenticator_id), $config['service'], $config['dn_string'], $config['search_dn'], $config['search_password']]);
        if (!empty($config['query_string'])) {
            if ('' === $config['search_dn'] || '' === $config['search_password']) {
                throw new Invalid_Configuration_Exception('Using the "query_string" config without using a "search_dn" and a "search_password" is not supported.');
            }
            $definition->add_argument($config['query_string']);
        }
        return $ldap_authenticator_id;
    }
}