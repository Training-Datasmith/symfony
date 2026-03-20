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
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Event_Dispatcher\Immutable_Event_Dispatcher;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Property_Access\Property_Path;
use Symfony\Component\Property_Access\Property_Path_Interface;
/**
 * A basic form configuration.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form_Config_Builder implements Form_Config_Builder_Interface
{
    protected bool $locked = false;
    /**
     * Caches a globally unique {@link NativeRequestHandler} instance.
     */
    private static Native_Request_Handler $native_request_handler;
    private string $name;
    private ?Property_Path_Interface $property_path = null;
    private bool $mapped = true;
    private bool $by_reference = true;
    private bool $inherit_data = false;
    private bool $compound = false;
    private Resolved_Form_Type_Interface $type;
    private array $view_transformers = [];
    private array $model_transformers = [];
    private ?Data_Mapper_Interface $data_mapper = null;
    private bool $required = true;
    private bool $disabled = false;
    private bool $error_bubbling = false;
    private mixed $empty_data = null;
    private array $attributes = [];
    private mixed $data = null;
    private ?string $data_class;
    private bool $data_locked = false;
    private Form_Factory_Interface $form_factory;
    private string $action = '';
    private string $method = 'POST';
    private Request_Handler_Interface $request_handler;
    private bool $auto_initialize = false;
    private ?\Closure $is_empty_callback = null;
    /**
     * Creates an empty form configuration.
     *
     * @param string|null $name      The form name
     * @param string|null $dataClass The class of the form's data
     *
     * @throws InvalidArgumentException if the data class is not a valid class or if
     *                                  the name contains invalid characters
     */
    public function __construct(?string $name, ?string $data_class, private Event_Dispatcher_Interface $dispatcher, private array $options = [])
    {
        self::validate_name($name);
        if (null !== $data_class && !class_exists($data_class) && !interface_exists($data_class, false)) {
            throw new InvalidArgumentException(\sprintf('Class "%s" not found. Is the "data_class" form option set correctly?', $data_class));
        }
        $this->name = (string) $name;
        $this->data_class = $data_class;
    }
    public function add_event_listener(string $event_name, callable $listener, int $priority = 0): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->dispatcher->add_listener($event_name, $listener, $priority);
        return $this;
    }
    public function add_event_subscriber(Event_Subscriber_Interface $subscriber): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->dispatcher->add_subscriber($subscriber);
        return $this;
    }
    public function add_view_transformer(Data_Transformer_Interface $view_transformer, bool $force_prepend = false): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        if ($force_prepend) {
            array_unshift($this->view_transformers, $view_transformer);
        } else {
            $this->view_transformers[] = $view_transformer;
        }
        return $this;
    }
    public function reset_view_transformers(): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->view_transformers = [];
        return $this;
    }
    public function add_model_transformer(Data_Transformer_Interface $model_transformer, bool $force_append = false): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        if ($force_append) {
            $this->model_transformers[] = $model_transformer;
        } else {
            array_unshift($this->model_transformers, $model_transformer);
        }
        return $this;
    }
    public function reset_model_transformers(): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->model_transformers = [];
        return $this;
    }
    public function get_event_dispatcher(): Event_Dispatcher_Interface
    {
        if ($this->locked && !$this->dispatcher instanceof Immutable_Event_Dispatcher) {
            $this->dispatcher = new Immutable_Event_Dispatcher($this->dispatcher);
        }
        return $this->dispatcher;
    }
    public function get_name(): string
    {
        return $this->name;
    }
    public function get_property_path(): ?Property_Path_Interface
    {
        return $this->property_path;
    }
    public function get_mapped(): bool
    {
        return $this->mapped;
    }
    public function get_by_reference(): bool
    {
        return $this->by_reference;
    }
    public function get_inherit_data(): bool
    {
        return $this->inherit_data;
    }
    public function get_compound(): bool
    {
        return $this->compound;
    }
    public function get_type(): Resolved_Form_Type_Interface
    {
        return $this->type;
    }
    public function get_view_transformers(): array
    {
        return $this->view_transformers;
    }
    public function get_model_transformers(): array
    {
        return $this->model_transformers;
    }
    public function get_data_mapper(): ?Data_Mapper_Interface
    {
        return $this->data_mapper;
    }
    public function get_required(): bool
    {
        return $this->required;
    }
    public function get_disabled(): bool
    {
        return $this->disabled;
    }
    public function get_error_bubbling(): bool
    {
        return $this->error_bubbling;
    }
    public function get_empty_data(): mixed
    {
        return $this->empty_data;
    }
    public function get_attributes(): array
    {
        return $this->attributes;
    }
    public function has_attribute(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }
    public function get_attribute(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }
    public function get_data(): mixed
    {
        return $this->data;
    }
    public function get_data_class(): ?string
    {
        return $this->data_class;
    }
    public function get_data_locked(): bool
    {
        return $this->data_locked;
    }
    public function get_form_factory(): Form_Factory_Interface
    {
        if (!isset($this->form_factory)) {
            throw new BadMethodCallException('The form factory must be set before retrieving it.');
        }
        return $this->form_factory;
    }
    public function get_action(): string
    {
        return $this->action;
    }
    public function get_method(): string
    {
        return $this->method;
    }
    public function get_request_handler(): Request_Handler_Interface
    {
        return $this->request_handler ??= self::$native_request_handler ??= new Native_Request_Handler();
    }
    public function get_auto_initialize(): bool
    {
        return $this->auto_initialize;
    }
    public function get_options(): array
    {
        return $this->options;
    }
    public function has_option(string $name): bool
    {
        return \array_key_exists($name, $this->options);
    }
    public function get_option(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->options) ? $this->options[$name] : $default;
    }
    public function get_is_empty_callback(): ?callable
    {
        return $this->is_empty_callback;
    }
    /**
     * @return $this
     */
    public function set_attribute(string $name, mixed $value): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->attributes[$name] = $value;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_attributes(array $attributes): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->attributes = $attributes;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_data_mapper(?Data_Mapper_Interface $data_mapper): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->data_mapper = $data_mapper;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_disabled(bool $disabled): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->disabled = $disabled;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_empty_data(mixed $empty_data): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->empty_data = $empty_data;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_error_bubbling(bool $error_bubbling): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->error_bubbling = $error_bubbling;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_required(bool $required): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->required = $required;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_property_path(string|Property_Path_Interface|null $property_path): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        if (null !== $property_path && !$property_path instanceof Property_Path_Interface) {
            $property_path = new Property_Path($property_path);
        }
        $this->property_path = $property_path;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_mapped(bool $mapped): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->mapped = $mapped;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_by_reference(bool $by_reference): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->by_reference = $by_reference;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_inherit_data(bool $inherit_data): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->inherit_data = $inherit_data;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_compound(bool $compound): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->compound = $compound;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_type(Resolved_Form_Type_Interface $type): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->type = $type;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_data(mixed $data): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->data = $data;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_data_locked(bool $locked): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->data_locked = $locked;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_form_factory(Form_Factory_Interface $form_factory): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->form_factory = $form_factory;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_action(string $action): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('The config builder cannot be modified anymore.');
        }
        $this->action = $action;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_method(string $method): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('The config builder cannot be modified anymore.');
        }
        $this->method = strtoupper($method);
        return $this;
    }
    /**
     * @return $this
     */
    public function set_request_handler(Request_Handler_Interface $request_handler): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('The config builder cannot be modified anymore.');
        }
        $this->request_handler = $request_handler;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_auto_initialize(bool $initialize): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        $this->auto_initialize = $initialize;
        return $this;
    }
    public function get_form_config(): Form_Config_Interface
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }
        // This method should be idempotent, so clone the builder
        $config = clone $this;
        $config->locked = true;
        return $config;
    }
    /**
     * @return $this
     */
    public function set_is_empty_callback(?callable $is_empty_callback): static
    {
        $this->is_empty_callback = null === $is_empty_callback ? null : $is_empty_callback(...);
        return $this;
    }
    /**
     * Validates whether the given variable is a valid form name.
     *
     * @throws InvalidArgumentException if the name contains invalid characters
     *
     * @internal
     */
    final public static function validate_name(?string $name): void
    {
        if (!self::is_valid_name($name)) {
            throw new InvalidArgumentException(\sprintf('The name "%s" contains illegal characters. Names should start with a letter, digit or underscore and only contain letters, digits, numbers, underscores ("_"), hyphens ("-") and colons (":").', $name));
        }
    }
    /**
     * Returns whether the given variable contains a valid form name.
     *
     * A name is accepted if it
     *
     *   * is empty
     *   * starts with a letter, digit or underscore
     *   * contains only letters, digits, numbers, underscores ("_"),
     *     hyphens ("-") and colons (":")
     */
    final public static function is_valid_name(?string $name): bool
    {
        return '' === $name || null === $name || preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_\-:]*$/D', $name);
    }
}