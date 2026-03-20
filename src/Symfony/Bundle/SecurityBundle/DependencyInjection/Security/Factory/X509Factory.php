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
 * X509Factory creates services for X509 certificate authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class X509Factory implements Authenticator_Factory_Interface
{
    public const PRIORITY = -10;
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, string $user_provider_id): string
    {
        $authenticator_id = 'security.authenticator.x509.' . $firewall_name;
        $container->set_definition($authenticator_id, new Child_Definition('security.authenticator.x509'))->replace_argument(0, new Reference($user_provider_id))->replace_argument(2, $firewall_name)->replace_argument(3, $config['user'])->replace_argument(4, $config['credentials'])->replace_argument(6, $config['user_identifier']);
        return $authenticator_id;
    }
    public function get_priority(): int
    {
        return self::PRIORITY;
    }
    public function get_key(): string
    {
        return 'x509';
    }
    public function add_configuration(Node_Definition $node): void
    {
        $node->children()->scalar_node('provider')->end()->scalar_node('user')->default_value('SSL_CLIENT_S_DN_Email')->end()->scalar_node('credentials')->default_value('SSL_CLIENT_S_DN')->end()->scalar_node('user_identifier')->default_value('emailAddress')->end()->end();
    }
}