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
namespace Symfony\Bridge\Doctrine\Dependency_Injection\Compiler_Pass;

use Symfony\Bridge\Doctrine\Container_Aware_Event_Manager;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Service_Locator_Tag_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Registers event listeners to the available doctrine connections.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 * @author Alexander <iam.asm89@gmail.com>
 * @author David Maicher <mail@dmaicher.de>
 */
class Register_Event_Listeners_And_Subscribers_Pass implements Compiler_Pass_Interface
{
    private array $connections;
    /**
     * @var array<string, Definition>
     */
    private array $event_managers = [];
    /**
     * @param string $managerTemplate sprintf() template for generating the event
     *                                manager's service ID for a connection name
     * @param string $tagPrefix       Tag prefix for listeners
     */
    public function __construct(private readonly string $connections_parameter, private readonly string $manager_template, private readonly string $tag_prefix)
    {
    }
    public function process(Container_Builder $container): void
    {
        if (!$container->has_parameter($this->connections_parameter)) {
            return;
        }
        $this->connections = $container->get_parameter($this->connections_parameter);
        $listener_refs = $this->add_tagged_services($container);
        // replace service container argument of event managers with smaller service locator
        // so services can even remain private
        foreach ($listener_refs as $connection => $refs) {
            $this->get_event_manager_def($container, $connection)->replace_argument(0, Service_Locator_Tag_Pass::register($container, $refs));
        }
    }
    private function add_tagged_services(Container_Builder $container): array
    {
        $listener_refs = [];
        $manager_defs = [];
        foreach ($this->find_and_sort_tags($container) as [$id, $tag]) {
            $connections = isset($tag['connection']) ? [$container->get_parameter_bag()->resolve_value($tag['connection'])] : array_keys($this->connections);
            if (!isset($tag['event'])) {
                throw new InvalidArgumentException(\sprintf('Doctrine event listener "%s" must specify the "event" attribute.', $id));
            }
            foreach ($connections as $con) {
                if (!isset($this->connections[$con])) {
                    throw new RuntimeException(\sprintf('The Doctrine connection "%s" referenced in service "%s" does not exist. Available connections names: "%s".', $con, $id, implode('", "', array_keys($this->connections))));
                }
                if (!isset($manager_defs[$con])) {
                    $manager_def = $parent_def = $this->get_event_manager_def($container, $con);
                    while (!$parent_def->get_class() && $parent_def instanceof Child_Definition) {
                        $parent_def = $container->find_definition($parent_def->get_parent());
                    }
                    $manager_class = $container->get_parameter_bag()->resolve_value($parent_def->get_class());
                    $manager_defs[$con] = [$manager_def, $manager_class];
                } else {
                    [$manager_def, $manager_class] = $manager_defs[$con];
                }
                if (Container_Aware_Event_Manager::class === $manager_class) {
                    $refs = $manager_def->get_arguments()[1] ?? [];
                    $listener_refs[$con][$id] = new Reference($id);
                    $refs[] = [[$tag['event']], $id];
                    $manager_def->set_argument(1, $refs);
                } else {
                    $manager_def->add_method_call('addEventListener', [[$tag['event']], new Reference($id)]);
                }
            }
        }
        return $listener_refs;
    }
    private function get_event_manager_def(Container_Builder $container, string $name): Definition
    {
        if (!isset($this->event_managers[$name])) {
            $this->event_managers[$name] = $container->get_definition(\sprintf($this->manager_template, $name));
        }
        return $this->event_managers[$name];
    }
    /**
     * Finds and orders all service tags with the given name by their priority.
     *
     * The order of additions must be respected for services having the same priority,
     * and knowing that the \SplPriorityQueue class does not respect the FIFO method,
     * we should not use this class.
     *
     * @see https://bugs.php.net/53710
     * @see https://bugs.php.net/60926
     */
    private function find_and_sort_tags(Container_Builder $container): array
    {
        $sorted_tags = [];
        foreach ($container->find_tagged_service_ids($this->tag_prefix . '.event_listener', true) as $service_id => $tags) {
            foreach ($tags as $attributes) {
                $priority = $attributes['priority'] ?? 0;
                $sorted_tags[$priority][] = [$service_id, $attributes];
            }
        }
        krsort($sorted_tags);
        return array_merge(...$sorted_tags);
    }
}