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
namespace Symfony\Bundle\Framework_Bundle\Console\Descriptor;

use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Argument\Abstract_Argument;
use Symfony\Component\Dependency_Injection\Argument\Argument_Interface;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher_Interface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Route_Collection;
/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Json_Descriptor extends Descriptor
{
    protected function describe_route_collection(Route_Collection $routes, array $options = []): void
    {
        $data = [];
        foreach ($routes->all() as $name => $route) {
            $data[$name] = $this->get_route_data($route);
            if (($show_aliases ??= $options['show_aliases'] ?? false) && $aliases = ($reverse_aliases ??= $this->get_reverse_aliases($routes))[$name] ?? []) {
                $data[$name]['aliases'] = $aliases;
            }
        }
        $this->write_data($data, $options);
    }
    protected function describe_route(Route $route, array $options = []): void
    {
        $this->write_data($this->get_route_data($route), $options);
    }
    protected function describe_container_parameters(Parameter_Bag $parameters, array $options = []): void
    {
        $this->write_data($this->sort_parameters($parameters), $options);
    }
    protected function describe_container_tags(Container_Builder $container, array $options = []): void
    {
        $show_hidden = isset($options['show_hidden']) && $options['show_hidden'];
        $data = [];
        foreach ($this->find_definitions_by_tag($container, $show_hidden) as $tag => $definitions) {
            $data[$tag] = [];
            foreach ($definitions as $definition) {
                $data[$tag][] = $this->get_container_definition_data($definition, true, $container, $options['id'] ?? null);
            }
        }
        $this->write_data($data, $options);
    }
    protected function describe_container_service(object $service, array $options = [], ?Container_Builder $container = null): void
    {
        if (!isset($options['id'])) {
            throw new \InvalidArgumentException('An "id" option must be provided.');
        }
        if ($service instanceof Alias) {
            $this->describe_container_alias($service, $options, $container);
        } elseif ($service instanceof Definition) {
            $data = $this->get_container_definition_data($service, isset($options['omit_tags']) && $options['omit_tags'], $container, $options['id']);
            $this->write_data($data, $options);
        } else {
            $this->write_data($service::class, $options);
        }
    }
    protected function describe_container_services(Container_Builder $container, array $options = []): void
    {
        $service_ids = isset($options['tag']) && $options['tag'] ? $this->sort_tagged_services_by_priority($container->find_tagged_service_ids($options['tag'])) : $this->sort_service_ids($container->get_service_ids());
        $show_hidden = isset($options['show_hidden']) && $options['show_hidden'];
        $omit_tags = isset($options['omit_tags']) && $options['omit_tags'];
        $data = ['definitions' => [], 'aliases' => [], 'services' => []];
        if (isset($options['filter'])) {
            $service_ids = array_filter($service_ids, $options['filter']);
        }
        foreach ($service_ids as $service_id) {
            $service = $this->resolve_service_definition($container, $service_id);
            if ($show_hidden xor '.' === ($service_id[0] ?? null)) {
                continue;
            }
            if ($service instanceof Alias) {
                $data['aliases'][$service_id] = $this->get_container_alias_data($service);
            } elseif ($service instanceof Definition) {
                if ($service->has_tag('container.excluded')) {
                    continue;
                }
                $data['definitions'][$service_id] = $this->get_container_definition_data($service, $omit_tags, $container, $service_id);
            } else {
                $data['services'][$service_id] = $service::class;
            }
        }
        $this->write_data($data, $options);
    }
    protected function describe_container_definition(Definition $definition, array $options = [], ?Container_Builder $container = null): void
    {
        $this->write_data($this->get_container_definition_data($definition, isset($options['omit_tags']) && $options['omit_tags'], $container, $options['id'] ?? null), $options);
    }
    protected function describe_container_alias(Alias $alias, array $options = [], ?Container_Builder $container = null): void
    {
        if (!$container) {
            $this->write_data($this->get_container_alias_data($alias), $options);
            return;
        }
        $this->write_data([$this->get_container_alias_data($alias), $this->get_container_definition_data($container->get_definition((string) $alias), isset($options['omit_tags']) && $options['omit_tags'], $container, (string) $alias)], array_merge($options, ['id' => (string) $alias]));
    }
    protected function describe_event_dispatcher_listeners(Event_Dispatcher_Interface $event_dispatcher, array $options = []): void
    {
        $this->write_data($this->get_event_dispatcher_listeners_data($event_dispatcher, $options), $options);
    }
    protected function describe_callable(mixed $callable, array $options = []): void
    {
        $this->write_data($this->get_callable_data($callable), $options);
    }
    protected function describe_container_parameter(mixed $parameter, ?array $deprecation, array $options = []): void
    {
        $key = $options['parameter'] ?? '';
        $data = [$key => $parameter];
        if ($deprecation) {
            $data['_deprecation'] = \sprintf('Since %s %s: %s', $deprecation[0], $deprecation[1], \sprintf(...\array_slice($deprecation, 2)));
        }
        $this->write_data($data, $options);
    }
    protected function describe_container_env_vars(array $envs, array $options = []): void
    {
        throw new LogicException('Using the JSON format to debug environment variables is not supported.');
    }
    protected function describe_container_deprecations(Container_Builder $container, array $options = []): void
    {
        $container_deprecation_file_path = \sprintf('%s/%sDeprecations.log', $container->get_parameter('kernel.build_dir'), $container->get_parameter('kernel.container_class'));
        if (!file_exists($container_deprecation_file_path)) {
            throw new RuntimeException('The deprecation file does not exist, please try warming the cache first.');
        }
        $logs = unserialize(file_get_contents($container_deprecation_file_path));
        $formatted_logs = [];
        $remaining_count = 0;
        foreach ($logs as $log) {
            $formatted_logs[] = ['message' => $log['message'], 'file' => $log['file'], 'line' => $log['line'], 'count' => $log['count']];
            $remaining_count += $log['count'];
        }
        $this->write_data(['remainingCount' => $remaining_count, 'deprecations' => $formatted_logs], $options);
    }
    private function write_data(array $data, array $options): void
    {
        $flags = $options['json_encoding'] ?? 0;
        // Recursively search for enum values, so we can replace it
        // before json_encode (which will not display anything for \UnitEnum otherwise)
        array_walk_recursive($data, static function (&$value): void {
            if ($value instanceof \Unit_Enum) {
                $value = ltrim(var_export($value, true), '\\');
            }
        });
        $this->write(json_encode($data, $flags | \JSON_PRETTY_PRINT) . "\n");
    }
    protected function get_route_data(Route $route): array
    {
        $data = ['path' => $route->get_path(), 'pathRegex' => $route->compile()->get_regex(), 'host' => '' !== $route->get_host() ? $route->get_host() : 'ANY', 'hostRegex' => '' !== $route->get_host() ? $route->compile()->get_host_regex() : '', 'scheme' => $route->get_schemes() ? implode('|', $route->get_schemes()) : 'ANY', 'method' => $route->get_methods() ? implode('|', $route->get_methods()) : 'ANY', 'class' => $route::class, 'defaults' => $route->get_defaults(), 'requirements' => $route->get_requirements() ?: 'NO CUSTOM', 'options' => $route->get_options()];
        if ('' !== $route->get_condition()) {
            $data['condition'] = $route->get_condition();
        }
        return $data;
    }
    protected function sort_parameters(Parameter_Bag $parameters): array
    {
        $sorted_parameters = parent::sort_parameters($parameters);
        if ($deprecated = $parameters->all_deprecated()) {
            $deprecations = [];
            foreach ($deprecated as $parameter => $deprecation) {
                $deprecations[$parameter] = \sprintf('Since %s %s: %s', $deprecation[0], $deprecation[1], \sprintf(...\array_slice($deprecation, 2)));
            }
            $sorted_parameters['_deprecations'] = $deprecations;
        }
        return $sorted_parameters;
    }
    private function get_container_definition_data(Definition $definition, bool $omit_tags = false, ?Container_Builder $container = null, ?string $id = null): array
    {
        $data = ['class' => (string) $definition->get_class(), 'public' => $definition->is_public(), 'synthetic' => $definition->is_synthetic(), 'lazy' => $definition->is_lazy(), 'shared' => $definition->is_shared(), 'abstract' => $definition->is_abstract(), 'autowire' => $definition->is_autowired(), 'autoconfigure' => $definition->is_autoconfigured()];
        if ($definition->is_deprecated()) {
            $data['deprecated'] = true;
            $data['deprecation_message'] = $definition->get_deprecation($id)['message'];
        } else {
            $data['deprecated'] = false;
        }
        if ('' !== $class_description = $this->get_class_description((string) $definition->get_class())) {
            $data['description'] = $class_description;
        }
        $data['arguments'] = $this->describe_value($definition->get_arguments(), $omit_tags, $container, $id);
        $data['file'] = $definition->get_file();
        if ($factory = $definition->get_factory()) {
            if (\is_array($factory)) {
                if ($factory[0] instanceof Reference) {
                    $data['factory_service'] = (string) $factory[0];
                } elseif ($factory[0] instanceof Definition) {
                    $data['factory_service'] = \sprintf('inline factory service (%s)', $factory[0]->get_class() ?? 'class not configured');
                } else {
                    $data['factory_class'] = $factory[0];
                }
                $data['factory_method'] = $factory[1];
            } else {
                $data['factory_function'] = $factory;
            }
        }
        $calls = $definition->get_method_calls();
        if (\count($calls) > 0) {
            $data['calls'] = [];
            foreach ($calls as $call_data) {
                $data['calls'][] = $call_data[0];
            }
        }
        if (!$omit_tags) {
            $data['tags'] = [];
            foreach ($this->sort_tags_by_priority($container ? $this->resolve_priority_service_tags($container, $definition) : $definition->get_tags()) as $tag_name => $tag_data) {
                foreach ($tag_data as $parameters) {
                    $data['tags'][] = ['name' => $tag_name, 'parameters' => $parameters];
                }
            }
        }
        $data['usages'] = null !== $container && null !== $id ? $this->get_service_edges($container, $id) : [];
        if ($container && $id) {
            $decoration_stack = $this->get_decoration_stack($container, $id);
            if (\count($decoration_stack) > 1) {
                $data['decoration_stack'] = $decoration_stack;
            }
        }
        return $data;
    }
    private function get_container_alias_data(Alias $alias): array
    {
        return ['service' => (string) $alias, 'public' => $alias->is_public()];
    }
    private function get_event_dispatcher_listeners_data(Event_Dispatcher_Interface $event_dispatcher, array $options): array
    {
        $data = [];
        $event = $options['event'] ?? null;
        if (null !== $event) {
            foreach ($event_dispatcher->get_listeners($event) as $listener) {
                $l = $this->get_callable_data($listener);
                $l['priority'] = $event_dispatcher->get_listener_priority($event, $listener);
                $data[] = $l;
            }
        } else {
            $registered_listeners = \array_key_exists('events', $options) ? array_combine($options['events'], array_map($event_dispatcher->get_listeners(...), $options['events'])) : $event_dispatcher->get_listeners();
            ksort($registered_listeners);
            foreach ($registered_listeners as $event_listened => $event_listeners) {
                foreach ($event_listeners as $event_listener) {
                    $l = $this->get_callable_data($event_listener);
                    $l['priority'] = $event_dispatcher->get_listener_priority($event_listened, $event_listener);
                    $data[$event_listened][] = $l;
                }
            }
        }
        return $data;
    }
    private function get_callable_data(mixed $callable): array
    {
        $data = [];
        if (\is_array($callable)) {
            $data['type'] = 'function';
            if (\is_object($callable[0])) {
                $data['name'] = $callable[1];
                $data['class'] = $callable[0]::class;
            } else if (!str_starts_with((string) $callable[1], 'parent::')) {
                $data['name'] = $callable[1];
                $data['class'] = $callable[0];
                $data['static'] = true;
            } else {
                $data['name'] = substr((string) $callable[1], 8);
                $data['class'] = $callable[0];
                $data['static'] = true;
                $data['parent'] = true;
            }
            return $data;
        }
        if (\is_string($callable)) {
            $data['type'] = 'function';
            if (!str_contains($callable, '::')) {
                $data['name'] = $callable;
            } else {
                $callable_parts = explode('::', $callable);
                $data['name'] = $callable_parts[1];
                $data['class'] = $callable_parts[0];
                $data['static'] = true;
            }
            return $data;
        }
        if ($callable instanceof \Closure) {
            $data['type'] = 'closure';
            $r = new \ReflectionFunction($callable);
            if ($r->is_anonymous()) {
                return $data;
            }
            $data['name'] = $r->name;
            if ($class = $r->get_closure_called_class()) {
                $data['class'] = $class->name;
                if (!$r->get_closure_this()) {
                    $data['static'] = true;
                }
            }
            return $data;
        }
        if (method_exists($callable, '__invoke')) {
            $data['type'] = 'object';
            $data['name'] = $callable::class;
            return $data;
        }
        throw new \InvalidArgumentException('Callable is not describable.');
    }
    private function describe_value($value, bool $omit_tags, ?Container_Builder $container = null, ?string $id = null): mixed
    {
        if (\is_array($value)) {
            $data = [];
            foreach ($value as $k => $v) {
                $data[$k] = $this->describe_value($v, $omit_tags, $container, $id);
            }
            return $data;
        }
        if ($value instanceof Service_Closure_Argument) {
            $value = $value->get_values()[0];
        }
        if ($value instanceof Reference) {
            return ['type' => 'service', 'id' => (string) $value];
        }
        if ($value instanceof Abstract_Argument) {
            return ['type' => 'abstract', 'text' => $value->get_text()];
        }
        if ($value instanceof Argument_Interface) {
            return $this->describe_value($value->get_values(), $omit_tags, $container, $id);
        }
        if ($value instanceof Definition) {
            return $this->get_container_definition_data($value, $omit_tags, $container, $id);
        }
        return $value;
    }
}