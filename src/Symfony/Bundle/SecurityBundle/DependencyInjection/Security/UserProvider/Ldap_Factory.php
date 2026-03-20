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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection\Security\User_Provider;

use Symfony\Component\Config\Definition\Builder\Array_Node_Definition;
use Symfony\Component\Config\Definition\Builder\Node_Definition;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * LdapFactory creates services for Ldap user provider.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Ldap_Factory implements User_Provider_Factory_Interface
{
    public function create(Container_Builder $container, string $id, array $config): void
    {
        $container->set_definition($id, new Child_Definition('security.user.provider.ldap'))->replace_argument(0, new Reference($config['service']))->replace_argument(1, $config['base_dn'])->replace_argument(2, $config['search_dn'])->replace_argument(3, $config['search_password'])->replace_argument(4, $config['role_fetcher'] ? new Reference($config['role_fetcher']) : $config['default_roles'])->replace_argument(5, $config['uid_key'])->replace_argument(6, $config['filter'])->replace_argument(7, $config['password_attribute'])->replace_argument(8, $config['extra_fields']);
    }
    public function get_key(): string
    {
        return 'ldap';
    }
    /**
     * @param ArrayNodeDefinition $node
     */
    public function add_configuration(Node_Definition $node): void
    {
        $node->children()->scalar_node('service')->is_required()->cannot_be_empty()->example('ldap')->end()->scalar_node('base_dn')->is_required()->cannot_be_empty()->end()->scalar_node('search_dn')->default_null()->end()->scalar_node('search_password')->default_null()->end()->array_node('extra_fields', 'extra_field')->prototype('scalar')->end()->end()->array_node('default_roles', 'default_role')->before_normalization()->if_string()->then(static fn($v) => preg_split('/\s*,\s*/', (string) $v))->end()->requires_at_least_one_element()->prototype('scalar')->end()->end()->scalar_node('role_fetcher')->default_null()->end()->scalar_node('uid_key')->default_value('sAMAccountName')->end()->scalar_node('filter')->default_value('({uid_key}={user_identifier})')->end()->scalar_node('password_attribute')->default_null()->end()->end();
    }
}