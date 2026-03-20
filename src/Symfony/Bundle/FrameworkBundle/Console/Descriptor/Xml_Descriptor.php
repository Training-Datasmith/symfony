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
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
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
class Xml_Descriptor extends Descriptor
{
    protected function describe_route_collection(Route_Collection $routes, array $options = []): void
    {
        $this->write_document($this->get_route_collection_document($routes, $options));
    }
    protected function describe_route(Route $route, array $options = []): void
    {
        $this->write_document($this->get_route_document($route, $options['name'] ?? null));
    }
    protected function describe_container_parameters(Parameter_Bag $parameters, array $options = []): void
    {
        $this->write_document($this->get_container_parameters_document($parameters));
    }
    protected function describe_container_tags(Container_Builder $container, array $options = []): void
    {
        $this->write_document($this->get_container_tags_document($container, isset($options['show_hidden']) && $options['show_hidden']));
    }
    protected function describe_container_service(object $service, array $options = [], ?Container_Builder $container = null): void
    {
        if (!isset($options['id'])) {
            throw new \InvalidArgumentException('An "id" option must be provided.');
        }
        $this->write_document($this->get_container_service_document($service, $options['id'], $container));
    }
    protected function describe_container_services(Container_Builder $container, array $options = []): void
    {
        $this->write_document($this->get_container_services_document($container, $options['tag'] ?? null, isset($options['show_hidden']) && $options['show_hidden'], $options['filter'] ?? null));
    }
    protected function describe_container_definition(Definition $definition, array $options = [], ?Container_Builder $container = null): void
    {
        $this->write_document($this->get_container_definition_document($definition, $options['id'] ?? null, isset($options['omit_tags']) && $options['omit_tags'], $container));
    }
    protected function describe_container_alias(Alias $alias, array $options = [], ?Container_Builder $container = null): void
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($dom->import_node($this->get_container_alias_document($alias, $options['id'] ?? null)->child_nodes->item(0), true));
        if ($container) {
            $dom->append_child($dom->import_node($this->get_container_definition_document($container->get_definition((string) $alias), (string) $alias, false, $container)->child_nodes->item(0), true));
        }
        $this->write_document($dom);
    }
    protected function describe_event_dispatcher_listeners(Event_Dispatcher_Interface $event_dispatcher, array $options = []): void
    {
        $this->write_document($this->get_event_dispatcher_listeners_document($event_dispatcher, $options));
    }
    protected function describe_callable(mixed $callable, array $options = []): void
    {
        $this->write_document($this->get_callable_document($callable));
    }
    protected function describe_container_parameter(mixed $parameter, ?array $deprecation, array $options = []): void
    {
        $this->write_document($this->get_container_parameter_document($parameter, $deprecation, $options));
    }
    protected function describe_container_env_vars(array $envs, array $options = []): void
    {
        throw new LogicException('Using the XML format to debug environment variables is not supported.');
    }
    protected function describe_container_deprecations(Container_Builder $container, array $options = []): void
    {
        $container_deprecation_file_path = \sprintf('%s/%sDeprecations.log', $container->get_parameter('kernel.build_dir'), $container->get_parameter('kernel.container_class'));
        if (!file_exists($container_deprecation_file_path)) {
            throw new RuntimeException('The deprecation file does not exist, please try warming the cache first.');
        }
        $logs = unserialize(file_get_contents($container_deprecation_file_path));
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($deprecations_xml = $dom->create_element('deprecations'));
        $remaining_count = 0;
        foreach ($logs as $log) {
            $deprecations_xml->append_child($deprecation_xml = $dom->create_element('deprecation'));
            $deprecation_xml->set_attribute('count', $log['count']);
            $deprecation_xml->append_child($dom->create_element('message', $log['message']));
            $deprecation_xml->append_child($dom->create_element('file', $log['file']));
            $deprecation_xml->append_child($dom->create_element('line', $log['line']));
            $remaining_count += $log['count'];
        }
        $deprecations_xml->set_attribute('remainingCount', $remaining_count);
        $this->write_document($dom);
    }
    private function write_document(\Dom_Document $dom): void
    {
        $dom->format_output = true;
        $this->write($dom->save_xml());
    }
    private function get_route_collection_document(Route_Collection $routes, array $options): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($routes_xml = $dom->create_element('routes'));
        foreach ($routes->all() as $name => $route) {
            $route_xml = $this->get_route_document($route, $name);
            if (($show_aliases ??= $options['show_aliases'] ?? false) && $aliases = ($reverse_aliases ??= $this->get_reverse_aliases($routes))[$name] ?? []) {
                $route_xml->first_child->append_child($aliases_xml = $route_xml->create_element('aliases'));
                foreach ($aliases as $alias) {
                    $aliases_xml->append_child($alias_xml = $route_xml->create_element('alias'));
                    $alias_xml->append_child(new \Dom_Text($alias));
                }
            }
            $routes_xml->append_child($routes_xml->owner_document->import_node($route_xml->child_nodes->item(0), true));
        }
        return $dom;
    }
    private function get_route_document(Route $route, ?string $name = null): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($route_xml = $dom->create_element('route'));
        if ($name) {
            $route_xml->set_attribute('name', $name);
        }
        $route_xml->set_attribute('class', $route::class);
        $route_xml->append_child($path_xml = $dom->create_element('path'));
        $path_xml->set_attribute('regex', $route->compile()->get_regex());
        $path_xml->append_child(new \Dom_Text($route->get_path()));
        if ('' !== $route->get_host()) {
            $route_xml->append_child($host_xml = $dom->create_element('host'));
            $host_xml->set_attribute('regex', $route->compile()->get_host_regex());
            $host_xml->append_child(new \Dom_Text($route->get_host()));
        }
        foreach ($route->get_schemes() as $scheme) {
            $route_xml->append_child($scheme_xml = $dom->create_element('scheme'));
            $scheme_xml->append_child(new \Dom_Text($scheme));
        }
        foreach ($route->get_methods() as $method) {
            $route_xml->append_child($method_xml = $dom->create_element('method'));
            $method_xml->append_child(new \Dom_Text($method));
        }
        if ($route->get_defaults()) {
            $route_xml->append_child($defaults_xml = $dom->create_element('defaults'));
            foreach ($route->get_defaults() as $attribute => $value) {
                $defaults_xml->append_child($default_xml = $dom->create_element('default'));
                $default_xml->set_attribute('key', $attribute);
                $default_xml->append_child(new \Dom_Text($this->format_value($value)));
            }
        }
        $origin_requirements = $requirements = $route->get_requirements();
        unset($requirements['_scheme'], $requirements['_method']);
        if ($requirements) {
            $route_xml->append_child($requirements_xml = $dom->create_element('requirements'));
            foreach ($origin_requirements as $attribute => $pattern) {
                $requirements_xml->append_child($requirement_xml = $dom->create_element('requirement'));
                $requirement_xml->set_attribute('key', $attribute);
                $requirement_xml->append_child(new \Dom_Text($pattern));
            }
        }
        if ($route->get_options()) {
            $route_xml->append_child($options_xml = $dom->create_element('options'));
            foreach ($route->get_options() as $name => $value) {
                $options_xml->append_child($option_xml = $dom->create_element('option'));
                $option_xml->set_attribute('key', $name);
                $option_xml->append_child(new \Dom_Text($this->format_value($value)));
            }
        }
        if ('' !== $route->get_condition()) {
            $route_xml->append_child($condition_xml = $dom->create_element('condition'));
            $condition_xml->append_child(new \Dom_Text($route->get_condition()));
        }
        return $dom;
    }
    private function get_container_parameters_document(Parameter_Bag $parameters): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($parameters_xml = $dom->create_element('parameters'));
        $deprecated_parameters = $parameters->all_deprecated();
        foreach ($this->sort_parameters($parameters) as $key => $value) {
            $parameters_xml->append_child($parameter_xml = $dom->create_element('parameter'));
            $parameter_xml->set_attribute('key', $key);
            $parameter_xml->append_child(new \Dom_Text($this->format_parameter($value)));
            if (isset($deprecated_parameters[$key])) {
                $parameter_xml->set_attribute('deprecated', \sprintf('Since %s %s: %s', $deprecated_parameters[$key][0], $deprecated_parameters[$key][1], \sprintf(...\array_slice($deprecated_parameters[$key], 2))));
            }
        }
        return $dom;
    }
    private function get_container_tags_document(Container_Builder $container, bool $show_hidden = false): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($container_xml = $dom->create_element('container'));
        foreach ($this->find_definitions_by_tag($container, $show_hidden) as $tag => $definitions) {
            $container_xml->append_child($tag_xml = $dom->create_element('tag'));
            $tag_xml->set_attribute('name', $tag);
            foreach ($definitions as $service_id => $definition) {
                $definition_xml = $this->get_container_definition_document($definition, $service_id, true, $container);
                $tag_xml->append_child($dom->import_node($definition_xml->child_nodes->item(0), true));
            }
        }
        return $dom;
    }
    private function get_container_service_document(object $service, string $id, ?Container_Builder $container = null): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        if ($service instanceof Alias) {
            $dom->append_child($dom->import_node($this->get_container_alias_document($service, $id)->child_nodes->item(0), true));
            if ($container) {
                $dom->append_child($dom->import_node($this->get_container_definition_document($container->get_definition((string) $service), (string) $service, false, $container)->child_nodes->item(0), true));
            }
        } elseif ($service instanceof Definition) {
            $dom->append_child($dom->import_node($this->get_container_definition_document($service, $id, false, $container)->child_nodes->item(0), true));
        } else {
            $dom->append_child($service_xml = $dom->create_element('service'));
            $service_xml->set_attribute('id', $id);
            $service_xml->set_attribute('class', $service::class);
        }
        return $dom;
    }
    /**
     * @param (callable(string):bool)|null $filter
     */
    private function get_container_services_document(Container_Builder $container, ?string $tag = null, bool $show_hidden = false, ?callable $filter = null): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($container_xml = $dom->create_element('container'));
        $service_ids = $tag ? $this->sort_tagged_services_by_priority($container->find_tagged_service_ids($tag)) : $this->sort_service_ids($container->get_service_ids());
        if ($filter) {
            $service_ids = array_filter($service_ids, $filter);
        }
        foreach ($service_ids as $service_id) {
            $service = $this->resolve_service_definition($container, $service_id);
            if ($show_hidden xor '.' === ($service_id[0] ?? null)) {
                continue;
            }
            if ($service instanceof Definition && $service->has_tag('container.excluded')) {
                continue;
            }
            $service_xml = $this->get_container_service_document($service, $service_id, $service instanceof Definition ? $container : null);
            $container_xml->append_child($container_xml->owner_document->import_node($service_xml->child_nodes->item(0), true));
        }
        return $dom;
    }
    private function get_container_definition_document(Definition $definition, ?string $id = null, bool $omit_tags = false, ?Container_Builder $container = null): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($service_xml = $dom->create_element('definition'));
        if ($id) {
            $service_xml->set_attribute('id', $id);
        }
        if ('' !== $class_description = $this->get_class_description((string) $definition->get_class())) {
            $service_xml->append_child($description_xml = $dom->create_element('description'));
            $description_xml->append_child($dom->create_cdata_section($class_description));
        }
        $service_xml->set_attribute('class', $definition->get_class() ?? '');
        if ($factory = $definition->get_factory()) {
            $service_xml->append_child($factory_xml = $dom->create_element('factory'));
            if (\is_array($factory)) {
                if ($factory[0] instanceof Reference) {
                    $factory_xml->set_attribute('service', (string) $factory[0]);
                } elseif ($factory[0] instanceof Definition) {
                    $factory_xml->set_attribute('service', \sprintf('inline factory service (%s)', $factory[0]->get_class() ?? 'not configured'));
                } else {
                    $factory_xml->set_attribute('class', $factory[0]);
                }
                $factory_xml->set_attribute('method', $factory[1]);
            } else {
                $factory_xml->set_attribute('function', $factory);
            }
        }
        $service_xml->set_attribute('public', $definition->is_public() ? 'true' : 'false');
        $service_xml->set_attribute('synthetic', $definition->is_synthetic() ? 'true' : 'false');
        $service_xml->set_attribute('lazy', $definition->is_lazy() ? 'true' : 'false');
        $service_xml->set_attribute('shared', $definition->is_shared() ? 'true' : 'false');
        $service_xml->set_attribute('abstract', $definition->is_abstract() ? 'true' : 'false');
        $service_xml->set_attribute('autowired', $definition->is_autowired() ? 'true' : 'false');
        $service_xml->set_attribute('autoconfigured', $definition->is_autoconfigured() ? 'true' : 'false');
        if ($definition->is_deprecated()) {
            $service_xml->set_attribute('deprecated', 'true');
            $service_xml->set_attribute('deprecation_message', $definition->get_deprecation($id)['message']);
        } else {
            $service_xml->set_attribute('deprecated', 'false');
        }
        $service_xml->set_attribute('file', $definition->get_file() ?? '');
        $calls = $definition->get_method_calls();
        if (\count($calls) > 0) {
            $service_xml->append_child($calls_xml = $dom->create_element('calls'));
            foreach ($calls as $call_data) {
                $calls_xml->append_child($call_xml = $dom->create_element('call'));
                $call_xml->set_attribute('method', $call_data[0]);
                if ($call_data[2] ?? false) {
                    $call_xml->set_attribute('returns-clone', 'true');
                }
            }
        }
        foreach ($this->get_argument_nodes($definition->get_arguments(), $dom, $container) as $node) {
            $service_xml->append_child($node);
        }
        if (!$omit_tags) {
            if ($tags = $this->sort_tags_by_priority($container ? $this->resolve_priority_service_tags($container, $definition) : $definition->get_tags())) {
                $service_xml->append_child($tags_xml = $dom->create_element('tags'));
                foreach ($tags as $tag_name => $tag_data) {
                    foreach ($tag_data as $parameters) {
                        $tags_xml->append_child($tag_xml = $dom->create_element('tag'));
                        $tag_xml->set_attribute('name', $tag_name);
                        foreach ($parameters as $name => $value) {
                            $tag_xml->append_child($parameter_xml = $dom->create_element('parameter'));
                            $parameter_xml->set_attribute('name', $name);
                            $parameter_xml->append_child(new \Dom_Text($this->format_parameter($value)));
                        }
                    }
                }
            }
        }
        if (null !== $container && null !== $id) {
            $edges = $this->get_service_edges($container, $id);
            if ($edges) {
                $service_xml->append_child($usages_xml = $dom->create_element('usages'));
                foreach ($edges as $edge) {
                    $usages_xml->append_child($usage_xml = $dom->create_element('usage'));
                    $usage_xml->append_child(new \Dom_Text($edge));
                }
            }
            $stack = $this->get_decoration_stack($container, $id);
            if (\count($stack) > 1) {
                $service_xml->append_child($stack_xml = $dom->create_element('decoration-stack'));
                foreach ($stack as $item) {
                    $stack_xml->append_child($item_xml = $dom->create_element('service'));
                    $item_xml->set_attribute('id', $item['id']);
                    $item_xml->set_attribute('class', $item['class']);
                    $item_xml->set_attribute('priority', $item['priority']);
                }
            }
        }
        return $dom;
    }
    /**
     * @return \DOMNode[]
     */
    private function get_argument_nodes(array $arguments, \Dom_Document $dom, ?Container_Builder $container = null): array
    {
        $nodes = [];
        foreach ($arguments as $argument_key => $argument) {
            $argument_xml = $dom->create_element('argument');
            if (\is_string($argument_key)) {
                $argument_xml->set_attribute('key', $argument_key);
            }
            if ($argument instanceof Service_Closure_Argument) {
                $argument = $argument->get_values()[0];
            }
            if ($argument instanceof Reference) {
                $argument_xml->set_attribute('type', 'service');
                $argument_xml->set_attribute('id', (string) $argument);
            } elseif ($argument instanceof Iterator_Argument || $argument instanceof Service_Locator_Argument) {
                $argument_xml->set_attribute('type', $argument instanceof Iterator_Argument ? 'iterator' : 'service_locator');
                foreach ($this->get_argument_nodes($argument->get_values(), $dom, $container) as $child_argument_xml) {
                    $argument_xml->append_child($child_argument_xml);
                }
            } elseif ($argument instanceof Definition) {
                $argument_xml->append_child($dom->import_node($this->get_container_definition_document($argument, null, false, $container)->child_nodes->item(0), true));
            } elseif ($argument instanceof Abstract_Argument) {
                $argument_xml->set_attribute('type', 'abstract');
                $argument_xml->append_child(new \Dom_Text($argument->get_text()));
            } elseif (\is_array($argument)) {
                $argument_xml->set_attribute('type', 'collection');
                foreach ($this->get_argument_nodes($argument, $dom, $container) as $child_argument_xml) {
                    $argument_xml->append_child($child_argument_xml);
                }
            } elseif ($argument instanceof \Unit_Enum) {
                $argument_xml->set_attribute('type', 'constant');
                $argument_xml->append_child(new \Dom_Text(ltrim(var_export($argument, true), '\\')));
            } else {
                $argument_xml->append_child(new \Dom_Text($argument));
            }
            $nodes[] = $argument_xml;
        }
        return $nodes;
    }
    private function get_container_alias_document(Alias $alias, ?string $id = null): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($alias_xml = $dom->create_element('alias'));
        if ($id) {
            $alias_xml->set_attribute('id', $id);
        }
        $alias_xml->set_attribute('service', (string) $alias);
        $alias_xml->set_attribute('public', $alias->is_public() ? 'true' : 'false');
        return $dom;
    }
    private function get_container_parameter_document(mixed $parameter, ?array $deprecation, array $options = []): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($parameter_xml = $dom->create_element('parameter'));
        if (isset($options['parameter'])) {
            $parameter_xml->set_attribute('key', $options['parameter']);
            if ($deprecation) {
                $parameter_xml->set_attribute('deprecated', \sprintf('Since %s %s: %s', $deprecation[0], $deprecation[1], \sprintf(...\array_slice($deprecation, 2))));
            }
        }
        $parameter_xml->append_child(new \Dom_Text($this->format_parameter($parameter)));
        return $dom;
    }
    private function get_event_dispatcher_listeners_document(Event_Dispatcher_Interface $event_dispatcher, array $options): \Dom_Document
    {
        $event = $options['event'] ?? null;
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($event_dispatcher_xml = $dom->create_element('event-dispatcher'));
        if (null !== $event) {
            $registered_listeners = $event_dispatcher->get_listeners($event);
            $this->append_event_listener_document($event_dispatcher, $event, $event_dispatcher_xml, $registered_listeners);
        } else {
            // Try to see if "events" exists
            $registered_listeners = \array_key_exists('events', $options) ? array_combine($options['events'], array_map($event_dispatcher->get_listeners(...), $options['events'])) : $event_dispatcher->get_listeners();
            ksort($registered_listeners);
            foreach ($registered_listeners as $event_listened => $event_listeners) {
                $event_dispatcher_xml->append_child($event_xml = $dom->create_element('event'));
                $event_xml->set_attribute('name', $event_listened);
                $this->append_event_listener_document($event_dispatcher, $event_listened, $event_xml, $event_listeners);
            }
        }
        return $dom;
    }
    private function append_event_listener_document(Event_Dispatcher_Interface $event_dispatcher, string $event, \Dom_Element $element, array $event_listeners): void
    {
        foreach ($event_listeners as $listener) {
            $callable_xml = $this->get_callable_document($listener);
            $callable_xml->child_nodes->item(0)->set_attribute('priority', $event_dispatcher->get_listener_priority($event, $listener));
            $element->append_child($element->owner_document->import_node($callable_xml->child_nodes->item(0), true));
        }
    }
    private function get_callable_document(mixed $callable): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($callable_xml = $dom->create_element('callable'));
        if (\is_array($callable)) {
            $callable_xml->set_attribute('type', 'function');
            if (\is_object($callable[0])) {
                $callable_xml->set_attribute('name', $callable[1]);
                $callable_xml->set_attribute('class', $callable[0]::class);
            } else if (!str_starts_with((string) $callable[1], 'parent::')) {
                $callable_xml->set_attribute('name', $callable[1]);
                $callable_xml->set_attribute('class', $callable[0]);
                $callable_xml->set_attribute('static', 'true');
            } else {
                $callable_xml->set_attribute('name', substr((string) $callable[1], 8));
                $callable_xml->set_attribute('class', $callable[0]);
                $callable_xml->set_attribute('static', 'true');
                $callable_xml->set_attribute('parent', 'true');
            }
            return $dom;
        }
        if (\is_string($callable)) {
            $callable_xml->set_attribute('type', 'function');
            if (!str_contains($callable, '::')) {
                $callable_xml->set_attribute('name', $callable);
            } else {
                $callable_parts = explode('::', $callable);
                $callable_xml->set_attribute('name', $callable_parts[1]);
                $callable_xml->set_attribute('class', $callable_parts[0]);
                $callable_xml->set_attribute('static', 'true');
            }
            return $dom;
        }
        if ($callable instanceof \Closure) {
            $callable_xml->set_attribute('type', 'closure');
            $r = new \ReflectionFunction($callable);
            if ($r->is_anonymous()) {
                return $dom;
            }
            $callable_xml->set_attribute('name', $r->name);
            if ($class = $r->get_closure_called_class()) {
                $callable_xml->set_attribute('class', $class->name);
                if (!$r->get_closure_this()) {
                    $callable_xml->set_attribute('static', 'true');
                }
            }
            return $dom;
        }
        if (method_exists($callable, '__invoke')) {
            $callable_xml->set_attribute('type', 'object');
            $callable_xml->set_attribute('name', $callable::class);
            return $dom;
        }
        throw new \InvalidArgumentException('Callable is not describable.');
    }
}