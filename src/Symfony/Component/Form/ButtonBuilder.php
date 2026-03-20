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

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Property_Access\Property_Path_Interface;
/**
 * A builder for {@link Button} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements \IteratorAggregate<string, FormBuilderInterface>
 */
class Button_Builder implements \IteratorAggregate, Form_Builder_Interface
{
    protected bool $locked = false;
    private bool $disabled = false;
    private Resolved_Form_Type_Interface $type;
    private string $name;
    private array $attributes = [];
    /**
     * @throws InvalidArgumentException if the name is empty
     */
    public function __construct(?string $name, private array $options = [])
    {
        if ('' === $name || null === $name) {
            throw new InvalidArgumentException('Buttons cannot have empty names.');
        }
        $this->name = $name;
        Form_Config_Builder::validate_name($name);
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function add(string|Form_Builder_Interface $child, ?string $type = null, array $options = []): never
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function create(string $name, ?string $type = null, array $options = []): never
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function get(string $name): never
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function remove(string $name): never
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }
    /**
     * Unsupported method.
     */
    public function has(string $name): bool
    {
        return false;
    }
    /**
     * Returns the children.
     */
    public function all(): array
    {
        return [];
    }
    /**
     * Creates the button.
     */
    public function get_form(): Button
    {
        return new Button($this->get_form_config());
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function add_event_listener(string $event_name, callable $listener, int $priority = 0): never
    {
        throw new BadMethodCallException('Buttons do not support event listeners.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function add_event_subscriber(Event_Subscriber_Interface $subscriber): never
    {
        throw new BadMethodCallException('Buttons do not support event subscribers.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function add_view_transformer(Data_Transformer_Interface $view_transformer, bool $force_prepend = false): never
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function reset_view_transformers(): never
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function add_model_transformer(Data_Transformer_Interface $model_transformer, bool $force_append = false): never
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function reset_model_transformers(): never
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }
    /**
     * @return $this
     */
    public function set_attribute(string $name, mixed $value): static
    {
        $this->attributes[$name] = $value;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_attributes(array $attributes): static
    {
        $this->attributes = $attributes;
        return $this;
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_data_mapper(?Data_Mapper_Interface $data_mapper): never
    {
        throw new BadMethodCallException('Buttons do not support data mappers.');
    }
    /**
     * Set whether the button is disabled.
     *
     * @return $this
     */
    public function set_disabled(bool $disabled): static
    {
        $this->disabled = $disabled;
        return $this;
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_empty_data(mixed $empty_data): never
    {
        throw new BadMethodCallException('Buttons do not support empty data.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_error_bubbling(bool $error_bubbling): never
    {
        throw new BadMethodCallException('Buttons do not support error bubbling.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_required(bool $required): never
    {
        throw new BadMethodCallException('Buttons cannot be required.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_property_path(string|Property_Path_Interface|null $property_path): never
    {
        throw new BadMethodCallException('Buttons do not support property paths.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_mapped(bool $mapped): never
    {
        throw new BadMethodCallException('Buttons do not support data mapping.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_by_reference(bool $by_reference): never
    {
        throw new BadMethodCallException('Buttons do not support data mapping.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_compound(bool $compound): never
    {
        throw new BadMethodCallException('Buttons cannot be compound.');
    }
    /**
     * Sets the type of the button.
     *
     * @return $this
     */
    public function set_type(Resolved_Form_Type_Interface $type): static
    {
        $this->type = $type;
        return $this;
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_data(mixed $data): never
    {
        throw new BadMethodCallException('Buttons do not support data.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_data_locked(bool $locked): never
    {
        throw new BadMethodCallException('Buttons do not support data locking.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_form_factory(Form_Factory_Interface $form_factory): never
    {
        throw new BadMethodCallException('Buttons do not support form factories.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_action(string $action): never
    {
        throw new BadMethodCallException('Buttons do not support actions.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_method(string $method): never
    {
        throw new BadMethodCallException('Buttons do not support methods.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_request_handler(Request_Handler_Interface $request_handler): never
    {
        throw new BadMethodCallException('Buttons do not support request handlers.');
    }
    /**
     * Unsupported method.
     *
     * @return $this
     *
     * @throws BadMethodCallException
     */
    public function set_auto_initialize(bool $initialize): static
    {
        if (true === $initialize) {
            throw new BadMethodCallException('Buttons do not support automatic initialization.');
        }
        return $this;
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_inherit_data(bool $inherit_data): never
    {
        throw new BadMethodCallException('Buttons do not support data inheritance.');
    }
    /**
     * Builds and returns the button configuration.
     */
    public function get_form_config(): Form_Config_Interface
    {
        // This method should be idempotent, so clone the builder
        $config = clone $this;
        $config->locked = true;
        return $config;
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function set_is_empty_callback(?callable $is_empty_callback): never
    {
        throw new BadMethodCallException('Buttons do not support "is empty" callback.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function get_event_dispatcher(): never
    {
        throw new BadMethodCallException('Buttons do not support event dispatching.');
    }
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * Unsupported method.
     */
    public function get_property_path(): ?Property_Path_Interface
    {
        return null;
    }
    /**
     * Unsupported method.
     */
    public function get_mapped(): bool
    {
        return false;
    }
    /**
     * Unsupported method.
     */
    public function get_by_reference(): bool
    {
        return false;
    }
    /**
     * Unsupported method.
     */
    public function get_compound(): bool
    {
        return false;
    }
    /**
     * Returns the form type used to construct the button.
     */
    public function get_type(): Resolved_Form_Type_Interface
    {
        return $this->type;
    }
    /**
     * Unsupported method.
     */
    public function get_view_transformers(): array
    {
        return [];
    }
    /**
     * Unsupported method.
     */
    public function get_model_transformers(): array
    {
        return [];
    }
    /**
     * Unsupported method.
     */
    public function get_data_mapper(): ?Data_Mapper_Interface
    {
        return null;
    }
    /**
     * Unsupported method.
     */
    public function get_required(): bool
    {
        return false;
    }
    /**
     * Returns whether the button is disabled.
     */
    public function get_disabled(): bool
    {
        return $this->disabled;
    }
    /**
     * Unsupported method.
     */
    public function get_error_bubbling(): bool
    {
        return false;
    }
    /**
     * Unsupported method.
     */
    public function get_empty_data(): mixed
    {
        return null;
    }
    /**
     * Returns additional attributes of the button.
     */
    public function get_attributes(): array
    {
        return $this->attributes;
    }
    /**
     * Returns whether the attribute with the given name exists.
     */
    public function has_attribute(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }
    /**
     * Returns the value of the given attribute.
     */
    public function get_attribute(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }
    /**
     * Unsupported method.
     */
    public function get_data(): mixed
    {
        return null;
    }
    /**
     * Unsupported method.
     */
    public function get_data_class(): ?string
    {
        return null;
    }
    /**
     * Unsupported method.
     */
    public function get_data_locked(): bool
    {
        return false;
    }
    /**
     * Unsupported method.
     */
    public function get_form_factory(): never
    {
        throw new BadMethodCallException('Buttons do not support adding children.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function get_action(): never
    {
        throw new BadMethodCallException('Buttons do not support actions.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function get_method(): never
    {
        throw new BadMethodCallException('Buttons do not support methods.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function get_request_handler(): never
    {
        throw new BadMethodCallException('Buttons do not support request handlers.');
    }
    /**
     * Unsupported method.
     */
    public function get_auto_initialize(): bool
    {
        return false;
    }
    /**
     * Unsupported method.
     */
    public function get_inherit_data(): bool
    {
        return false;
    }
    /**
     * Returns all options passed during the construction of the button.
     */
    public function get_options(): array
    {
        return $this->options;
    }
    /**
     * Returns whether a specific option exists.
     */
    public function has_option(string $name): bool
    {
        return \array_key_exists($name, $this->options);
    }
    /**
     * Returns the value of a specific option.
     */
    public function get_option(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->options) ? $this->options[$name] : $default;
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function get_is_empty_callback(): never
    {
        throw new BadMethodCallException('Buttons do not support "is empty" callback.');
    }
    /**
     * Unsupported method.
     */
    public function count(): int
    {
        return 0;
    }
    /**
     * Unsupported method.
     */
    public function getIterator(): \Empty_Iterator
    {
        return new \Empty_Iterator();
    }
}