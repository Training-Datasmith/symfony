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

use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\Table_Cell;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Argument\Abstract_Argument;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Error_Handler\Error_Renderer\File_Link_Formatter;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher_Interface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Route_Collection;
/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Text_Descriptor extends Descriptor
{
    private const VERB_COLORS = ['ANY' => 'default', 'GET' => 'blue', 'QUERY' => 'blue', 'HEAD' => 'magenta', 'OPTIONS' => 'blue', 'POST' => 'green', 'PUT' => 'yellow', 'PATCH' => 'yellow', 'DELETE' => 'red'];
    public function __construct(private readonly ?File_Link_Formatter $file_link_formatter = null)
    {
    }
    protected function describe_route_collection(Route_Collection $routes, array $options = []): void
    {
        $show_aliases = $options['show_aliases'] ?? false;
        $show_controllers = $options['show_controllers'] ?? false;
        $table_rows = [];
        $should_show_scheme = false;
        $should_show_host = false;
        foreach ($routes->all() as $name => $route) {
            $controller = $route->get_default('_controller');
            $scheme = $route->get_schemes() ? implode('|', $route->get_schemes()) : 'ANY';
            $should_show_scheme = $should_show_scheme || 'ANY' !== $scheme;
            $host = '' !== $route->get_host() ? $route->get_host() : 'ANY';
            $should_show_host = $should_show_host || 'ANY' !== $host;
            $row = ['Name' => $name, 'Methods' => $this->format_methods($route->get_methods()), 'Scheme' => $scheme, 'Host' => $host, 'Path' => $route->get_path()];
            if ($show_controllers) {
                $row['Controller'] = $controller ? $this->format_controller_link($controller, $this->format_callable($controller), $options['container'] ?? null) : '';
            }
            if ($show_aliases) {
                $row['Aliases'] = implode('|', $this->get_reverse_aliases($routes)[$name] ?? []);
            }
            $table_rows[] = $row;
        }
        $table_headers = ['Name', 'Method'];
        if ($should_show_scheme) {
            $table_headers[] = 'Scheme';
        } else {
            array_walk($table_rows, static function (array &$row): void {
                unset($row['Scheme']);
            });
        }
        if ($should_show_host) {
            $table_headers[] = 'Host';
        } else {
            array_walk($table_rows, static function (array &$row): void {
                unset($row['Host']);
            });
        }
        $table_headers[] = 'Path';
        if ($show_controllers) {
            $table_headers[] = 'Controller';
        }
        if ($show_aliases) {
            $table_headers[] = 'Aliases';
        }
        if (isset($options['output'])) {
            $options['output']->table($table_headers, $table_rows);
        } else {
            $table = new Table($this->get_output());
            $table->set_headers($table_headers)->set_rows($table_rows);
            $table->render();
        }
    }
    protected function describe_route(Route $route, array $options = []): void
    {
        $defaults = $route->get_defaults();
        if (isset($defaults['_controller'])) {
            $defaults['_controller'] = $this->format_controller_link($defaults['_controller'], $this->format_callable($defaults['_controller']), $options['container'] ?? null);
        }
        $table_headers = ['Property', 'Value'];
        $table_rows = [['Route Name', $options['name'] ?? ''], ['Path', $route->get_path()], ['Path Regex', $route->compile()->get_regex()], ['Host', '' !== $route->get_host() ? $route->get_host() : 'ANY'], ['Host Regex', '' !== $route->get_host() ? $route->compile()->get_host_regex() : ''], ['Scheme', $route->get_schemes() ? implode('|', $route->get_schemes()) : 'ANY'], ['Method', $this->format_methods($route->get_methods())], ['Requirements', $route->get_requirements() ? $this->format_router_config($route->get_requirements()) : 'NO CUSTOM'], ['Class', $route::class], ['Defaults', $this->format_router_config($defaults)], ['Options', $this->format_router_config($route->get_options())]];
        if ('' !== $route->get_condition()) {
            $table_rows[] = ['Condition', $route->get_condition()];
        }
        $table = new Table($this->get_output());
        $table->set_headers($table_headers)->set_rows($table_rows);
        $table->render();
    }
    protected function describe_container_parameters(Parameter_Bag $parameters, array $options = []): void
    {
        $table_headers = ['Parameter', 'Value'];
        $deprecated_parameters = $parameters->all_deprecated();
        $table_rows = [];
        foreach ($this->sort_parameters($parameters) as $parameter => $value) {
            $table_rows[] = [$parameter, $this->format_parameter($value)];
            if (isset($deprecated_parameters[$parameter])) {
                $table_rows[] = [new Table_Cell(\sprintf('<comment>(Since %s %s: %s)</comment>', $deprecated_parameters[$parameter][0], $deprecated_parameters[$parameter][1], \sprintf(...\array_slice($deprecated_parameters[$parameter], 2))), ['colspan' => 2])];
            }
        }
        $options['output']->title('Symfony Container Parameters');
        $options['output']->table($table_headers, $table_rows);
    }
    protected function describe_container_tags(Container_Builder $container, array $options = []): void
    {
        $show_hidden = isset($options['show_hidden']) && $options['show_hidden'];
        if ($show_hidden) {
            $options['output']->title('Symfony Container Hidden Tags');
        } else {
            $options['output']->title('Symfony Container Tags');
        }
        foreach ($this->find_definitions_by_tag($container, $show_hidden) as $tag => $definitions) {
            $options['output']->section(\sprintf('"%s" tag', $tag));
            $options['output']->listing(array_keys($definitions));
        }
    }
    protected function describe_container_service(object $service, array $options = [], ?Container_Builder $container = null): void
    {
        if (!isset($options['id'])) {
            throw new \InvalidArgumentException('An "id" option must be provided.');
        }
        if ($service instanceof Alias) {
            $this->describe_container_alias($service, $options, $container);
        } elseif ($service instanceof Definition) {
            $this->describe_container_definition($service, $options, $container);
        } else {
            $options['output']->title(\sprintf('Information for Service "<info>%s</info>"', $options['id']));
            $options['output']->table(['Service ID', 'Class'], [[$options['id'], $service::class]]);
        }
    }
    protected function describe_container_services(Container_Builder $container, array $options = []): void
    {
        $show_hidden = isset($options['show_hidden']) && $options['show_hidden'];
        $show_tag = $options['tag'] ?? null;
        if ($show_hidden) {
            $title = 'Symfony Container Hidden Services';
        } else {
            $title = 'Symfony Container Services';
        }
        if ($show_tag) {
            $title .= \sprintf(' Tagged with "%s" Tag', $options['tag']);
        }
        $options['output']->title($title);
        if (isset($options['tag']) && $options['tag']) {
            $services = [];
            foreach ($container->find_tagged_service_ids($options['tag']) as $service_id => $tags) {
                $definition = $container->get_definition($service_id);
                $services[$service_id] = $this->resolve_priority_service_tags($container, $definition, $options['tag']);
            }
            $service_ids = $this->sort_tagged_services_by_priority($services);
        } else {
            $service_ids = $this->sort_service_ids($container->get_service_ids());
        }
        $max_tags = [];
        if (isset($options['filter'])) {
            $service_ids = array_filter($service_ids, $options['filter']);
        }
        foreach ($service_ids as $key => $service_id) {
            $definition = $this->resolve_service_definition($container, $service_id);
            // filter out hidden services unless shown explicitly
            if ($show_hidden xor '.' === ($service_id[0] ?? null)) {
                unset($service_ids[$key]);
                continue;
            }
            if ($definition instanceof Definition) {
                if ($definition->has_tag('container.excluded')) {
                    unset($service_ids[$key]);
                    continue;
                }
                if ($show_tag) {
                    $tags = $services[$service_id];
                    foreach ($tags as $tag) {
                        foreach ($tag as $key => $value) {
                            if (!isset($max_tags[$key])) {
                                $max_tags[$key] = \strlen((string) $key);
                            }
                            if (\is_array($value)) {
                                $value = $this->format_parameter($value);
                            }
                            if (\strlen((string) $value) > $max_tags[$key]) {
                                $max_tags[$key] = \strlen((string) $value);
                            }
                        }
                    }
                }
            }
        }
        $tags_count = \count($max_tags);
        $tags_names = array_keys($max_tags);
        $table_headers = array_merge(['Service ID'], $tags_names, ['Class name']);
        $table_rows = [];
        $raw_output = isset($options['raw_text']) && $options['raw_text'];
        foreach ($service_ids as $service_id) {
            $definition = $this->resolve_service_definition($container, $service_id);
            $styled_service_id = $raw_output ? $service_id : \sprintf('<fg=cyan>%s</fg=cyan>', Output_Formatter::escape($service_id));
            if ($definition instanceof Definition) {
                if ($show_tag) {
                    foreach ($this->sort_by_priority($services[$service_id]) as $key => $tag) {
                        $tag_values = [];
                        foreach ($tags_names as $tag_name) {
                            if (\is_array($tag_value = $tag[$tag_name] ?? '')) {
                                $tag_value = $this->format_parameter($tag_value);
                            }
                            $tag_values[] = $tag_value;
                        }
                        if (0 === $key) {
                            $table_rows[] = array_merge([$service_id], $tag_values, [$definition->get_class()]);
                        } else {
                            $table_rows[] = array_merge([' (same service as previous, another tag)'], $tag_values, ['']);
                        }
                    }
                } else {
                    $table_rows[] = [$styled_service_id, $definition->get_class()];
                }
            } elseif ($definition instanceof Alias) {
                $alias = $definition;
                $table_rows[] = array_merge([$styled_service_id, \sprintf('alias for "%s"', $alias)], $tags_count ? array_fill(0, $tags_count, '') : []);
            } else {
                $table_rows[] = array_merge([$styled_service_id, $definition::class], $tags_count ? array_fill(0, $tags_count, '') : []);
            }
        }
        $options['output']->table($table_headers, $table_rows);
    }
    protected function describe_container_definition(Definition $definition, array $options = [], ?Container_Builder $container = null): void
    {
        if (isset($options['id'])) {
            $options['output']->title(\sprintf('Information for Service "<info>%s</info>"', $options['id']));
        }
        if ('' !== $class_description = $this->get_class_description((string) $definition->get_class())) {
            $options['output']->text($class_description . "\n");
        }
        $table_headers = ['Option', 'Value'];
        $table_rows[] = ['Service ID', $options['id'] ?? '-'];
        $table_rows[] = ['Class', $definition->get_class() ?: '-'];
        $omit_tags = isset($options['omit_tags']) && $options['omit_tags'];
        if (!$omit_tags && $tags = $container ? $this->resolve_priority_service_tags($container, $definition) : $definition->get_tags()) {
            $tag_information = [];
            foreach ($tags as $tag_name => $tag_data) {
                foreach ($tag_data as $tag_parameters) {
                    $parameters = array_map(fn(string $key, $value): string => \sprintf('<info>%s</info>: %s', $key, \is_array($value) ? $this->format_parameter($value) : $value), array_keys($tag_parameters), array_values($tag_parameters));
                    $parameters = implode(', ', $parameters);
                    if ('' === $parameters) {
                        $tag_information[] = \sprintf('%s', $tag_name);
                    } else {
                        $tag_information[] = \sprintf('%s (%s)', $tag_name, $parameters);
                    }
                }
            }
            $tag_information = implode("\n", $tag_information);
        } else {
            $tag_information = '-';
        }
        $table_rows[] = ['Tags', $tag_information];
        $calls = $definition->get_method_calls();
        if (\count($calls) > 0) {
            $call_information = [];
            foreach ($calls as $call) {
                $call_information[] = $call[0];
            }
            $table_rows[] = ['Calls', implode(', ', $call_information)];
        }
        $table_rows[] = ['Public', $definition->is_public() ? 'yes' : 'no'];
        $table_rows[] = ['Synthetic', $definition->is_synthetic() ? 'yes' : 'no'];
        $table_rows[] = ['Lazy', $definition->is_lazy() ? 'yes' : 'no'];
        $table_rows[] = ['Shared', $definition->is_shared() ? 'yes' : 'no'];
        $table_rows[] = ['Abstract', $definition->is_abstract() ? 'yes' : 'no'];
        $table_rows[] = ['Autowired', $definition->is_autowired() ? 'yes' : 'no'];
        $table_rows[] = ['Autoconfigured', $definition->is_autoconfigured() ? 'yes' : 'no'];
        if ($definition->get_file()) {
            $table_rows[] = ['Required File', $definition->get_file()];
        }
        if ($factory = $definition->get_factory()) {
            if (\is_array($factory)) {
                if ($factory[0] instanceof Reference) {
                    $table_rows[] = ['Factory Service', $factory[0]];
                } elseif ($factory[0] instanceof Definition) {
                    $table_rows[] = ['Factory Service', \sprintf('inline factory service (%s)', $factory[0]->get_class() ?? 'class not configured')];
                } else {
                    $table_rows[] = ['Factory Class', $factory[0]];
                }
                $table_rows[] = ['Factory Method', $factory[1]];
            } else {
                $table_rows[] = ['Factory Function', $factory];
            }
        }
        $arguments_information = [];
        if ($arguments = $definition->get_arguments()) {
            foreach ($arguments as $argument) {
                if ($argument instanceof Service_Closure_Argument) {
                    $argument = $argument->get_values()[0];
                }
                if ($argument instanceof Reference) {
                    $arguments_information[] = \sprintf('Service(%s)', (string) $argument);
                } elseif ($argument instanceof Iterator_Argument) {
                    if ($argument instanceof Tagged_Iterator_Argument) {
                        $arguments_information[] = \sprintf('Tagged Iterator for "%s"%s', $argument->get_tag(), $options['is_debug'] ? '' : \sprintf(' (%d element(s))', \count($argument->get_values())));
                    } else {
                        $arguments_information[] = \sprintf('Iterator (%d element(s))', \count($argument->get_values()));
                    }
                    foreach ($argument->get_values() as $ref) {
                        $arguments_information[] = \sprintf('- Service(%s)', $ref);
                    }
                } elseif ($argument instanceof Service_Locator_Argument) {
                    $arguments_information[] = \sprintf('Service locator (%d element(s))', \count($argument->get_values()));
                } elseif ($argument instanceof Definition) {
                    $arguments_information[] = 'Inlined Service';
                } elseif ($argument instanceof \Unit_Enum) {
                    $arguments_information[] = ltrim(var_export($argument, true), '\\');
                } elseif ($argument instanceof Abstract_Argument) {
                    $arguments_information[] = \sprintf('Abstract argument (%s)', $argument->get_text());
                } else {
                    $arguments_information[] = \is_array($argument) ? \sprintf('Array (%d element(s))', \count($argument)) : $argument;
                }
            }
            $table_rows[] = ['Arguments', implode("\n", $arguments_information)];
        }
        $in_edges = null !== $container && isset($options['id']) ? $this->get_service_edges($container, $options['id']) : [];
        $table_rows[] = ['Usages', $in_edges ? implode(\PHP_EOL, $in_edges) : 'none'];
        $options['output']->table($table_headers, $table_rows);
        if (isset($options['id']) && $container) {
            $stack = $this->get_decoration_stack($container, $options['id']);
            if (\count($stack) > 1) {
                $options['output']->section('Decoration Stack');
                $options['output']->table(['ID', 'Class', 'Priority'], array_map(array_values(...), $stack));
            }
        }
    }
    protected function describe_container_deprecations(Container_Builder $container, array $options = []): void
    {
        $container_deprecation_file_path = \sprintf('%s/%sDeprecations.log', $container->get_parameter('kernel.build_dir'), $container->get_parameter('kernel.container_class'));
        if (!file_exists($container_deprecation_file_path)) {
            $options['output']->warning('The deprecation file does not exist, please try warming the cache first.');
            return;
        }
        $logs = unserialize(file_get_contents($container_deprecation_file_path));
        if (0 === \count($logs)) {
            $options['output']->success('There are no deprecations in the logs!');
            return;
        }
        $formatted_logs = [];
        $remaining_count = 0;
        foreach ($logs as $log) {
            $formatted_logs[] = \sprintf("%sx: %s\n      in %s:%s", $log['count'], $log['message'], $log['file'], $log['line']);
            $remaining_count += $log['count'];
        }
        $options['output']->title(\sprintf('Remaining deprecations (%s)', $remaining_count));
        $options['output']->listing($formatted_logs);
    }
    protected function describe_container_alias(Alias $alias, array $options = [], ?Container_Builder $container = null): void
    {
        if ($alias->is_public()) {
            $options['output']->comment(\sprintf('This service is a <info>public</info> alias for the service <info>%s</info>', (string) $alias));
        } else {
            $options['output']->comment(\sprintf('This service is a <comment>private</comment> alias for the service <info>%s</info>', (string) $alias));
        }
        if (!$container) {
            return;
        }
        $this->describe_container_definition($container->get_definition((string) $alias), array_merge($options, ['id' => (string) $alias]), $container);
    }
    protected function describe_container_parameter(mixed $parameter, ?array $deprecation, array $options = []): void
    {
        $parameter_name = $options['parameter'];
        $rows = [[$parameter_name, $this->format_parameter($parameter)]];
        if ($deprecation) {
            $rows[] = [new Table_Cell(\sprintf('<comment>(Since %s %s: %s)</comment>', $deprecation[0], $deprecation[1], \sprintf(...\array_slice($deprecation, 2))), ['colspan' => 2])];
        }
        $options['output']->table(['Parameter', 'Value'], $rows);
    }
    protected function describe_container_env_vars(array $envs, array $options = []): void
    {
        $dump = new Dumper($this->output);
        $options['output']->title('Symfony Container Environment Variables');
        if (null !== $name = $options['name'] ?? null) {
            $options['output']->comment('Displaying detailed environment variable usage matching ' . $name);
            $matches = false;
            foreach ($envs as $env) {
                if ($name === $env['name'] || false !== stripos((string) $env['name'], (string) $name)) {
                    $matches = true;
                    $options['output']->section('%env(' . $env['processor'] . ':' . $env['name'] . ')%');
                    $options['output']->table([], [['<info>Default value</>', $env['default_available'] ? $dump($env['default_value']) : 'n/a'], ['<info>Real value</>', $env['runtime_available'] ? $dump($env['runtime_value']) : 'n/a'], ['<info>Processed value</>', $env['default_available'] || $env['runtime_available'] ? $dump($env['processed_value']) : 'n/a']]);
                }
            }
            if (!$matches) {
                $options['output']->block('None of the environment variables match this name.');
            } else {
                $options['output']->comment('Note real values might be different between web and CLI.');
            }
            return;
        }
        if (!$envs) {
            $options['output']->block('No environment variables are being used.');
            return;
        }
        $rows = [];
        $missing = [];
        foreach ($envs as $env) {
            if (isset($rows[$env['name']])) {
                continue;
            }
            $rows[$env['name']] = [$env['name'], $env['default_available'] ? $dump($env['default_value']) : 'n/a', $env['runtime_available'] ? $dump($env['runtime_value']) : 'n/a'];
            if (!$env['default_available'] && !$env['runtime_available']) {
                $missing[$env['name']] = true;
            }
        }
        $options['output']->table(['Name', 'Default value', 'Real value'], $rows);
        $options['output']->comment('Note real values might be different between web and CLI.');
        if ($missing) {
            $options['output']->warning('The following variables are missing:');
            $options['output']->listing(array_keys($missing));
        }
    }
    protected function describe_event_dispatcher_listeners(Event_Dispatcher_Interface $event_dispatcher, array $options = []): void
    {
        $event = $options['event'] ?? null;
        $dispatcher_service_name = $options['dispatcher_service_name'] ?? null;
        $title = 'Registered Listeners';
        if (null !== $dispatcher_service_name) {
            $title .= \sprintf(' of Event Dispatcher "%s"', $dispatcher_service_name);
        }
        if (null !== $event) {
            $title .= \sprintf(' for "%s" Event', $event);
            $registered_listeners = $event_dispatcher->get_listeners($event);
        } else {
            $title .= ' Grouped by Event';
            // Try to see if "events" exists
            $registered_listeners = \array_key_exists('events', $options) ? array_combine($options['events'], array_map($event_dispatcher->get_listeners(...), $options['events'])) : $event_dispatcher->get_listeners();
        }
        $options['output']->title($title);
        if (null !== $event) {
            $this->render_event_listener_table($event_dispatcher, $event, $registered_listeners, $options['output']);
        } else {
            ksort($registered_listeners);
            foreach ($registered_listeners as $event_listened => $event_listeners) {
                $options['output']->section(\sprintf('"%s" event', $event_listened));
                $this->render_event_listener_table($event_dispatcher, $event_listened, $event_listeners, $options['output']);
            }
        }
    }
    protected function describe_callable(mixed $callable, array $options = []): void
    {
        $this->write_text($this->format_callable($callable), $options);
    }
    private function render_event_listener_table(Event_Dispatcher_Interface $event_dispatcher, string $event, array $event_listeners, Symfony_Style $io): void
    {
        $table_headers = ['Order', 'Callable', 'Priority'];
        $table_rows = [];
        foreach ($event_listeners as $order => $listener) {
            $table_rows[] = [\sprintf('#%d', $order + 1), $this->format_callable($listener), $event_dispatcher->get_listener_priority($event, $listener)];
        }
        $io->table($table_headers, $table_rows);
    }
    private function format_router_config(array $config): string
    {
        if (!$config) {
            return 'NONE';
        }
        ksort($config);
        $config_as_string = '';
        foreach ($config as $key => $value) {
            $config_as_string .= \sprintf("\n%s: %s", $key, $this->format_value($value));
        }
        return trim($config_as_string);
    }
    /**
     * @param array<string> $methods
     */
    private function format_methods(array $methods): string
    {
        if ([] === $methods) {
            $methods = ['ANY'];
        }
        return implode('|', array_map(static fn(string $method): string => \sprintf('<fg=%s>%s</>', self::VERB_COLORS[$method] ?? 'default', $method), $methods));
    }
    /**
     * @param (callable():ContainerBuilder)|null $getContainer
     */
    private function format_controller_link(mixed $controller, string $anchor_text, ?callable $get_container = null): string
    {
        if (null === $this->file_link_formatter) {
            return $anchor_text;
        }
        try {
            if (null === $controller) {
                return $anchor_text;
            }
            if (\is_array($controller)) {
                $r = new \ReflectionMethod($controller[0], $controller[1]);
            } elseif ($controller instanceof \Closure) {
                $r = new \ReflectionFunction($controller);
            } elseif (method_exists($controller, '__invoke')) {
                $r = new \ReflectionMethod($controller, '__invoke');
            } elseif (str_contains((string) $controller, '::')) {
                $r = new \ReflectionMethod(...explode('::', (string) $controller, 2));
            } elseif (!\is_string($controller)) {
                return $anchor_text;
            } else {
                $r = new \ReflectionFunction($controller);
            }
        } catch (\Reflection_Exception) {
            if (\is_array($controller)) {
                $controller = implode('::', $controller);
            }
            $id = $controller;
            $method = '__invoke';
            if ($pos = strpos($controller, '::')) {
                $id = substr($controller, 0, $pos);
                $method = substr($controller, $pos + 2);
            }
            if (!$get_container || !($container = $get_container()) || !$container->has($id)) {
                return $anchor_text;
            }
            try {
                $r = new \ReflectionMethod($container->find_definition($id)->get_class(), $method);
            } catch (\Reflection_Exception) {
                return $anchor_text;
            }
        }
        $file_link = $this->file_link_formatter->format($r->get_file_name(), $r->get_start_line());
        if ($file_link) {
            return \sprintf('<href=%s>%s</>', $file_link, $anchor_text);
        }
        return $anchor_text;
    }
    private function format_callable(mixed $callable): string
    {
        if (\is_array($callable)) {
            if (\is_object($callable[0])) {
                return \sprintf('%s::%s()', $callable[0]::class, $callable[1]);
            }
            return \sprintf('%s::%s()', $callable[0], $callable[1]);
        }
        if (\is_string($callable)) {
            return \sprintf('%s()', $callable);
        }
        if ($callable instanceof \Closure) {
            $r = new \ReflectionFunction($callable);
            if ($r->is_anonymous()) {
                return 'Closure()';
            }
            if ($class = $r->get_closure_called_class()) {
                return \sprintf('%s::%s()', $class->name, $r->name);
            }
            return $r->name . '()';
        }
        if (method_exists($callable, '__invoke')) {
            return \sprintf('%s::__invoke()', $callable::class);
        }
        throw new \InvalidArgumentException('Callable is not describable.');
    }
    private function write_text(string $content, array $options = []): void
    {
        $this->write(isset($options['raw_text']) && $options['raw_text'] ? strip_tags($content) : $content, isset($options['raw_output']) ? !$options['raw_output'] : true);
    }
}