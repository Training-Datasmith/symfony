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
namespace Symfony\Component\Form\Flow;

use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Flow\Data_Storage\Data_Storage_Interface;
use Symfony\Component\Form\Flow\Step_Accessor\Step_Accessor_Interface;
use Symfony\Component\Form\Form_Builder;
use Symfony\Component\Form\Form_Builder_Interface;
/**
 * A builder for creating {@link FormFlow} instances.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @implements \IteratorAggregate<string, FormBuilderInterface>
 */
class Form_Flow_Builder extends Form_Builder implements Form_Flow_Builder_Interface
{
    /**
     * @var array<string, StepFlowBuilderConfigInterface>
     */
    private array $steps = [];
    private array $initial_options = [];
    private Data_Storage_Interface $data_storage;
    private Step_Accessor_Interface $step_accessor;
    public function create_step(string $name, string $type = Form_Type::class, array $options = []): Step_Flow_Builder_Config_Interface
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }
        return new Step_Flow_Builder($name, $type, $options);
    }
    public function add_step(Step_Flow_Builder_Config_Interface|string $name, string $type = Form_Type::class, array $options = [], ?callable $skip = null, int $priority = 0): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }
        if ($name instanceof Step_Flow_Builder_Config_Interface) {
            $this->steps[$name->get_name()] = $name;
            return $this;
        }
        $this->steps[$name] = $this->create_step($name, $type, $options)->set_skip($skip ? $skip(...) : null)->set_priority($priority);
        return $this;
    }
    public function remove_step(string $name): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }
        unset($this->steps[$name]);
        return $this;
    }
    public function has_step(string $name): bool
    {
        return isset($this->steps[$name]);
    }
    public function get_step(string $name): Step_Flow_Builder_Config_Interface
    {
        return $this->steps[$name] ?? throw new InvalidArgumentException(\sprintf('Step "%s" does not exist.', $name));
    }
    public function get_steps(): array
    {
        return $this->steps;
    }
    public function set_initial_options(array $options): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }
        $this->initial_options = $options;
        return $this;
    }
    public function get_initial_step(): string
    {
        $default_step = (string) key($this->steps);
        if (!isset($this->initial_options['data'])) {
            return $default_step;
        }
        return (string) $this->step_accessor->get_step($this->initial_options['data'], $default_step);
    }
    public function get_initial_options(): array
    {
        return $this->initial_options;
    }
    public function set_data_storage(Data_Storage_Interface $data_storage): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }
        $this->data_storage = $data_storage;
        // make sure the current data is available immediately
        $this->set_data($data_storage->load($this->get_data()));
        return $this;
    }
    public function get_data_storage(): Data_Storage_Interface
    {
        return $this->data_storage;
    }
    public function set_step_accessor(Step_Accessor_Interface $step_accessor): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }
        $this->step_accessor = $step_accessor;
        return $this;
    }
    public function get_step_accessor(): Step_Accessor_Interface
    {
        return $this->step_accessor;
    }
    public function is_auto_reset(): bool
    {
        return $this->get_option('auto_reset');
    }
    public function get_form_config(): Form_Flow_Config_Interface
    {
        /** @var self $config */
        $config = parent::get_form_config();
        foreach ($config->steps as $name => $step) {
            $config->steps[$name] = $step->get_step_config();
        }
        return $config;
    }
    public function get_form(): Form_Flow_Interface
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormFlowBuilder methods cannot be accessed anymore once the builder is turned into a FormFlowConfigInterface instance.');
        }
        $flow = $this->create_form_flow();
        foreach ($this->all() as $child) {
            if ($child instanceof Form_Flow_Builder_Interface) {
                throw new LogicException('Nested form flows is not currently supported.');
            }
            // Automatic initialization is only supported on root forms
            $flow->add($child->set_auto_initialize(false)->get_form());
        }
        if ($this->get_auto_initialize()) {
            // Automatically initialize the form if it is configured so
            $flow->initialize();
        }
        return $flow;
    }
    private function create_form_flow(): Form_Flow_Interface
    {
        if (!$this->steps) {
            throw new InvalidArgumentException('Steps not configured.');
        }
        uasort($this->steps, static fn(Step_Flow_Builder_Config_Interface $a, Step_Flow_Builder_Config_Interface $b): int => $b->get_priority() <=> $a->get_priority());
        $current_step = $this->resolve_current_step();
        if (!isset($this->steps[$current_step])) {
            throw new InvalidArgumentException(\sprintf('Step form "%s" is not defined.', $current_step));
        }
        $step = $this->steps[$current_step];
        $this->add($step->get_name(), $step->get_type(), $step->get_options());
        $cursor = new Form_Flow_Cursor(array_keys($this->steps), $current_step);
        $this->prune_action_buttons($this, $cursor);
        return new Form_Flow($this->get_form_config(), $cursor);
    }
    private function resolve_current_step(): string
    {
        $data = $this->get_data();
        if (!$current_step = $this->get_step_accessor()->get_step($data)) {
            $current_step = key($this->steps);
            $this->get_step_accessor()->set_step($data, $current_step);
            $this->set_data($data);
        }
        return $current_step;
    }
    private function prune_action_buttons(Form_Builder_Interface $builder, Form_Flow_Cursor $cursor): void
    {
        foreach ($builder->all() as $child) {
            if ($child->count() > 0) {
                $this->prune_action_buttons($child, $cursor);
                continue;
            }
            if (!$child instanceof Button_Flow_Builder) {
                continue;
            }
            if (!\is_callable($include = $child->get_option('include_if'))) {
                continue;
            }
            if (!$include($cursor)) {
                $builder->remove($child->get_name());
            }
        }
    }
}