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

use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Security\Http\Firewall\Firewall_Listener_Interface;
/**
 * Sorts firewall listeners based on the execution order provided by FirewallListenerInterface::getPriority().
 *
 * @author Christian Scheb <me@christianscheb.de>
 */
class Sort_Firewall_Listeners_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_parameter('security.firewalls')) {
            return;
        }
        foreach ($container->get_parameter('security.firewalls') as $firewall_name) {
            $firewall_context_definition = $container->get_definition('security.firewall.map.context.' . $firewall_name);
            $this->sort_firewall_context_listeners($firewall_context_definition, $container);
        }
    }
    private function sort_firewall_context_listeners(Definition $definition, Container_Builder $container): void
    {
        /** @var IteratorArgument $listenerIteratorArgument */
        $listener_iterator_argument = $definition->get_argument(0);
        $priorities_by_service_id = $this->get_listener_priorities($listener_iterator_argument, $container);
        $listeners = $listener_iterator_argument->get_values();
        usort($listeners, static fn(Reference $a, Reference $b): int => $priorities_by_service_id[(string) $b] <=> $priorities_by_service_id[(string) $a]);
        $listener_iterator_argument->set_values(array_values($listeners));
    }
    private function get_listener_priorities(Iterator_Argument $listeners, Container_Builder $container): array
    {
        $priorities = [];
        foreach ($listeners->get_values() as $reference) {
            $id = (string) $reference;
            $def = $container->get_definition($id);
            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $def->get_class();
            if (!$r = $container->get_reflection_class($class)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            $priority = 0;
            if ($r->is_subclass_of(Firewall_Listener_Interface::class)) {
                $priority = $r->get_method('getPriority')->invoke(null);
            }
            $priorities[$id] = $priority;
        }
        return $priorities;
    }
}