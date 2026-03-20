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
use Symfony\Component\Property_Access\Property_Path_Interface;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Form_Config_Builder_Interface extends Form_Config_Interface
{
    /**
     * Adds an event listener to an event on this form.
     *
     * @param int $priority The priority of the listener. Listeners
     *                      with a higher priority are called before
     *                      listeners with a lower priority.
     *
     * @return $this
     */
    public function add_event_listener(string $event_name, callable $listener, int $priority = 0): static;
    /**
     * Adds an event subscriber for events on this form.
     *
     * @return $this
     */
    public function add_event_subscriber(Event_Subscriber_Interface $subscriber): static;
    /**
     * Appends / prepends a transformer to the view transformer chain.
     *
     * The transform method of the transformer is used to convert data from the
     * normalized to the view format.
     * The reverseTransform method of the transformer is used to convert from the
     * view to the normalized format.
     *
     * @param bool $forcePrepend If set to true, prepend instead of appending
     *
     * @return $this
     */
    public function add_view_transformer(Data_Transformer_Interface $view_transformer, bool $force_prepend = false): static;
    /**
     * Clears the view transformers.
     *
     * @return $this
     */
    public function reset_view_transformers(): static;
    /**
     * Prepends / appends a transformer to the normalization transformer chain.
     *
     * The transform method of the transformer is used to convert data from the
     * model to the normalized format.
     * The reverseTransform method of the transformer is used to convert from the
     * normalized to the model format.
     *
     * @param bool $forceAppend If set to true, append instead of prepending
     *
     * @return $this
     */
    public function add_model_transformer(Data_Transformer_Interface $model_transformer, bool $force_append = false): static;
    /**
     * Clears the normalization transformers.
     *
     * @return $this
     */
    public function reset_model_transformers(): static;
    /**
     * Sets the value for an attribute.
     *
     * @param mixed $value The value of the attribute
     *
     * @return $this
     */
    public function set_attribute(string $name, mixed $value): static;
    /**
     * Sets the attributes.
     *
     * @return $this
     */
    public function set_attributes(array $attributes): static;
    /**
     * Sets the data mapper used by the form.
     *
     * @return $this
     */
    public function set_data_mapper(?Data_Mapper_Interface $data_mapper): static;
    /**
     * Sets whether the form is disabled.
     *
     * @return $this
     */
    public function set_disabled(bool $disabled): static;
    /**
     * Sets the data used for the client data when no value is submitted.
     *
     * @param mixed $emptyData The empty data
     *
     * @return $this
     */
    public function set_empty_data(mixed $empty_data): static;
    /**
     * Sets whether errors bubble up to the parent.
     *
     * @return $this
     */
    public function set_error_bubbling(bool $error_bubbling): static;
    /**
     * Sets whether this field is required to be filled out when submitted.
     *
     * @return $this
     */
    public function set_required(bool $required): static;
    /**
     * Sets the property path that the form should be mapped to.
     *
     * @param string|PropertyPathInterface|null $propertyPath The property path or null if the path should be set
     *                                                        automatically based on the form's name
     *
     * @return $this
     */
    public function set_property_path(string|Property_Path_Interface|null $property_path): static;
    /**
     * Sets whether the form should be mapped to an element of its
     * parent's data.
     *
     * @return $this
     */
    public function set_mapped(bool $mapped): static;
    /**
     * Sets whether the form's data should be modified by reference.
     *
     * @return $this
     */
    public function set_by_reference(bool $by_reference): static;
    /**
     * Sets whether the form should read and write the data of its parent.
     *
     * @return $this
     */
    public function set_inherit_data(bool $inherit_data): static;
    /**
     * Sets whether the form should be compound.
     *
     * @return $this
     *
     * @see FormConfigInterface::getCompound()
     */
    public function set_compound(bool $compound): static;
    /**
     * Sets the resolved type.
     *
     * @return $this
     */
    public function set_type(Resolved_Form_Type_Interface $type): static;
    /**
     * Sets the initial data of the form.
     *
     * @param mixed $data The data of the form in model format
     *
     * @return $this
     */
    public function set_data(mixed $data): static;
    /**
     * Locks the form's data to the data passed in the configuration.
     *
     * A form with locked data is restricted to the data passed in
     * this configuration. The data can only be modified then by
     * submitting the form or using PRE_SET_DATA event.
     *
     * It means data passed to a factory method or mapped from the
     * parent will be ignored.
     *
     * @return $this
     */
    public function set_data_locked(bool $locked): static;
    /**
     * Sets the form factory used for creating new forms.
     *
     * @return $this
     */
    public function set_form_factory(Form_Factory_Interface $form_factory): static;
    /**
     * Sets the target URL of the form.
     *
     * @return $this
     */
    public function set_action(string $action): static;
    /**
     * Sets the HTTP method used by the form.
     *
     * @return $this
     */
    public function set_method(string $method): static;
    /**
     * Sets the request handler used by the form.
     *
     * @return $this
     */
    public function set_request_handler(Request_Handler_Interface $request_handler): static;
    /**
     * Sets whether the form should be initialized automatically.
     *
     * Should be set to true only for root forms.
     *
     * @param bool $initialize True to initialize the form automatically,
     *                         false to suppress automatic initialization.
     *                         In the second case, you need to call
     *                         {@link FormInterface::initialize()} manually.
     *
     * @return $this
     */
    public function set_auto_initialize(bool $initialize): static;
    /**
     * Builds and returns the form configuration.
     */
    public function get_form_config(): Form_Config_Interface;
    /**
     * Sets the callback that will be called to determine if the model
     * data of the form is empty or not.
     *
     * @return $this
     */
    public function set_is_empty_callback(?callable $is_empty_callback): static;
}