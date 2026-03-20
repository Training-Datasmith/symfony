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

use Symfony\Component\Config\Definition\Exception\Invalid_Configuration_Exception;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Security\Http\Entry_Point\Authentication_Entry_Point_Interface;
/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class Register_Entry_Point_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_parameter('security.firewalls')) {
            return;
        }
        $firewalls = $container->get_parameter('security.firewalls');
        foreach ($firewalls as $firewall_name) {
            if (!$container->has_definition('security.authenticator.manager.' . $firewall_name)) {
                continue;
            }
            if (!$container->has_parameter('security.' . $firewall_name . '._indexed_authenticators')) {
                continue;
            }
            $entry_points = [];
            $indexed_authenticators = $container->get_parameter('security.' . $firewall_name . '._indexed_authenticators');
            // this is a compile-only parameter, removing it cleans up space and avoids unintended usage
            $container->get_parameter_bag()->remove('security.' . $firewall_name . '._indexed_authenticators');
            foreach ($indexed_authenticators as $key => $authenticator_id) {
                if (!$container->has($authenticator_id)) {
                    continue;
                }
                // because this pass runs before ResolveChildDefinitionPass, child definitions didn't inherit the parent class yet
                $definition = $container->find_definition($authenticator_id);
                while (!($authenticator_class = $definition->get_class()) && $definition instanceof Child_Definition) {
                    $definition = $container->find_definition($definition->get_parent());
                }
                if (is_a($authenticator_class, Authentication_Entry_Point_Interface::class, true)) {
                    $entry_points[$key] = $authenticator_id;
                }
            }
            if (!$entry_points) {
                continue;
            }
            $config = $container->get_definition('security.firewall.map.config.' . $firewall_name);
            $configured_entry_point = $config->get_argument(7);
            if (null !== $configured_entry_point) {
                // allow entry points to be configured by authenticator key (e.g. "http_basic")
                $entry_point = $entry_points[$configured_entry_point] ?? $configured_entry_point;
            } elseif (1 === \count($entry_points)) {
                $entry_point = array_shift($entry_points);
            } else {
                $entry_point_names = [];
                foreach ($entry_points as $key => $service_id) {
                    $entry_point_names[] = is_numeric($key) ? $service_id : $key;
                }
                throw new Invalid_Configuration_Exception(\sprintf('Because you have multiple authenticators in firewall "%s", you need to set the "entry_point" key to one of your authenticators ("%s") or a service ID implementing "%s". The "entry_point" determines what should happen (e.g. redirect to "/login") when an anonymous user tries to access a protected page.', $firewall_name, implode('", "', $entry_point_names), Authentication_Entry_Point_Interface::class));
            }
            $config->replace_argument(7, $entry_point);
            $container->get_definition('security.exception_listener.' . $firewall_name)->replace_argument(4, new Reference($entry_point));
        }
    }
}