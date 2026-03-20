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
namespace Symfony\Component\Dependency_Injection\Dumper;

use Symfony\Component\Dependency_Injection\Argument\Argument_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\Parameter_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * GraphvizDumper dumps a service container as a graphviz file.
 *
 * You can convert the generated dot file with the dot utility (http://www.graphviz.org/):
 *
 *   dot -Tpng container.dot > foo.png
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Graphviz_Dumper extends Dumper
{
    private array $nodes;
    private array $edges;
    // All values should be strings
    private array $options = ['graph' => ['ratio' => 'compress'], 'node' => ['fontsize' => '11', 'fontname' => 'Arial', 'shape' => 'record'], 'edge' => ['fontsize' => '9', 'fontname' => 'Arial', 'color' => 'grey', 'arrowhead' => 'open', 'arrowsize' => '0.5'], 'node.instance' => ['fillcolor' => '#9999ff', 'style' => 'filled'], 'node.definition' => ['fillcolor' => '#eeeeee'], 'node.missing' => ['fillcolor' => '#ff9999', 'style' => 'filled']];
    /**
     * Dumps the service container as a graphviz graph.
     *
     * Available options:
     *
     *  * graph: The default options for the whole graph
     *  * node: The default options for nodes
     *  * edge: The default options for edges
     *  * node.instance: The default options for services that are defined directly by object instances
     *  * node.definition: The default options for services that are defined via service definition instances
     *  * node.missing: The default options for missing services
     */
    public function dump(array $options = []): string
    {
        foreach (['graph', 'node', 'edge', 'node.instance', 'node.definition', 'node.missing'] as $key) {
            if (isset($options[$key])) {
                $this->options[$key] = array_merge($this->options[$key], $options[$key]);
            }
        }
        $this->nodes = $this->find_nodes();
        $this->edges = [];
        foreach ($this->container->get_definitions() as $id => $definition) {
            $this->edges[$id] = array_merge($this->find_edges($id, $definition->get_arguments(), true, ''), $this->find_edges($id, $definition->get_properties(), false, ''));
            foreach ($definition->get_method_calls() as $call) {
                $this->edges[$id] = array_merge($this->edges[$id], $this->find_edges($id, $call[1], false, $call[0] . '()'));
            }
        }
        return $this->container->resolve_env_placeholders($this->start_dot() . $this->add_nodes() . $this->add_edges() . $this->end_dot(), '__ENV_%s__');
    }
    private function add_nodes(): string
    {
        $code = '';
        foreach ($this->nodes as $id => $node) {
            $aliases = $this->get_aliases($id);
            $code .= \sprintf("  node_%s [label=\"%s\\n%s\\n\", shape=%s%s];\n", $this->dotize($id), $id . ($aliases ? ' (' . implode(', ', $aliases) . ')' : ''), $node['class'], $this->options['node']['shape'], $this->add_attributes($node['attributes']));
        }
        return $code;
    }
    private function add_edges(): string
    {
        $code = '';
        foreach ($this->edges as $id => $edges) {
            foreach ($edges as $edge) {
                $code .= \sprintf("  node_%s -> node_%s [label=\"%s\" style=\"%s\"%s];\n", $this->dotize($id), $this->dotize($edge['to']), $edge['name'], $edge['required'] ? 'filled' : 'dashed', $edge['lazy'] ? ' color="#9999ff"' : '');
            }
        }
        return $code;
    }
    /**
     * Finds all edges belonging to a specific service id.
     */
    private function find_edges(string $id, array $arguments, bool $required, string $name, bool $lazy = false): array
    {
        $edges = [];
        foreach ($arguments as $argument) {
            if ($argument instanceof Parameter) {
                $argument = $this->container->has_parameter($argument) ? $this->container->get_parameter($argument) : null;
            } elseif (\is_string($argument) && preg_match('/^%([^%]+)%$/', $argument, $match)) {
                $argument = $this->container->has_parameter($match[1]) ? $this->container->get_parameter($match[1]) : null;
            }
            if ($argument instanceof Reference) {
                $lazy_edge = $lazy;
                if (!$this->container->has((string) $argument)) {
                    $this->nodes[(string) $argument] = ['name' => $name, 'required' => $required, 'class' => '', 'attributes' => $this->options['node.missing']];
                } elseif ('service_container' !== (string) $argument) {
                    $lazy_edge = $lazy || $this->container->get_definition((string) $argument)->is_lazy();
                }
                $edges[] = [['name' => $name, 'required' => $required, 'to' => $argument, 'lazy' => $lazy_edge]];
            } elseif ($argument instanceof Argument_Interface) {
                $edges[] = $this->find_edges($id, $argument->get_values(), $required, $name, true);
            } elseif ($argument instanceof Definition) {
                $edges[] = $this->find_edges($id, $argument->get_arguments(), $required, '');
                $edges[] = $this->find_edges($id, $argument->get_properties(), false, '');
                foreach ($argument->get_method_calls() as $call) {
                    $edges[] = $this->find_edges($id, $call[1], false, $call[0] . '()');
                }
            } elseif (\is_array($argument)) {
                $edges[] = $this->find_edges($id, $argument, $required, $name, $lazy);
            }
        }
        return array_merge([], ...$edges);
    }
    private function find_nodes(): array
    {
        $nodes = [];
        $container = $this->clone_container();
        foreach ($container->get_definitions() as $id => $definition) {
            $class = $definition->get_class();
            if (str_starts_with((string) $class, '\\')) {
                $class = substr((string) $class, 1);
            }
            try {
                $class = $this->container->get_parameter_bag()->resolve_value($class);
            } catch (Parameter_Not_Found_Exception) {
            }
            $nodes[$id] = ['class' => str_replace('\\', '\\\\', $class), 'attributes' => array_merge($this->options['node.definition'], ['style' => $definition->is_shared() ? 'filled' : 'dotted'])];
            $container->set_definition($id, new Definition('stdClass'));
        }
        foreach ($container->get_service_ids() as $id) {
            if (\array_key_exists($id, $container->get_aliases())) {
                continue;
            }
            if (!$container->has_definition($id)) {
                $nodes[$id] = ['class' => str_replace('\\', '\\\\', $container->get($id)::class), 'attributes' => $this->options['node.instance']];
            }
        }
        return $nodes;
    }
    private function clone_container(): Container_Builder
    {
        $parameter_bag = new Parameter_Bag($this->container->get_parameter_bag()->all());
        $container = new Container_Builder($parameter_bag);
        $container->set_definitions($this->container->get_definitions());
        $container->set_aliases($this->container->get_aliases());
        $container->set_resources($this->container->get_resources());
        foreach ($this->container->get_extensions() as $extension) {
            $container->register_extension($extension);
        }
        return $container;
    }
    private function start_dot(): string
    {
        return \sprintf("digraph sc {\n  %s\n  node [%s];\n  edge [%s];\n\n", $this->add_options($this->options['graph']), $this->add_options($this->options['node']), $this->add_options($this->options['edge']));
    }
    private function end_dot(): string
    {
        return "}\n";
    }
    private function add_attributes(array $attributes): string
    {
        $code = [];
        foreach ($attributes as $k => $v) {
            $code[] = \sprintf('%s="%s"', $k, $v);
        }
        return $code ? ', ' . implode(', ', $code) : '';
    }
    private function add_options(array $options): string
    {
        $code = [];
        foreach ($options as $k => $v) {
            $code[] = \sprintf('%s="%s"', $k, $v);
        }
        return implode(' ', $code);
    }
    private function dotize(string $id): string
    {
        return preg_replace('/\W/i', '_', $id);
    }
    private function get_aliases(string $id): array
    {
        $aliases = [];
        foreach ($this->container->get_aliases() as $alias => $origin) {
            if ($id == $origin) {
                $aliases[] = $alias;
            }
        }
        return $aliases;
    }
}