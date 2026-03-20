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

use Symfony\Component\Property_Access\Property_Path_Interface;
/**
 * A form group bundling multiple forms in a hierarchical structure.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @extends \ArrayAccess<string, FormInterface>
 * @extends \Traversable<string, FormInterface>
 */
interface Form_Interface extends \ArrayAccess, \Traversable, \Countable
{
    /**
     * Sets the parent form.
     *
     * @param FormInterface|null $parent The parent form or null if it's the root
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the form has already been submitted
     * @throws Exception\LogicException            when trying to set a parent for a form with
     *                                             an empty name
     */
    public function set_parent(?self $parent): static;
    /**
     * Returns the parent form.
     */
    public function get_parent(): ?self;
    /**
     * Adds or replaces a child to the form.
     *
     * @param FormInterface|string $child   The FormInterface instance or the name of the child
     * @param string|null          $type    The child's type, if a name was passed
     * @param array                $options The child's options, if a name was passed
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the form has already been submitted
     * @throws Exception\LogicException            when trying to add a child to a non-compound form
     * @throws Exception\UnexpectedTypeException   if $child or $type has an unexpected type
     */
    public function add(self|string $child, ?string $type = null, array $options = []): static;
    /**
     * Returns the child with the given name.
     *
     * @throws Exception\OutOfBoundsException if the named child does not exist
     */
    public function get(string $name): self;
    /**
     * Returns whether a child with the given name exists.
     */
    public function has(string $name): bool;
    /**
     * Removes a child from the form.
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the form has already been submitted
     */
    public function remove(string $name): static;
    /**
     * Returns all children in this group.
     *
     * @return self[]
     */
    public function all(): array;
    /**
     * Returns the errors of this form.
     *
     * @param bool $deep    Whether to include errors of child forms as well
     * @param bool $flatten Whether to flatten the list of errors in case
     *                      $deep is set to true
     */
    public function get_errors(bool $deep = false, bool $flatten = true): Form_Error_Iterator;
    /**
     * Updates the form with default model data.
     *
     * @param mixed $modelData The data formatted as expected for the underlying object
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException     If the form has already been submitted
     * @throws Exception\LogicException                if the view data does not match the expected type
     *                                                 according to {@link FormConfigInterface::getDataClass}
     * @throws Exception\RuntimeException              If listeners try to call setData in a cycle or if
     *                                                 the form inherits data from its parent
     * @throws Exception\TransformationFailedException if the synchronization failed
     */
    public function set_data(mixed $model_data): static;
    /**
     * Returns the model data in the format needed for the underlying object.
     *
     * @return mixed When the field is not submitted, the default data is returned.
     *               When the field is submitted, the default data has been bound
     *               to the submitted view data.
     *
     * @throws Exception\RuntimeException If the form inherits data but has no parent
     */
    public function get_data(): mixed;
    /**
     * Returns the normalized data of the field, used as internal bridge
     * between model data and view data.
     *
     * @return mixed When the field is not submitted, the default data is returned.
     *               When the field is submitted, the normalized submitted data
     *               is returned if the field is synchronized with the view data,
     *               null otherwise.
     *
     * @throws Exception\RuntimeException If the form inherits data but has no parent
     */
    public function get_norm_data(): mixed;
    /**
     * Returns the view data of the field.
     *
     * It may be defined by {@link FormConfigInterface::getDataClass}.
     *
     * There are two cases:
     *
     * - When the form is compound the view data is mapped to the children.
     *   Each child will use its mapped data as model data.
     *   It can be an array, an object or null.
     *
     * - When the form is simple its view data is used to be bound
     *   to the submitted data.
     *   It can be a string or an array.
     *
     * In both cases the view data is the actual altered data on submission.
     *
     * @throws Exception\RuntimeException If the form inherits data but has no parent
     */
    public function get_view_data(): mixed;
    /**
     * Returns the extra submitted data.
     *
     * @return array The submitted data which do not belong to a child
     */
    public function get_extra_data(): array;
    /**
     * Returns the form's configuration.
     */
    public function get_config(): Form_Config_Interface;
    /**
     * Returns whether the form is submitted.
     */
    public function is_submitted(): bool;
    /**
     * Returns the name by which the form is identified in forms.
     *
     * Only root forms are allowed to have an empty name.
     */
    public function get_name(): string;
    /**
     * Returns the property path that the form is mapped to.
     */
    public function get_property_path(): ?Property_Path_Interface;
    /**
     * Adds an error to this form.
     *
     * @return $this
     */
    public function add_error(Form_Error $error): static;
    /**
     * Returns whether the form and all children are valid.
     *
     * @throws Exception\LogicException if the form is not submitted
     */
    public function is_valid(): bool;
    /**
     * Returns whether the form is required to be filled out.
     *
     * If the form has a parent and the parent is not required, this method
     * will always return false. Otherwise the value set with setRequired()
     * is returned.
     */
    public function is_required(): bool;
    /**
     * Returns whether this form is disabled.
     *
     * The content of a disabled form is displayed, but not allowed to be
     * modified. The validation of modified disabled forms should fail.
     *
     * Forms whose parents are disabled are considered disabled regardless of
     * their own state.
     */
    public function is_disabled(): bool;
    /**
     * Returns whether the form is empty.
     */
    public function is_empty(): bool;
    /**
     * Returns whether the data in the different formats is synchronized.
     *
     * If the data is not synchronized, you can get the transformation failure
     * by calling {@link getTransformationFailure()}.
     *
     * If the form is not submitted, this method always returns true.
     */
    public function is_synchronized(): bool;
    /**
     * Returns the data transformation failure, if any, during submission.
     */
    public function get_transformation_failure(): ?Exception\Transformation_Failed_Exception;
    /**
     * Initializes the form tree.
     *
     * Should be called on the root form after constructing the tree.
     *
     * @return $this
     *
     * @throws Exception\RuntimeException If the form is not the root
     */
    public function initialize(): static;
    /**
     * Inspects the given request and calls {@link submit()} if the form was
     * submitted.
     *
     * Internally, the request is forwarded to the configured
     * {@link RequestHandlerInterface} instance, which determines whether to
     * submit the form or not.
     *
     * @return $this
     */
    public function handle_request(mixed $request = null): static;
    /**
     * Submits data to the form.
     *
     * @param string|array|null $submittedData The submitted data
     * @param bool              $clearMissing  Whether to set fields to NULL
     *                                         when they are missing in the
     *                                         submitted data. This argument
     *                                         is only used in compound form
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the form has already been submitted
     */
    public function submit(string|array|null $submitted_data, bool $clear_missing = true): static;
    /**
     * Returns the root of the form tree.
     */
    public function get_root(): self;
    /**
     * Returns whether the field is the root of the form tree.
     */
    public function is_root(): bool;
    public function create_view(?Form_View $parent = null): Form_View;
}