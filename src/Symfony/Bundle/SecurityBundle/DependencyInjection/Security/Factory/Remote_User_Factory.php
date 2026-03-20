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
/**
 * RemoteUserFactory creates services for REMOTE_USER based authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Maxime Douailin <maxime.douailin@gmail.com>
 *
 * @internal
 */
class Remote_User_Factory implements Authenticator_Factory_Interface
{
    public const PRIORITY = -10;
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, string $user_provider_id): string
    {
        $authenticator_id = 'security.authenticator.remote_user.' . $firewall_name;
        $container->set_definition($authenticator_id, new Child_Definition('security.authenticator.remote_user'))->replace_argument(0, new Reference($user_provider_id))->replace_argument(2, $firewall_name)->replace_argument(3, $config['user']);
        return $authenticator_id;
    }
    public function get_priority(): int
    {
        return self::PRIORITY;
    }
    public function get_key(): string
    {
        return 'remote-user';
    }
    public function add_configuration(Node_Definition $node): void
    {
        $node->children()->scalar_node('provider')->end()->scalar_node('user')->default_value('REMOTE_USER')->end()->end();
    }
}