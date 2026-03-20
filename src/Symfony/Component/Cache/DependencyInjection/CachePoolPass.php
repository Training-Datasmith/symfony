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

use Symfony\Component\Cache\Adapter\Abstract_Adapter;
use Symfony\Component\Cache\Adapter\Array_Adapter;
use Symfony\Component\Cache\Adapter\Chain_Adapter;
use Symfony\Component\Cache\Adapter\Null_Adapter;
use Symfony\Component\Cache\Adapter\Parameter_Normalizer;
use Symfony\Component\Cache\Adapter\Tag_Aware_Adapter;
use Symfony\Component\Cache\Messenger\Early_Expiration_Dispatcher;
use Symfony\Component\Cache\Pruneable_Interface;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Cache_Pool_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if ($container->has_parameter('cache.prefix.seed')) {
            $seed = $container->get_parameter_bag()->resolve_value($container->get_parameter('cache.prefix.seed'));
        } else {
            $seed = '_' . $container->get_parameter('kernel.project_dir');
            $seed .= '.' . $container->get_parameter('kernel.container_class');
        }
        $needs_message_handler = false;
        $all_pools = [];
        $clearers = [];
        $attributes = ['provider', 'name', 'namespace', 'default_lifetime', 'early_expiration_message_bus', 'reset', 'pruneable'];
        foreach ($container->find_tagged_service_ids('cache.pool') as $id => $tags) {
            $adapter = $pool = $container->get_definition($id);
            if ($pool->is_abstract()) {
                continue;
            }
            $class = $adapter->get_class();
            $providers = $adapter->get_arguments();
            while ($adapter instanceof Child_Definition) {
                $adapter = $container->find_definition($adapter->get_parent());
                $class = $class ?: $adapter->get_class();
                $providers += $adapter->get_arguments();
                if ($t = $adapter->get_tag('cache.pool')) {
                    $tags[0] += $t[0];
                }
            }
            $name = $tags[0]['name'] ?? $id;
            if (!isset($tags[0]['namespace'])) {
                $namespace_seed = $seed;
                if (null !== $class) {
                    $namespace_seed .= '.' . $class;
                }
                $tags[0]['namespace'] = $this->get_namespace($namespace_seed, $name);
            }
            if (isset($tags[0]['clearer'])) {
                $clearer = $tags[0]['clearer'];
                while ($container->has_alias($clearer)) {
                    $clearer = (string) $container->get_alias($clearer);
                }
            } else {
                $clearer = null;
            }
            $marshaller_service_id = $tags[0]['marshaller'] ?? null;
            unset($tags[0]['clearer'], $tags[0]['name'], $tags[0]['marshaller']);
            if (isset($tags[0]['provider'])) {
                $tags[0]['provider'] = new Reference(static::get_service_provider($container, $tags[0]['provider']));
            }
            $pruneable = $tags[0]['pruneable'] ?? $container->get_reflection_class($class, false)?->implements_interface(Pruneable_Interface::class) ?? false;
            if (Chain_Adapter::class === $class) {
                $adapters = [];
                foreach ($providers['index_0'] ?? $providers[0] as $provider => $adapter) {
                    if ($adapter instanceof Child_Definition) {
                        $chained_pool = clone $adapter;
                    } else {
                        $chained_pool = $adapter = new Child_Definition($adapter);
                    }
                    $chained_tags = [\is_int($provider) ? [] : ['provider' => $provider]];
                    $chained_class = '';
                    while ($adapter instanceof Child_Definition) {
                        $adapter = $container->find_definition($adapter->get_parent());
                        $chained_class = $chained_class ?: $adapter->get_class();
                        if ($t = $adapter->get_tag('cache.pool')) {
                            $chained_tags[0] += $t[0];
                        }
                    }
                    if (Chain_Adapter::class === $chained_class) {
                        throw new InvalidArgumentException(\sprintf('Invalid service "%s": chain of adapters cannot reference another chain, found "%s".', $id, $chained_pool->get_parent()));
                    }
                    $i = 0;
                    if (isset($chained_tags[0]['provider'])) {
                        $chained_pool->replace_argument($i++, new Reference(static::get_service_provider($container, $chained_tags[0]['provider'])));
                    }
                    if (isset($tags[0]['namespace']) && !\in_array($adapter->get_class(), [Array_Adapter::class, Null_Adapter::class], true)) {
                        $chained_pool->replace_argument($i++, $tags[0]['namespace']);
                    }
                    if (isset($tags[0]['default_lifetime'])) {
                        $chained_pool->replace_argument($i++, $tags[0]['default_lifetime']);
                    }
                    if (null !== $marshaller_service_id) {
                        if (null !== $marshaller_index = $this->find_default_marshaller_argument_index($adapter)) {
                            $chained_pool->replace_argument($marshaller_index, new Reference($marshaller_service_id));
                        } elseif (!\in_array($chained_class, [Array_Adapter::class, Null_Adapter::class], true)) {
                            throw new InvalidArgumentException(\sprintf('The "marshaller" attribute of the "cache.pool" tag for service "%s" is not supported by chained adapter "%s".', $id, $chained_class));
                        }
                    }
                    $adapters[] = $chained_pool;
                }
                $pool->replace_argument(0, $adapters);
                unset($tags[0]['provider'], $tags[0]['namespace']);
                $i = 1;
            } else {
                $i = 0;
            }
            foreach ($attributes as $attr) {
                if (!isset($tags[0][$attr])) {
                    // no-op
                } elseif ('reset' === $attr) {
                    if ($tags[0][$attr]) {
                        $pool->add_tag('kernel.reset', ['method' => $tags[0][$attr]]);
                    }
                } elseif ('early_expiration_message_bus' === $attr) {
                    $needs_message_handler = true;
                    $pool->add_method_call('setCallbackWrapper', [(new Definition(Early_Expiration_Dispatcher::class))->add_argument(new Reference($tags[0]['early_expiration_message_bus']))->add_argument(new Reference('reverse_container'))->add_argument((new Definition('callable'))->set_factory([new Reference($id), 'setCallbackWrapper'])->add_argument(null))]);
                    $pool->add_tag('container.reversible');
                } elseif ('pruneable' === $attr) {
                    // no-op
                } elseif ('namespace' !== $attr || !\in_array($class, [Array_Adapter::class, Null_Adapter::class, Tag_Aware_Adapter::class], true)) {
                    $argument = $tags[0][$attr];
                    if ('default_lifetime' === $attr && !is_numeric($argument)) {
                        $argument = (new Definition('int', [$argument]))->set_factory(Parameter_Normalizer::normalize_duration(...));
                    }
                    $pool->replace_argument($i++, $argument);
                }
                unset($tags[0][$attr]);
            }
            if (null !== $marshaller_service_id && Chain_Adapter::class !== $class) {
                if (null === $marshaller_index = $this->find_default_marshaller_argument_index($adapter)) {
                    throw new InvalidArgumentException(\sprintf('The "marshaller" attribute of the "cache.pool" tag for service "%s" is not supported by adapter "%s".', $id, $class));
                }
                $pool->replace_argument($marshaller_index, new Reference($marshaller_service_id));
            }
            if (!empty($tags[0])) {
                throw new InvalidArgumentException(\sprintf('Invalid "cache.pool" tag for service "%s": accepted attributes are "clearer", "provider", "name", "namespace", "default_lifetime", "early_expiration_message_bus", "reset", "pruneable" and "marshaller", found "%s".', $id, implode('", "', array_keys($tags[0]))));
            }
            if (null !== $clearer) {
                $clearers[$clearer][$name] = new Reference($id, $container::IGNORE_ON_UNINITIALIZED_REFERENCE);
            }
            $pool_tags = $pool->get_tags();
            $pool_tags['cache.pool'][0]['pruneable'] ??= $pruneable;
            $pool->set_tags($pool_tags);
            $all_pools[$name] = new Reference($id, $container::IGNORE_ON_UNINITIALIZED_REFERENCE);
        }
        if (!$needs_message_handler) {
            $container->remove_definition('cache.early_expiration_handler');
        }
        $not_aliased_cache_clearer_id = 'cache.global_clearer';
        while ($container->has_alias($not_aliased_cache_clearer_id)) {
            $not_aliased_cache_clearer_id = (string) $container->get_alias($not_aliased_cache_clearer_id);
        }
        if ($container->has_definition($not_aliased_cache_clearer_id)) {
            $clearers[$not_aliased_cache_clearer_id] = $all_pools;
        }
        foreach ($clearers as $id => $pools) {
            $clearer = $container->get_definition($id);
            if ($clearer instanceof Child_Definition) {
                $clearer->replace_argument(0, $pools);
            } else {
                $clearer->set_argument(0, $pools);
            }
            $clearer->add_tag('cache.pool.clearer');
        }
        $all_pools_keys = array_keys($all_pools);
        if ($container->has_definition('console.command.cache_pool_list')) {
            $container->get_definition('console.command.cache_pool_list')->replace_argument(0, $all_pools_keys);
        }
        if ($container->has_definition('console.command.cache_pool_clear')) {
            $container->get_definition('console.command.cache_pool_clear')->add_argument($all_pools_keys);
        }
        if ($container->has_definition('console.command.cache_pool_delete')) {
            $container->get_definition('console.command.cache_pool_delete')->add_argument($all_pools_keys);
        }
    }
    private function get_namespace(string $seed, string $id): string
    {
        return substr(str_replace('/', '-', base64_encode(hash('xxh128', $id . $seed, true))), 0, 10);
    }
    /**
     * @internal
     */
    public static function get_service_provider(Container_Builder $container, string $name): string
    {
        $container->resolve_env_placeholders($name, null, $used_envs);
        if ($used_envs || preg_match('#^[a-z]++:#', $name)) {
            $dsn = $name;
            if (!$container->has_definition($name = '.cache_connection.' . Container_Builder::hash($dsn))) {
                $definition = new Definition(Abstract_Adapter::class);
                $definition->set_factory(Abstract_Adapter::create_connection(...));
                $definition->set_arguments([$dsn, ['lazy' => true]]);
                $container->set_definition($name, $definition);
            }
        }
        return $name;
    }
    private function find_default_marshaller_argument_index(Definition $definition): int|string|null
    {
        foreach ($definition->get_arguments() as $index => $argument) {
            if ($argument instanceof Reference && 'cache.default_marshaller' === (string) $argument) {
                return \is_int($index) ? $index : (str_starts_with($index, 'index_') ? (int) substr($index, 6) : $index);
            }
        }
        return null;
    }
}