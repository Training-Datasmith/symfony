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
 * HttpBasicFactory creates services for HTTP basic authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class Http_Basic_Factory implements Authenticator_Factory_Interface
{
    public const PRIORITY = -50;
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, string $user_provider_id): string
    {
        $authenticator_id = 'security.authenticator.http_basic.' . $firewall_name;
        $container->set_definition($authenticator_id, new Child_Definition('security.authenticator.http_basic'))->replace_argument(0, $config['realm'])->replace_argument(1, new Reference($user_provider_id));
        return $authenticator_id;
    }
    public function get_priority(): int
    {
        return self::PRIORITY;
    }
    public function get_key(): string
    {
        return 'http-basic';
    }
    public function add_configuration(Node_Definition $node): void
    {
        $node->children()->scalar_node('provider')->end()->scalar_node('realm')->default_value('Secured Area')->end()->end();
    }
}