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

use Symfony\Component\Config\Definition\Builder\Node_Definition;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Security\Core\Exception\LogicException;
/**
 * HttpBasicFactory creates services for HTTP basic authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @internal
 */
class Http_Basic_Ldap_Factory extends Http_Basic_Factory
{
    use Ldap_Factory_Trait;
    public function create(Container_Builder $container, string $id, array $config, string $user_provider, ?string $default_entry_point): array
    {
        $provider = 'security.authentication.provider.ldap_bind.' . $id;
        $definition = $container->set_definition($provider, new Child_Definition('security.authentication.provider.ldap_bind'))->replace_argument(0, new Reference($user_provider))->replace_argument(1, new Reference('security.user_checker.' . $id))->replace_argument(2, $id)->replace_argument(3, new Reference($config['service']))->replace_argument(4, $config['dn_string'])->replace_argument(6, $config['search_dn'])->replace_argument(7, $config['search_password']);
        // entry point
        $entry_point_id = $default_entry_point;
        if (null === $entry_point_id) {
            $entry_point_id = 'security.authentication.basic_entry_point.' . $id;
            $container->set_definition($entry_point_id, new Child_Definition('security.authentication.basic_entry_point'))->add_argument($config['realm']);
        }
        if (!empty($config['query_string'])) {
            if ('' === $config['search_dn'] || '' === $config['search_password']) {
                throw new LogicException('Using the "query_string" config without using a "search_dn" and a "search_password" is not supported.');
            }
            $definition->add_method_call('setQueryString', [$config['query_string']]);
        }
        // listener
        $listener_id = 'security.authentication.listener.basic.' . $id;
        $listener = $container->set_definition($listener_id, new Child_Definition('security.authentication.listener.basic'));
        $listener->replace_argument(2, $id);
        $listener->replace_argument(3, new Reference($entry_point_id));
        return [$provider, $listener_id, $entry_point_id];
    }
    public function add_configuration(Node_Definition $node): void
    {
        parent::add_configuration($node);
        $node->children()->scalar_node('service')->default_value('ldap')->end()->scalar_node('dn_string')->default_value('{user_identifier}')->end()->scalar_node('query_string')->end()->scalar_node('search_dn')->default_value('')->end()->scalar_node('search_password')->default_value('')->end()->end();
    }
}