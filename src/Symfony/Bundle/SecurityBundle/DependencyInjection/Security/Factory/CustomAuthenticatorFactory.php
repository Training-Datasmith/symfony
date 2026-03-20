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

use Symfony\Component\Config\Definition\Builder\Array_Node_Definition;
use Symfony\Component\Config\Definition\Builder\Node_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
class Custom_Authenticator_Factory implements Authenticator_Factory_Interface
{
    public function get_priority(): int
    {
        return 0;
    }
    public function get_key(): string
    {
        return 'custom_authenticators';
    }
    /**
     * @param ArrayNodeDefinition $builder
     */
    public function add_configuration(Node_Definition $builder): void
    {
        // get the parent array node builder ("firewalls") from inside the children builder
        $factory_root_node = $builder->end()->end();
        $factory_root_node->fix_xml_config('custom_authenticator')->validate()->if_true(static fn($v): bool => isset($v['custom_authenticators']) && !$v['custom_authenticators'])->then(static function (array $v): array {
            unset($v['custom_authenticators']);
            return $v;
        })->end();
        $builder->info('An array of service ids for all of your "authenticators".')->requires_at_least_one_element()->prototype('scalar')->end();
    }
    public function create_authenticator(Container_Builder $container, string $firewall_name, array $config, string $user_provider_id): array
    {
        return $config;
    }
}