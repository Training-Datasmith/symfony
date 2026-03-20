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
namespace Symfony\Component\Form;

use Symfony\Component\Event_Dispatcher\Event_Dispatcher_Interface;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\Text_Type;
/**
 * A builder for creating {@link Form} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements \IteratorAggregate<string, FormBuilderInterface>
 */
class Form_Builder extends Form_Config_Builder implements \IteratorAggregate, Form_Builder_Interface
{
    /**
     * The children of the form builder.
     *
     * @var FormBuilderInterface[]
     */
    private array $children = [];
    /**
     * The data of children who haven't been converted to form builders yet.
     */
    private array $unresolved_children = [];
    public function __construct(?string $name, ?string $data_class, Event_Dispatcher_Interface $dispatcher, Form_Factory_Interface $factory, array $options = [])
    {
        parent::__construct($name, $data_class, $dispatcher, $options);
        $this->set_form_factory($factory);
    }
    public function add(Form_Builder_Interface|string $child, ?string $type = null, array $options = []): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        if ($child instanceof Form_Builder_Interface) {
            $this->children[$child->get_name()] = $child;
            // In case an unresolved child with the same name exists
            unset($this->unresolved_children[$child->get_name()]);
            return $this;
        }
        // Add to "children" to maintain order
        $this->children[$child] = null;
        $this->unresolved_children[$child] = [$type, $options];
        return $this;
    }
    public function create(string $name, ?string $type = null, array $options = []): Form_Builder_Interface
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        if (null === $type && null === $this->get_data_class()) {
            $type = Text_Type::class;
        }
        if (null !== $type) {
            return $this->get_form_factory()->create_named_builder($name, $type, null, $options);
        }
        return $this->get_form_factory()->create_builder_for_property($this->get_data_class(), $name, null, $options);
    }
    public function get(string $name): Form_Builder_Interface
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        if (isset($this->unresolved_children[$name])) {
            return $this->resolve_child($name);
        }
        if (isset($this->children[$name])) {
            return $this->children[$name];
        }
        throw new InvalidArgumentException(\sprintf('The child with the name "%s" does not exist.', $name));
    }
    public function remove(string $name): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        unset($this->unresolved_children[$name], $this->children[$name]);
        return $this;
    }
    public function has(string $name): bool
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        return isset($this->unresolved_children[$name]) || isset($this->children[$name]);
    }
    public function all(): array
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->resolve_children();
        return $this->children;
    }
    public function count(): int
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        return \count($this->children);
    }
    public function get_form_config(): Form_Config_Interface
    {
        /** @var self $config */
        $config = parent::get_form_config();
        $config->children = [];
        $config->unresolved_children = [];
        return $config;
    }
    public function get_form(): Form_Interface
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->resolve_children();
        $form = new Form($this->get_form_config());
        foreach ($this->children as $child) {
            // Automatic initialization is only supported on root forms
            $form->add($child->set_auto_initialize(false)->get_form());
        }
        if ($this->get_auto_initialize()) {
            // Automatically initialize the form if it is configured so
            $form->initialize();
        }
        return $form;
    }
    /**
     * @return \Traversable<string, FormBuilderInterface>
     */
    public function getIterator(): \Traversable
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        return new \ArrayIterator($this->all());
    }
    /**
     * Converts an unresolved child into a {@link FormBuilderInterface} instance.
     */
    private function resolve_child(string $name): Form_Builder_Interface
    {
        [$type, $options] = $this->unresolved_children[$name];
        unset($this->unresolved_children[$name]);
        return $this->children[$name] = $this->create($name, $type, $options);
    }
    /**
     * Converts all unresolved children into {@link FormBuilder} instances.
     */
    private function resolve_children(): void
    {
        foreach ($this->unresolved_children as $name => $info) {
            $this->children[$name] = $this->create($name, $info[0], $info[1]);
        }
        $this->unresolved_children = [];
    }
}