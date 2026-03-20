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
use Symfony\Component\Dependency_Injection\Parameter;
/**
 * InMemoryFactory creates services for the memory provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class In_Memory_Factory implements User_Provider_Factory_Interface
{
    public function create(Container_Builder $container, string $id, array $config): void
    {
        $definition = $container->set_definition($id, new Child_Definition('security.user.provider.in_memory'));
        $default_password = new Parameter('container.build_id');
        $users = [];
        foreach ($config['users'] as $username => $user) {
            $users[$username] = ['password' => null !== $user['password'] ? (string) $user['password'] : $default_password, 'roles' => $user['roles']];
        }
        $definition->add_argument($users);
    }
    public function get_key(): string
    {
        return 'memory';
    }
    /**
     * @param ArrayNodeDefinition $node
     */
    public function add_configuration(Node_Definition $node): void
    {
        $node->children()->array_node('users', 'user')->use_attribute_as_key('identifier')->normalize_keys(false)->prototype('array')->children()->scalar_node('password')->default_null()->end()->array_node('roles')->before_normalization()->if_string()->then(static fn($v) => preg_split('/\s*,\s*/', (string) $v))->end()->prototype('scalar')->end()->end()->end()->end()->end()->end();
    }
}