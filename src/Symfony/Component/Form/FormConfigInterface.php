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
use Symfony\Component\Property_Access\Property_Path_Interface;
/**
 * The configuration of a {@link Form} object.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Form_Config_Interface
{
    /**
     * Returns the event dispatcher used to dispatch form events.
     */
    public function get_event_dispatcher(): Event_Dispatcher_Interface;
    /**
     * Returns the name of the form used as HTTP parameter.
     */
    public function get_name(): string;
    /**
     * Returns the property path that the form should be mapped to.
     */
    public function get_property_path(): ?Property_Path_Interface;
    /**
     * Returns whether the form should be mapped to an element of its
     * parent's data.
     */
    public function get_mapped(): bool;
    /**
     * Returns whether the form's data should be modified by reference.
     */
    public function get_by_reference(): bool;
    /**
     * Returns whether the form should read and write the data of its parent.
     */
    public function get_inherit_data(): bool;
    /**
     * Returns whether the form is compound.
     *
     * This property is independent of whether the form actually has
     * children. A form can be compound and have no children at all, like
     * for example an empty collection form.
     * The contrary is not possible, a form which is not compound
     * cannot have any children.
     */
    public function get_compound(): bool;
    /**
     * Returns the resolved form type used to construct the form.
     */
    public function get_type(): Resolved_Form_Type_Interface;
    /**
     * Returns the view transformers of the form.
     *
     * @return DataTransformerInterface[]
     */
    public function get_view_transformers(): array;
    /**
     * Returns the model transformers of the form.
     *
     * @return DataTransformerInterface[]
     */
    public function get_model_transformers(): array;
    /**
     * Returns the data mapper of the compound form or null for a simple form.
     */
    public function get_data_mapper(): ?Data_Mapper_Interface;
    /**
     * Returns whether the form is required.
     */
    public function get_required(): bool;
    /**
     * Returns whether the form is disabled.
     */
    public function get_disabled(): bool;
    /**
     * Returns whether errors attached to the form will bubble to its parent.
     */
    public function get_error_bubbling(): bool;
    /**
     * Used when the view data is empty on submission.
     *
     * When the form is compound it will also be used to map the
     * children data.
     *
     * The empty data must match the view format as it will passed to the first view transformer's
     * "reverseTransform" method.
     */
    public function get_empty_data(): mixed;
    /**
     * Returns additional attributes of the form.
     */
    public function get_attributes(): array;
    /**
     * Returns whether the attribute with the given name exists.
     */
    public function has_attribute(string $name): bool;
    /**
     * Returns the value of the given attribute.
     */
    public function get_attribute(string $name, mixed $default = null): mixed;
    /**
     * Returns the initial data of the form.
     */
    public function get_data(): mixed;
    /**
     * Returns the class of the view data or null if the data is scalar or an array.
     */
    public function get_data_class(): ?string;
    /**
     * Returns whether the form's data is locked.
     *
     * A form with locked data is restricted to the data passed in
     * this configuration. The data can only be modified then by
     * submitting the form.
     */
    public function get_data_locked(): bool;
    /**
     * Returns the form factory used for creating new forms.
     */
    public function get_form_factory(): Form_Factory_Interface;
    /**
     * Returns the target URL of the form.
     */
    public function get_action(): string;
    /**
     * Returns the HTTP method used by the form.
     */
    public function get_method(): string;
    /**
     * Returns the request handler used by the form.
     */
    public function get_request_handler(): Request_Handler_Interface;
    /**
     * Returns whether the form should be initialized upon creation.
     */
    public function get_auto_initialize(): bool;
    /**
     * Returns all options passed during the construction of the form.
     *
     * @return array<string, mixed> The passed options
     */
    public function get_options(): array;
    /**
     * Returns whether a specific option exists.
     */
    public function has_option(string $name): bool;
    /**
     * Returns the value of a specific option.
     */
    public function get_option(string $name, mixed $default = null): mixed;
    /**
     * Returns a callable that takes the model data as argument and that returns if it is empty or not.
     */
    public function get_is_empty_callback(): ?callable;
}