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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Argument\Argument_Interface;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Expression_Language\Expression;
/**
 * Run this pass before passes that need to know more about the relation of
 * your services.
 *
 * This class will populate the ServiceReferenceGraph with information. You can
 * retrieve the graph in other passes from the compiler.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Analyze_Service_References_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private Service_Reference_Graph $graph;
    private ?Definition $current_definition = null;
    private bool $lazy;
    private bool $by_constructor;
    private bool $by_factory;
    private bool $by_multi_use_argument;
    private array $definitions;
    private array $aliases;
    /**
     * @param bool $onlyConstructorArguments Sets this Service Reference pass to ignore method calls
     */
    public function __construct(private readonly bool $only_constructor_arguments = false, private readonly bool $has_proxy_dumper = true)
    {
        $this->enable_expression_processing();
    }
    /**
     * Processes a ContainerBuilder object to populate the service reference graph.
     */
    public function process(Container_Builder $container): void
    {
        $this->container = $container;
        $this->graph = $container->get_compiler()->get_service_reference_graph();
        $this->graph->clear();
        $this->lazy = false;
        $this->by_constructor = false;
        $this->by_factory = false;
        $this->by_multi_use_argument = false;
        $this->definitions = $container->get_definitions();
        $this->aliases = $container->get_aliases();
        foreach ($this->aliases as $id => $alias) {
            $target_id = $this->get_definition_id((string) $alias);
            $this->graph->connect($id, $alias, $target_id, null !== $target_id ? $this->container->get_definition($target_id) : null);
        }
        try {
            parent::process($container);
        } finally {
            $this->aliases = $this->definitions = [];
        }
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        $lazy = $this->lazy;
        $in_expression = $this->in_expression();
        if ($value instanceof Argument_Interface) {
            $this->lazy = !$this->by_factory || !$value instanceof Iterator_Argument;
            $by_multi_use_argument = $this->by_multi_use_argument;
            if ($value instanceof Iterator_Argument) {
                $this->by_multi_use_argument = true;
            }
            parent::process_value($value->get_values());
            $this->by_multi_use_argument = $by_multi_use_argument;
            $this->lazy = $lazy;
            return $value;
        }
        if ($value instanceof Reference) {
            $target_id = $this->get_definition_id((string) $value);
            $target_definition = null !== $target_id ? $this->container->get_definition($target_id) : null;
            $this->graph->connect($this->current_id, $this->current_definition, $target_id, $target_definition, $value, $this->lazy || $this->has_proxy_dumper && $target_definition?->is_lazy(), Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE === $value->get_invalid_behavior(), $this->by_constructor, $this->by_multi_use_argument);
            if ($in_expression) {
                $this->graph->connect('.internal.reference_in_expression', null, $target_id, $target_definition, $value, $this->lazy || $target_definition?->is_lazy(), true, $this->by_constructor, $this->by_multi_use_argument);
            }
            return $value;
        }
        if (!$value instanceof Definition) {
            return parent::process_value($value, $is_root);
        }
        if ($is_root) {
            if ($value->is_synthetic() || $value->is_abstract()) {
                return $value;
            }
            $this->current_definition = $value;
        } elseif ($this->current_definition === $value) {
            return $value;
        }
        $this->lazy = false;
        $by_constructor = $this->by_constructor;
        $this->by_constructor = $is_root || $by_constructor;
        $by_factory = $this->by_factory;
        $this->by_factory = true;
        if (\is_string($factory = $value->get_factory()) && str_starts_with($factory, '@=')) {
            if (!class_exists(Expression::class)) {
                throw new LogicException('Expressions cannot be used in service factories without the ExpressionLanguage component. Try running "composer require symfony/expression-language".');
            }
            $factory = new Expression(substr($factory, 2));
        }
        $this->process_value($factory);
        $this->by_factory = $by_factory;
        $this->process_value($value->get_arguments());
        $properties = $value->get_properties();
        $setters = $value->get_method_calls();
        // Any references before a "wither" are part of the constructor-instantiation graph
        $last_wither_index = null;
        foreach ($setters as $k => $call) {
            if ($call[2] ?? false) {
                $last_wither_index = $k;
            }
        }
        if (null !== $last_wither_index) {
            $this->process_value($properties);
            $setters = $properties = [];
            foreach ($value->get_method_calls() as $k => $call) {
                if (null === $last_wither_index) {
                    $setters[] = $call;
                    continue;
                }
                if ($last_wither_index === $k) {
                    $last_wither_index = null;
                }
                $this->process_value($call);
            }
        }
        $this->by_constructor = $by_constructor;
        if (!$this->only_constructor_arguments) {
            $this->process_value($properties);
            $this->process_value($setters);
            $this->process_value($value->get_configurator());
        }
        $this->lazy = $lazy;
        return $value;
    }
    private function get_definition_id(string $id): ?string
    {
        while (isset($this->aliases[$id])) {
            $id = (string) $this->aliases[$id];
        }
        return isset($this->definitions[$id]) ? $id : null;
    }
}