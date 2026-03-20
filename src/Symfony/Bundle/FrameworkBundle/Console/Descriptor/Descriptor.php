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

use Symfony\Component\Config\Resource\Class_Existence_Resource;
use Symfony\Component\Console\Descriptor\Descriptor_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Attribute\As_Tagged_Item;
use Symfony\Component\Dependency_Injection\Compiler\Analyze_Service_References_Pass;
use Symfony\Component\Dependency_Injection\Compiler\Service_Reference_Graph_Edge;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher_Interface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Route_Collection;
/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
abstract class Descriptor implements Descriptor_Interface
{
    protected Output_Interface $output;
    public function describe(Output_Interface $output, mixed $object, array $options = []): void
    {
        $this->output = $output;
        if ($object instanceof Container_Builder) {
            (new Analyze_Service_References_Pass(false, false))->process($object);
        }
        $deprecated_parameters = [];
        if ($object instanceof Container_Builder && isset($options['parameter']) && ($parameter_bag = $object->get_parameter_bag()) instanceof Parameter_Bag) {
            $deprecated_parameters = $parameter_bag->all_deprecated();
        }
        match (true) {
            $object instanceof Route_Collection => $this->describe_route_collection($this->filter_routes_by_http_method($object, $options['method'] ?? ''), $options),
            $object instanceof Route => $this->describe_route($object, $options),
            $object instanceof Parameter_Bag => $this->describe_container_parameters($object, $options),
            $object instanceof Container_Builder && !empty($options['env-vars']) => $this->describe_container_env_vars($this->get_container_env_vars($object), $options),
            $object instanceof Container_Builder && isset($options['group_by']) && 'tags' === $options['group_by'] => $this->describe_container_tags($object, $options),
            $object instanceof Container_Builder && isset($options['id']) => $this->describe_container_service($this->resolve_service_definition($object, $options['id']), $options, $object),
            $object instanceof Container_Builder && isset($options['parameter']) => $this->describe_container_parameter($object->resolve_env_placeholders($object->get_parameter($options['parameter'])), $deprecated_parameters[$options['parameter']] ?? null, $options),
            $object instanceof Container_Builder && isset($options['deprecations']) => $this->describe_container_deprecations($object, $options),
            $object instanceof Container_Builder => $this->describe_container_services($object, $options),
            $object instanceof Definition => $this->describe_container_definition($object, $options),
            $object instanceof Alias => $this->describe_container_alias($object, $options),
            $object instanceof Event_Dispatcher_Interface => $this->describe_event_dispatcher_listeners($object, $options),
            \is_callable($object) => $this->describe_callable($object, $options),
            default => throw new \InvalidArgumentException(\sprintf('Object of type "%s" is not describable.', get_debug_type($object))),
        };
        if ($object instanceof Container_Builder) {
            $object->get_compiler()->get_service_reference_graph()->clear();
        }
    }
    protected function get_output(): Output_Interface
    {
        return $this->output;
    }
    protected function write(string $content, bool $decorated = false): void
    {
        $this->output->write($content, false, $decorated ? Output_Interface::OUTPUT_NORMAL : Output_Interface::OUTPUT_RAW);
    }
    abstract protected function describe_route_collection(Route_Collection $routes, array $options = []): void;
    abstract protected function describe_route(Route $route, array $options = []): void;
    abstract protected function describe_container_parameters(Parameter_Bag $parameters, array $options = []): void;
    abstract protected function describe_container_tags(Container_Builder $container, array $options = []): void;
    /**
     * Describes a container service by its name.
     *
     * Common options are:
     * * name: name of described service
     */
    abstract protected function describe_container_service(object $service, array $options = [], ?Container_Builder $container = null): void;
    /**
     * Describes container services.
     *
     * Common options are:
     * * tag: filters described services by given tag
     */
    abstract protected function describe_container_services(Container_Builder $container, array $options = []): void;
    abstract protected function describe_container_deprecations(Container_Builder $container, array $options = []): void;
    abstract protected function describe_container_definition(Definition $definition, array $options = [], ?Container_Builder $container = null): void;
    abstract protected function describe_container_alias(Alias $alias, array $options = [], ?Container_Builder $container = null): void;
    abstract protected function describe_container_parameter(mixed $parameter, ?array $deprecation, array $options = []): void;
    abstract protected function describe_container_env_vars(array $envs, array $options = []): void;
    /**
     * Describes event dispatcher listeners.
     *
     * Common options are:
     * * name: name of listened event
     */
    abstract protected function describe_event_dispatcher_listeners(Event_Dispatcher_Interface $event_dispatcher, array $options = []): void;
    abstract protected function describe_callable(mixed $callable, array $options = []): void;
    protected function format_value(mixed $value): string
    {
        if ($value instanceof \Unit_Enum) {
            return ltrim(var_export($value, true), '\\');
        }
        if (\is_object($value)) {
            return \sprintf('object(%s)', $value::class);
        }
        if (\is_string($value)) {
            return $value;
        }
        return preg_replace("/\n\\s*/s", '', var_export($value, true));
    }
    protected function format_parameter(mixed $value): string
    {
        if ($value instanceof \Unit_Enum) {
            return ltrim(var_export($value, true), '\\');
        }
        // Recursively search for enum values, so we can replace it
        // before json_encode (which will not display anything for \UnitEnum otherwise)
        if (\is_array($value)) {
            array_walk_recursive($value, static function (&$value): void {
                if ($value instanceof \Unit_Enum) {
                    $value = ltrim(var_export($value, true), '\\');
                }
            });
        }
        if (\is_bool($value) || \is_array($value) || null === $value) {
            $json_string = json_encode($value);
            if (preg_match('/^(.{60})./us', $json_string, $matches)) {
                return $matches[1] . '...';
            }
            return $json_string;
        }
        return (string) $value;
    }
    protected function resolve_service_definition(Container_Builder $container, string $service_id): mixed
    {
        if ($container->has_definition($service_id)) {
            return $container->get_definition($service_id);
        }
        // Some service IDs don't have a Definition, they're aliases
        if ($container->has_alias($service_id)) {
            return $container->get_alias($service_id);
        }
        if ('service_container' === $service_id) {
            return (new Definition(Container_Interface::class))->set_public(true)->set_synthetic(true);
        }
        // the service has been injected in some special way, just return the service
        return $container->get($service_id);
    }
    protected function find_definitions_by_tag(Container_Builder $container, bool $show_hidden): array
    {
        $definitions = [];
        $tags = $container->find_tags();
        asort($tags);
        foreach ($tags as $tag) {
            foreach ($container->find_tagged_service_ids($tag) as $service_id => $attributes) {
                $definition = $this->resolve_service_definition($container, $service_id);
                if ($show_hidden xor '.' === ($service_id[0] ?? null)) {
                    continue;
                }
                if (!isset($definitions[$tag])) {
                    $definitions[$tag] = [];
                }
                $definitions[$tag][$service_id] = $definition;
            }
        }
        return $definitions;
    }
    protected function sort_parameters(Parameter_Bag $parameters): array
    {
        $parameters = $parameters->all();
        ksort($parameters);
        return $parameters;
    }
    protected function sort_service_ids(array $service_ids): array
    {
        asort($service_ids);
        return $service_ids;
    }
    protected function sort_tagged_services_by_priority(array $services): array
    {
        $max_priority = [];
        foreach ($services as $service => $tags) {
            $max_priority[$service] = \PHP_INT_MIN;
            foreach ($tags as $tag) {
                $current_priority = $tag['priority'] ?? 0;
                if ($max_priority[$service] < $current_priority) {
                    $max_priority[$service] = $current_priority;
                }
            }
        }
        uasort($max_priority, static fn($a, $b): int => $b <=> $a);
        return array_keys($max_priority);
    }
    protected function sort_tags_by_priority(array $tags): array
    {
        $sorted_tags = [];
        foreach ($tags as $tag_name => $tag) {
            $sorted_tags[$tag_name] = $this->sort_by_priority($tag);
        }
        return $sorted_tags;
    }
    protected function resolve_priority_service_tags(Container_Builder $container, Definition $definition, ?string $tag_name = null): array
    {
        $tags = null !== $tag_name ? $definition->get_tag($tag_name) : $definition->get_tags();
        $priority = ($container->get_reflection_class($definition->get_class())?->get_attributes(As_Tagged_Item::class)[0] ?? null)?->new_instance()->priority;
        if (!$priority) {
            return $tags;
        }
        if (null !== $tag_name) {
            foreach ($tags as &$tag) {
                $tag['priority'] ??= $priority;
            }
        } else {
            foreach ($tags as &$tag_configs) {
                foreach ($tag_configs as &$tag) {
                    $tag['priority'] ??= $priority;
                }
            }
        }
        return $tags;
    }
    protected function sort_by_priority(array $tag): array
    {
        usort($tag, static fn(array $a, array $b): int => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));
        return $tag;
    }
    /**
     * @return array<string, string[]>
     */
    protected function get_reverse_aliases(Route_Collection $routes): array
    {
        $reverse_aliases = [];
        foreach ($routes->get_aliases() as $name => $alias) {
            $reverse_aliases[$alias->get_id()][] = $name;
        }
        return $reverse_aliases;
    }
    public static function get_class_description(string $class, ?string &$resolved_class = null): string
    {
        $resolved_class = $class;
        try {
            $resource = new Class_Existence_Resource($class, false);
            // isFresh() will explode ONLY if a parent class/trait does not exist
            $resource->is_fresh(0);
            $r = new \ReflectionClass($class);
            $resolved_class = $r->name;
            if ($doc_comment = $r->get_doc_comment()) {
                $doc_comment = preg_split('#\n\s*\*\s*[\n@]#', substr($doc_comment, 3, -2), 2)[0];
                return trim((string) preg_replace('#\s*\n\s*\*\s*#', ' ', $doc_comment));
            }
        } catch (\Reflection_Exception) {
        }
        return '';
    }
    private function get_container_env_vars(Container_Builder $container): array
    {
        if (!$container->has_parameter('debug.container.dump')) {
            return [];
        }
        if (!$container->get_parameter('debug.container.dump') || !is_file($container->get_parameter('debug.container.dump'))) {
            return [];
        }
        $file = file_get_contents($container->get_parameter('debug.container.dump'));
        preg_match_all('{%env\(((?:\w++:)*+\w++)\)%}', $file, $env_vars);
        $env_vars = array_unique($env_vars[1]);
        $bag = $container->get_parameter_bag();
        $get_default_parameter = fn(string $name) => parent::get($name);
        $get_default_parameter = $get_default_parameter->bind_to($bag, $bag::class);
        $get_env_reflection = new \ReflectionMethod($container, 'getEnv');
        $envs = [];
        foreach ($env_vars as $env) {
            $processor = 'string';
            if (false !== $i = strrpos($name = $env, ':')) {
                $name = substr($env, $i + 1);
                $processor = substr($env, 0, $i);
            }
            $default_value = ($has_default = $container->has_parameter("env({$name})")) ? $get_default_parameter("env({$name})") : null;
            if (false === $runtime_value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name)) {
                $runtime_value = null;
            }
            $processed_value = ($has_runtime = null !== $runtime_value) || $has_default ? $get_env_reflection->invoke($container, $env) : null;
            $envs["{$name}{$processor}"] = ['name' => $name, 'processor' => $processor, 'default_available' => $has_default, 'default_value' => $default_value, 'runtime_available' => $has_runtime, 'runtime_value' => $runtime_value, 'processed_value' => $processed_value];
        }
        ksort($envs);
        return array_values($envs);
    }
    protected function get_service_edges(Container_Builder $container, string $service_id): array
    {
        try {
            return array_values(array_unique(array_map(static fn(Service_Reference_Graph_Edge $edge): string => $edge->get_source_node()->get_id(), $container->get_compiler()->get_service_reference_graph()->get_node($service_id)->get_in_edges())));
        } catch (InvalidArgumentException) {
            return [];
        }
    }
    /**
     * @return array<array{id: string, class: ?string, priority: int}>
     */
    protected function get_decoration_stack(Container_Builder $container, string $id): array
    {
        $stack = [];
        while ($container->has_definition($id) || $container->has_alias($id)) {
            // resolve Alias and continue
            if ($container->has_alias($id)) {
                $id = (string) $container->get_alias($id);
                continue;
            }
            $definition = $container->get_definition($id);
            $class = $definition->get_class();
            $priority = $definition->decoration_priority ?? 0;
            $stack[] = ['id' => $id, 'class' => $class, 'priority' => $priority];
            if (!$next_id = $definition->inner_service_id) {
                break;
            }
            $id = $next_id;
        }
        return $stack;
    }
    private function filter_routes_by_http_method(Route_Collection $routes, string $method): Route_Collection
    {
        if (!$method) {
            return $routes;
        }
        $filtered_routes = clone $routes;
        foreach ($filtered_routes as $route_name => $route) {
            if ($route->get_methods() && !\in_array($method, $route->get_methods(), true)) {
                $filtered_routes->remove($route_name);
            }
        }
        return $filtered_routes;
    }
}