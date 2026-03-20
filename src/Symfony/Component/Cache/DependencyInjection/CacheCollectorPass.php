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
namespace Symfony\Component\Cache\Dependency_Injection;

use Symfony\Component\Cache\Adapter\Tag_Aware_Adapter_Interface;
use Symfony\Component\Cache\Adapter\Traceable_Adapter;
use Symfony\Component\Cache\Adapter\Traceable_Tag_Aware_Adapter;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Inject a data collector to all the cache services to be able to get detailed statistics.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Cache_Collector_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('data_collector.cache')) {
            return;
        }
        foreach ($container->find_tagged_service_ids('cache.pool') as $id => $attributes) {
            $pool_name = $attributes[0]['name'] ?? $id;
            $this->add_to_collector($id, $pool_name, $container);
        }
    }
    private function add_to_collector(string $id, string $name, Container_Builder $container): void
    {
        $definition = $container->get_definition($id);
        if ($definition->is_abstract()) {
            return;
        }
        $collector_definition = $container->get_definition('data_collector.cache');
        $recorder = new Definition(is_subclass_of($definition->get_class(), Tag_Aware_Adapter_Interface::class) ? Traceable_Tag_Aware_Adapter::class : Traceable_Adapter::class);
        $recorder->set_tags($definition->get_tags());
        $recorder->set_public($definition->is_public());
        $recorder->set_arguments([new Reference($inner_id = $id . '.recorder_inner'), new Reference('profiler.is_disabled_state_checker', Container_Builder::IGNORE_ON_INVALID_REFERENCE)]);
        foreach ($definition->get_method_calls() as [$method, $args]) {
            if ('setCallbackWrapper' !== $method) {
                continue;
            }
            if (!$args[0] instanceof Definition) {
                continue;
            }
            if (!($args[0]->get_arguments()[2] ?? null) instanceof Definition) {
                continue;
            }
            if ([new Reference($id), 'setCallbackWrapper'] == $args[0]->get_arguments()[2]->get_factory()) {
                $args[0]->get_arguments()[2]->set_factory([new Reference($inner_id), 'setCallbackWrapper']);
            }
        }
        $definition->set_tags([]);
        $definition->set_public(false);
        $container->set_definition($inner_id, $definition);
        $container->set_definition($id, $recorder);
        // Tell the collector to add the new instance
        $collector_definition->add_method_call('addInstance', [$name, new Reference($id)]);
    }
}