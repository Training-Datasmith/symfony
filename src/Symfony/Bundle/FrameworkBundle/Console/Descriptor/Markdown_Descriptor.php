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
class Markdown_Descriptor extends Descriptor
{
    protected function describe_route_collection(Route_Collection $routes, array $options = []): void
    {
        $first = true;
        foreach ($routes->all() as $name => $route) {
            if ($first) {
                $first = false;
            } else {
                $this->write("\n\n");
            }
            $this->describe_route($route, ['name' => $name]);
            if (($show_aliases ??= $options['show_aliases'] ?? false) && $aliases = ($reverse_aliases ??= $this->get_reverse_aliases($routes))[$name] ?? []) {
                $this->write(\sprintf("- Aliases: \n%s", implode("\n", array_map(static fn(string $alias): string => \sprintf('    - %s', $alias), $aliases))));
            }
        }
        $this->write("\n");
    }
    protected function describe_route(Route $route, array $options = []): void
    {
        $output = '- Path: ' . $route->get_path() . "\n" . '- Path Regex: ' . $route->compile()->get_regex() . "\n" . '- Host: ' . ('' !== $route->get_host() ? $route->get_host() : 'ANY') . "\n" . '- Host Regex: ' . ('' !== $route->get_host() ? $route->compile()->get_host_regex() : '') . "\n" . '- Scheme: ' . ($route->get_schemes() ? implode('|', $route->get_schemes()) : 'ANY') . "\n" . '- Method: ' . ($route->get_methods() ? implode('|', $route->get_methods()) : 'ANY') . "\n" . '- Class: ' . $route::class . "\n" . '- Defaults: ' . $this->format_router_config($route->get_defaults()) . "\n" . '- Requirements: ' . ($route->get_requirements() ? $this->format_router_config($route->get_requirements()) : 'NO CUSTOM') . "\n" . '- Options: ' . $this->format_router_config($route->get_options());
        if ('' !== $route->get_condition()) {
            $output .= "\n" . '- Condition: ' . $route->get_condition();
        }
        $this->write(isset($options['name']) ? $options['name'] . "\n" . str_repeat('-', \strlen($options['name'])) . "\n\n" . $output : $output);
        $this->write("\n");
    }
    protected function describe_container_parameters(Parameter_Bag $parameters, array $options = []): void
    {
        $deprecated_parameters = $parameters->all_deprecated();
        $this->write("Container parameters\n====================\n");
        foreach ($this->sort_parameters($parameters) as $key => $value) {
            $this->write(\sprintf("\n- `%s`: `%s`%s", $key, $this->format_parameter($value), isset($deprecated_parameters[$key]) ? \sprintf(' *Since %s %s: %s*', $deprecated_parameters[$key][0], $deprecated_parameters[$key][1], \sprintf(...\array_slice($deprecated_parameters[$key], 2))) : ''));
        }
    }
    protected function describe_container_tags(Container_Builder $container, array $options = []): void
    {
        $show_hidden = isset($options['show_hidden']) && $options['show_hidden'];
        $this->write("Container tags\n==============");
        foreach ($this->find_definitions_by_tag($container, $show_hidden) as $tag => $definitions) {
            $this->write("\n\n" . $tag . "\n" . str_repeat('-', \strlen((string) $tag)));
            foreach ($definitions as $service_id => $definition) {
                $this->write("\n\n");
                $this->describe_container_definition($definition, ['omit_tags' => true, 'id' => $service_id], $container);
            }
        }
    }
    protected function describe_container_service(object $service, array $options = [], ?Container_Builder $container = null): void
    {
        if (!isset($options['id'])) {
            throw new \InvalidArgumentException('An "id" option must be provided.');
        }
        $child_options = array_merge($options, ['id' => $options['id'], 'as_array' => true]);
        if ($service instanceof Alias) {
            $this->describe_container_alias($service, $child_options, $container);
        } elseif ($service instanceof Definition) {
            $this->describe_container_definition($service, $child_options, $container);
        } else {
            $this->write(\sprintf('**`%s`:** `%s`', $options['id'], $service::class));
        }
    }
    protected function describe_container_deprecations(Container_Builder $container, array $options = []): void
    {
        $container_deprecation_file_path = \sprintf('%s/%sDeprecations.log', $container->get_parameter('kernel.build_dir'), $container->get_parameter('kernel.container_class'));
        if (!file_exists($container_deprecation_file_path)) {
            throw new RuntimeException('The deprecation file does not exist, please try warming the cache first.');
        }
        $logs = unserialize(file_get_contents($container_deprecation_file_path));
        if (0 === \count($logs)) {
            $this->write("## There are no deprecations in the logs!\n");
            return;
        }
        $formatted_logs = [];
        $remaining_count = 0;
        foreach ($logs as $log) {
            $formatted_logs[] = \sprintf("- %sx: \"%s\" in %s:%s\n", $log['count'], $log['message'], $log['file'], $log['line']);
            $remaining_count += $log['count'];
        }
        $this->write(\sprintf("## Remaining deprecations (%s)\n\n", $remaining_count));
        foreach ($formatted_logs as $formatted_log) {
            $this->write($formatted_log);
        }
    }
    protected function describe_container_services(Container_Builder $container, array $options = []): void
    {
        $show_hidden = isset($options['show_hidden']) && $options['show_hidden'];
        $title = $show_hidden ? 'Hidden services' : 'Services';
        if (isset($options['tag'])) {
            $title .= ' with tag `' . $options['tag'] . '`';
        }
        $this->write($title . "\n" . str_repeat('=', \strlen($title)));
        $service_ids = isset($options['tag']) && $options['tag'] ? $this->sort_tagged_services_by_priority($container->find_tagged_service_ids($options['tag'])) : $this->sort_service_ids($container->get_service_ids());
        $services = ['definitions' => [], 'aliases' => [], 'services' => []];
        if (isset($options['filter'])) {
            $service_ids = array_filter($service_ids, $options['filter']);
        }
        foreach ($service_ids as $service_id) {
            $service = $this->resolve_service_definition($container, $service_id);
            if ($show_hidden xor '.' === ($service_id[0] ?? null)) {
                continue;
            }
            if ($service instanceof Alias) {
                $services['aliases'][$service_id] = $service;
            } elseif ($service instanceof Definition) {
                if ($service->has_tag('container.excluded')) {
                    continue;
                }
                $services['definitions'][$service_id] = $service;
            } else {
                $services['services'][$service_id] = $service;
            }
        }
        if (!empty($services['definitions'])) {
            $this->write("\n\nDefinitions\n-----------\n");
            foreach ($services['definitions'] as $id => $service) {
                $this->write("\n");
                $this->describe_container_definition($service, ['id' => $id], $container);
            }
        }
        if (!empty($services['aliases'])) {
            $this->write("\n\nAliases\n-------\n");
            foreach ($services['aliases'] as $id => $service) {
                $this->write("\n");
                $this->describe_container_alias($service, ['id' => $id]);
            }
        }
        if (!empty($services['services'])) {
            $this->write("\n\nServices\n--------\n");
            foreach ($services['services'] as $id => $service) {
                $this->write("\n");
                $this->write(\sprintf('- `%s`: `%s`', $id, $service::class));
            }
        }
    }
    protected function describe_container_definition(Definition $definition, array $options = [], ?Container_Builder $container = null): void
    {
        $output = '';
        if ('' !== $class_description = $this->get_class_description((string) $definition->get_class())) {
            $output .= '- Description: `' . $class_description . '`' . "\n";
        }
        $output .= '- Class: `' . $definition->get_class() . '`' . "\n" . '- Public: ' . ($definition->is_public() ? 'yes' : 'no') . "\n" . '- Synthetic: ' . ($definition->is_synthetic() ? 'yes' : 'no') . "\n" . '- Lazy: ' . ($definition->is_lazy() ? 'yes' : 'no') . "\n" . '- Shared: ' . ($definition->is_shared() ? 'yes' : 'no') . "\n" . '- Abstract: ' . ($definition->is_abstract() ? 'yes' : 'no') . "\n" . '- Autowired: ' . ($definition->is_autowired() ? 'yes' : 'no') . "\n" . '- Autoconfigured: ' . ($definition->is_autoconfigured() ? 'yes' : 'no');
        if ($definition->is_deprecated()) {
            $output .= "\n" . '- Deprecated: yes';
            $output .= "\n" . '- Deprecation message: ' . $definition->get_deprecation($options['id'])['message'];
        } else {
            $output .= "\n" . '- Deprecated: no';
        }
        $output .= "\n" . '- Arguments: ' . ($definition->get_arguments() ? 'yes' : 'no');
        if ($definition->get_file()) {
            $output .= "\n" . '- File: `' . $definition->get_file() . '`';
        }
        if ($factory = $definition->get_factory()) {
            if (\is_array($factory)) {
                if ($factory[0] instanceof Reference) {
                    $output .= "\n" . '- Factory Service: `' . $factory[0] . '`';
                } elseif ($factory[0] instanceof Definition) {
                    $output .= "\n" . \sprintf('- Factory Service: inline factory service (%s)', $factory[0]->get_class() ? \sprintf('`%s`', $factory[0]->get_class()) : 'not configured');
                } else {
                    $output .= "\n" . '- Factory Class: `' . $factory[0] . '`';
                }
                $output .= "\n" . '- Factory Method: `' . $factory[1] . '`';
            } else {
                $output .= "\n" . '- Factory Function: `' . $factory . '`';
            }
        }
        $calls = $definition->get_method_calls();
        foreach ($calls as $call_data) {
            $output .= "\n" . '- Call: `' . $call_data[0] . '`';
        }
        if (!(isset($options['omit_tags']) && $options['omit_tags'])) {
            foreach ($this->sort_tags_by_priority($container ? $this->resolve_priority_service_tags($container, $definition) : $definition->get_tags()) as $tag_name => $tag_data) {
                foreach ($tag_data as $parameters) {
                    $output .= "\n" . '- Tag: `' . $tag_name . '`';
                    foreach ($parameters as $name => $value) {
                        $output .= "\n" . '    - ' . ucfirst((string) $name) . ': ' . (\is_array($value) ? $this->format_parameter($value) : $value);
                    }
                }
            }
        }
        $in_edges = null !== $container && isset($options['id']) ? $this->get_service_edges($container, $options['id']) : [];
        $output .= "\n" . '- Usages: ' . ($in_edges ? implode(', ', $in_edges) : 'none');
        if (isset($options['id']) && $container) {
            $stack = $this->get_decoration_stack($container, $options['id']);
            if (\count($stack) > 1) {
                $output .= "\n- Decoration Stack:\n";
                foreach ($stack as $item) {
                    $output .= \sprintf("  - Id: `%s`\n    Class: `%s`\n    Priority: %d\n", $item['id'], $item['class'], $item['priority']);
                }
            }
        }
        $this->write(isset($options['id']) ? \sprintf("### %s\n\n%s\n", $options['id'], $output) : $output);
    }
    protected function describe_container_alias(Alias $alias, array $options = [], ?Container_Builder $container = null): void
    {
        $output = '- Service: `' . $alias . '`' . "\n" . '- Public: ' . ($alias->is_public() ? 'yes' : 'no');
        if (!isset($options['id'])) {
            $this->write($output);
            return;
        }
        $this->write(\sprintf("### %s\n\n%s\n", $options['id'], $output));
        if (!$container) {
            return;
        }
        $this->write("\n");
        $this->describe_container_definition($container->get_definition((string) $alias), array_merge($options, ['id' => (string) $alias]), $container);
    }
    protected function describe_container_parameter(mixed $parameter, ?array $deprecation, array $options = []): void
    {
        if (isset($options['parameter'])) {
            $this->write(\sprintf("%s\n%s\n\n%s%s", $options['parameter'], str_repeat('=', \strlen($options['parameter'])), $this->format_parameter($parameter), $deprecation ? \sprintf("\n\n*Since %s %s: %s*", $deprecation[0], $deprecation[1], \sprintf(...\array_slice($deprecation, 2))) : ''));
        } else {
            $this->write($parameter);
        }
    }
    protected function describe_container_env_vars(array $envs, array $options = []): void
    {
        throw new LogicException('Using the markdown format to debug environment variables is not supported.');
    }
    protected function describe_event_dispatcher_listeners(Event_Dispatcher_Interface $event_dispatcher, array $options = []): void
    {
        $event = $options['event'] ?? null;
        $dispatcher_service_name = $options['dispatcher_service_name'] ?? null;
        $title = 'Registered listeners';
        if (null !== $dispatcher_service_name) {
            $title .= \sprintf(' of event dispatcher "%s"', $dispatcher_service_name);
        }
        if (null !== $event) {
            $title .= \sprintf(' for event `%s` ordered by descending priority', $event);
            $registered_listeners = $event_dispatcher->get_listeners($event);
        } else {
            // Try to see if "events" exists
            $registered_listeners = \array_key_exists('events', $options) ? array_combine($options['events'], array_map($event_dispatcher->get_listeners(...), $options['events'])) : $event_dispatcher->get_listeners();
        }
        $this->write(\sprintf('# %s', $title) . "\n");
        if (null !== $event) {
            foreach ($registered_listeners as $order => $listener) {
                $this->write("\n" . \sprintf('## Listener %d', $order + 1) . "\n");
                $this->describe_callable($listener);
                $this->write(\sprintf('- Priority: `%d`', $event_dispatcher->get_listener_priority($event, $listener)) . "\n");
            }
        } else {
            ksort($registered_listeners);
            foreach ($registered_listeners as $event_listened => $event_listeners) {
                $this->write("\n" . \sprintf('## %s', $event_listened) . "\n");
                foreach ($event_listeners as $order => $event_listener) {
                    $this->write("\n" . \sprintf('### Listener %d', $order + 1) . "\n");
                    $this->describe_callable($event_listener);
                    $this->write(\sprintf('- Priority: `%d`', $event_dispatcher->get_listener_priority($event_listened, $event_listener)) . "\n");
                }
            }
        }
    }
    protected function describe_callable(mixed $callable, array $options = []): void
    {
        $string = '';
        if (\is_array($callable)) {
            $string .= "\n- Type: `function`";
            if (\is_object($callable[0])) {
                $string .= "\n" . \sprintf('- Name: `%s`', $callable[1]);
                $string .= "\n" . \sprintf('- Class: `%s`', $callable[0]::class);
            } else if (!str_starts_with((string) $callable[1], 'parent::')) {
                $string .= "\n" . \sprintf('- Name: `%s`', $callable[1]);
                $string .= "\n" . \sprintf('- Class: `%s`', $callable[0]);
                $string .= "\n- Static: yes";
            } else {
                $string .= "\n" . \sprintf('- Name: `%s`', substr((string) $callable[1], 8));
                $string .= "\n" . \sprintf('- Class: `%s`', $callable[0]);
                $string .= "\n- Static: yes";
                $string .= "\n- Parent: yes";
            }
            $this->write($string . "\n");
            return;
        }
        if (\is_string($callable)) {
            $string .= "\n- Type: `function`";
            if (!str_contains($callable, '::')) {
                $string .= "\n" . \sprintf('- Name: `%s`', $callable);
            } else {
                $callable_parts = explode('::', $callable);
                $string .= "\n" . \sprintf('- Name: `%s`', $callable_parts[1]);
                $string .= "\n" . \sprintf('- Class: `%s`', $callable_parts[0]);
                $string .= "\n- Static: yes";
            }
            $this->write($string . "\n");
            return;
        }
        if ($callable instanceof \Closure) {
            $string .= "\n- Type: `closure`";
            $r = new \ReflectionFunction($callable);
            if ($r->is_anonymous()) {
                $this->write($string . "\n");
                return;
            }
            $string .= "\n" . \sprintf('- Name: `%s`', $r->name);
            if ($class = $r->get_closure_called_class()) {
                $string .= "\n" . \sprintf('- Class: `%s`', $class->name);
                if (!$r->get_closure_this()) {
                    $string .= "\n- Static: yes";
                }
            }
            $this->write($string . "\n");
            return;
        }
        if (method_exists($callable, '__invoke')) {
            $string .= "\n- Type: `object`";
            $string .= "\n" . \sprintf('- Name: `%s`', $callable::class);
            $this->write($string . "\n");
            return;
        }
        throw new \InvalidArgumentException('Callable is not describable.');
    }
    private function format_router_config(array $array): string
    {
        if (!$array) {
            return 'NONE';
        }
        $string = '';
        ksort($array);
        foreach ($array as $name => $value) {
            $string .= "\n" . '    - `' . $name . '`: ' . $this->format_value($value);
        }
        return $string;
    }
}