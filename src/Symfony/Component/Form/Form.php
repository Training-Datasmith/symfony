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

use Symfony\Component\Form\Event\Post_Set_Data_Event;
use Symfony\Component\Form\Event\Post_Submit_Event;
use Symfony\Component\Form\Event\Pre_Set_Data_Event;
use Symfony\Component\Form\Event\Pre_Submit_Event;
use Symfony\Component\Form\Event\Submit_Event;
use Symfony\Component\Form\Exception\Already_Submitted_Exception;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Exception\OutOfBoundsException;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
use Symfony\Component\Form\Extension\Core\Type\Text_Type;
use Symfony\Component\Form\Extension\Validator\Constraints\Form as AssertForm;
use Symfony\Component\Form\Util\Form_Util;
use Symfony\Component\Form\Util\Inherit_Data_Aware_Iterator;
use Symfony\Component\Form\Util\Ordered_Hash_Map;
use Symfony\Component\Property_Access\Property_Path;
use Symfony\Component\Property_Access\Property_Path_Interface;
use Symfony\Component\Validator\Constraints\Traverse;
/**
 * Form represents a form.
 *
 * To implement your own form fields, you need to have a thorough understanding
 * of the data flow within a form. A form stores its data in three different
 * representations:
 *
 *   (1) the "model" format required by the form's object
 *   (2) the "normalized" format for internal processing
 *   (3) the "view" format used for display simple fields
 *       or map children model data for compound fields
 *
 * A date field, for example, may store a date as "Y-m-d" string (1) in the
 * object. To facilitate processing in the field, this value is normalized
 * to a DateTime object (2). In the HTML representation of your form, a
 * localized string (3) may be presented to and modified by the user, or it could be an array of values
 * to be mapped to choices fields.
 *
 * In most cases, format (1) and format (2) will be the same. For example,
 * a checkbox field uses a Boolean value for both internal processing and
 * storage in the object. In these cases you need to set a view transformer
 * to convert between formats (2) and (3). You can do this by calling
 * addViewTransformer().
 *
 * In some cases though it makes sense to make format (1) configurable. To
 * demonstrate this, let's extend our above date field to store the value
 * either as "Y-m-d" string or as timestamp. Internally we still want to
 * use a DateTime object for processing. To convert the data from string/integer
 * to DateTime you can set a model transformer by calling
 * addModelTransformer(). The normalized data is then converted to the displayed
 * data as described before.
 *
 * The conversions (1) -> (2) -> (3) use the transform methods of the transformers.
 * The conversions (3) -> (2) -> (1) use the reverseTransform methods of the transformers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements \IteratorAggregate<string, FormInterface>
 */
#[Assert_Form]
#[Traverse(false)]
class Form implements \IteratorAggregate, Form_Interface, Clearable_Errors_Interface
{
    private ?Form_Interface $parent = null;
    /**
     * A map of FormInterface instances.
     *
     * @var OrderedHashMap<FormInterface>
     */
    private Ordered_Hash_Map $children;
    /**
     * @var FormError[]
     */
    private array $errors = [];
    private bool $submitted = false;
    /**
     * The button that was used to submit the form.
     */
    private Form_Interface|Clickable_Interface|null $clicked_button = null;
    private mixed $model_data = null;
    private mixed $norm_data = null;
    private mixed $view_data = null;
    /**
     * The submitted values that don't belong to any children.
     */
    private array $extra_data = [];
    /**
     * The transformation failure generated during submission, if any.
     */
    private ?Transformation_Failed_Exception $transformation_failure = null;
    /**
     * Whether the form's data has been initialized.
     *
     * When the data is initialized with its default value, that default value
     * is passed through the transformer chain in order to synchronize the
     * model, normalized and view format for the first time. This is done
     * lazily in order to save performance when {@link setData()} is called
     * manually, making the initialization with the configured default value
     * superfluous.
     */
    private bool $default_data_set = false;
    /**
     * Whether setData() is currently being called.
     */
    private bool $lock_set_data = false;
    private string $name = '';
    /**
     * Whether the form inherits its underlying data from its parent.
     */
    private bool $inherit_data;
    private ?Property_Path_Interface $property_path = null;
    /**
     * @throws LogicException if a data mapper is not provided for a compound form
     */
    public function __construct(private readonly Form_Config_Interface $config)
    {
        // Compound forms always need a data mapper, otherwise calls to
        // `setData` and `add` will not lead to the correct population of
        // the child forms.
        if ($config->get_compound() && !$config->get_data_mapper()) {
            throw new LogicException('Compound forms need a data mapper.');
        }
        // If the form inherits the data from its parent, it is not necessary
        // to call setData() with the default data.
        if ($this->inherit_data = $config->get_inherit_data()) {
            $this->default_data_set = true;
        }
        $this->children = new Ordered_Hash_Map();
        $this->name = $config->get_name();
    }
    public function __clone()
    {
        $this->children = clone $this->children;
        foreach ($this->children as $key => $child) {
            $this->children[$key] = clone $child;
        }
    }
    public function get_config(): Form_Config_Interface
    {
        return $this->config;
    }
    public function get_name(): string
    {
        return $this->name;
    }
    public function get_property_path(): ?Property_Path_Interface
    {
        if ($this->property_path || $this->property_path = $this->config->get_property_path()) {
            return $this->property_path;
        }
        if ('' === $this->name) {
            return null;
        }
        $parent = $this->parent;
        while ($parent?->get_config()->get_inherit_data()) {
            $parent = $parent->get_parent();
        }
        if ($parent && null === $parent->get_config()->get_data_class()) {
            $this->property_path = new Property_Path('[' . $this->name . ']');
        } else {
            $this->property_path = new Property_Path($this->name);
        }
        return $this->property_path;
    }
    public function is_required(): bool
    {
        if (null === $this->parent || $this->parent->is_required()) {
            return $this->config->get_required();
        }
        return false;
    }
    public function is_disabled(): bool
    {
        if (null === $this->parent || !$this->parent->is_disabled()) {
            return $this->config->get_disabled();
        }
        return true;
    }
    public function set_parent(?Form_Interface $parent): static
    {
        if ($this->submitted) {
            throw new Already_Submitted_Exception('You cannot set the parent of a submitted form.');
        }
        if (null !== $parent && '' === $this->name) {
            throw new LogicException('A form with an empty name cannot have a parent form.');
        }
        $this->parent = $parent;
        return $this;
    }
    public function get_parent(): ?Form_Interface
    {
        return $this->parent;
    }
    public function get_root(): Form_Interface
    {
        return $this->parent ? $this->parent->get_root() : $this;
    }
    public function is_root(): bool
    {
        return null === $this->parent;
    }
    public function set_data(mixed $model_data): static
    {
        // If the form is submitted while disabled, it is set to submitted, but the data is not
        // changed. In such cases (i.e. when the form is not initialized yet) don't
        // abort this method.
        if ($this->submitted && $this->default_data_set) {
            throw new Already_Submitted_Exception('You cannot change the data of a submitted form.');
        }
        // If the form inherits its parent's data, disallow data setting to
        // prevent merge conflicts
        if ($this->inherit_data) {
            throw new RuntimeException('You cannot change the data of a form inheriting its parent data.');
        }
        // Don't allow modifications of the configured data if the data is locked
        if ($this->config->get_data_locked() && $model_data !== $this->config->get_data()) {
            return $this;
        }
        if (\is_object($model_data) && !$this->config->get_by_reference()) {
            $model_data = clone $model_data;
        }
        if ($this->lock_set_data) {
            throw new RuntimeException('A cycle was detected. Listeners to the PRE_SET_DATA event must not call setData(). You should call setData() on the FormEvent object instead.');
        }
        $this->lock_set_data = true;
        $dispatcher = $this->config->get_event_dispatcher();
        // Hook to change content of the model data before transformation and mapping children
        if ($dispatcher->has_listeners(Form_Events::PRE_SET_DATA)) {
            $event = new Pre_Set_Data_Event($this, $model_data);
            $dispatcher->dispatch($event, Form_Events::PRE_SET_DATA);
            $model_data = $event->get_data();
        }
        // Treat data as strings unless a transformer exists
        if (\is_scalar($model_data) && !$this->config->get_view_transformers() && !$this->config->get_model_transformers()) {
            $model_data = (string) $model_data;
        }
        // Synchronize representations - must not change the content!
        // Transformation exceptions are not caught on initialization
        $norm_data = $this->model_to_norm($model_data);
        $view_data = $this->norm_to_view($norm_data);
        // Validate if view data matches data class (unless empty)
        if (!Form_Util::is_empty($view_data)) {
            $data_class = $this->config->get_data_class();
            if (null !== $data_class && !$view_data instanceof $data_class) {
                $actual_type = get_debug_type($view_data);
                throw new LogicException(\sprintf('The form\'s view data is expected to be a "%s", but it is a "%s". You can avoid this error by setting the "data_class" option to null or by adding a view transformer that transforms "%2$s" to an instance of "%1$s".', $data_class, $actual_type));
            }
        }
        $this->model_data = $model_data;
        $this->norm_data = $norm_data;
        $this->view_data = $view_data;
        $this->default_data_set = true;
        $this->lock_set_data = false;
        // Compound forms don't need to invoke this method if they don't have children
        if (\count($this->children) > 0) {
            // Update child forms from the data (unless their config data is locked)
            $this->config->get_data_mapper()->map_data_to_forms($view_data, new \Recursive_Iterator_Iterator(new Inherit_Data_Aware_Iterator($this->children)));
        }
        if ($dispatcher->has_listeners(Form_Events::POST_SET_DATA)) {
            $event = new Post_Set_Data_Event($this, $model_data);
            $dispatcher->dispatch($event, Form_Events::POST_SET_DATA);
        }
        return $this;
    }
    public function get_data(): mixed
    {
        if ($this->inherit_data) {
            if (!$this->parent) {
                throw new RuntimeException('The form is configured to inherit its parent\'s data, but does not have a parent.');
            }
            return $this->parent->get_data();
        }
        if (!$this->default_data_set) {
            if ($this->lock_set_data) {
                throw new RuntimeException('A cycle was detected. Listeners to the PRE_SET_DATA event must not call getData() if the form data has not already been set. You should call getData() on the FormEvent object instead.');
            }
            $this->set_data($this->config->get_data());
        }
        return $this->model_data;
    }
    public function get_norm_data(): mixed
    {
        if ($this->inherit_data) {
            if (!$this->parent) {
                throw new RuntimeException('The form is configured to inherit its parent\'s data, but does not have a parent.');
            }
            return $this->parent->get_norm_data();
        }
        if (!$this->default_data_set) {
            if ($this->lock_set_data) {
                throw new RuntimeException('A cycle was detected. Listeners to the PRE_SET_DATA event must not call getNormData() if the form data has not already been set.');
            }
            $this->set_data($this->config->get_data());
        }
        return $this->norm_data;
    }
    public function get_view_data(): mixed
    {
        if ($this->inherit_data) {
            if (!$this->parent) {
                throw new RuntimeException('The form is configured to inherit its parent\'s data, but does not have a parent.');
            }
            return $this->parent->get_view_data();
        }
        if (!$this->default_data_set) {
            if ($this->lock_set_data) {
                throw new RuntimeException('A cycle was detected. Listeners to the PRE_SET_DATA event must not call getViewData() if the form data has not already been set.');
            }
            $this->set_data($this->config->get_data());
        }
        return $this->view_data;
    }
    public function get_extra_data(): array
    {
        return $this->extra_data;
    }
    public function initialize(): static
    {
        if (null !== $this->parent) {
            throw new RuntimeException('Only root forms should be initialized.');
        }
        // Guarantee that the *_SET_DATA events have been triggered once the
        // form is initialized. This makes sure that dynamically added or
        // removed fields are already visible after initialization.
        if (!$this->default_data_set) {
            $this->set_data($this->config->get_data());
        }
        return $this;
    }
    public function handle_request(mixed $request = null): static
    {
        $this->config->get_request_handler()->handle_request($this, $request);
        return $this;
    }
    public function submit(mixed $submitted_data, bool $clear_missing = true): static
    {
        if ($this->submitted) {
            throw new Already_Submitted_Exception('A form can only be submitted once.');
        }
        // Initialize errors in the very beginning so we're sure
        // they are collectable during submission only
        $this->errors = [];
        // Obviously, a disabled form should not change its data upon submission.
        if ($this->is_disabled()) {
            $this->submitted = true;
            return $this;
        }
        // The data must be initialized if it was not initialized yet.
        // This is necessary to guarantee that the *_SET_DATA listeners
        // are always invoked before submit() takes place.
        if (!$this->default_data_set) {
            $this->set_data($this->config->get_data());
        }
        // Treat false as NULL to support binding false to checkboxes.
        // Don't convert NULL to a string here in order to determine later
        // whether an empty value has been submitted or whether no value has
        // been submitted at all. This is important for processing checkboxes
        // and radio buttons with empty values.
        if (false === $submitted_data) {
            $submitted_data = null;
        } elseif (\is_scalar($submitted_data)) {
            $submitted_data = (string) $submitted_data;
        } elseif ($this->config->get_request_handler()->is_file_upload($submitted_data)) {
            if (!$this->config->get_option('allow_file_upload')) {
                $submitted_data = null;
                $this->transformation_failure = new Transformation_Failed_Exception('Submitted data was expected to be text or number, file upload given.');
            }
        } elseif (\is_array($submitted_data) && !$this->config->get_compound() && !$this->config->get_option('multiple', false)) {
            $submitted_data = null;
            $this->transformation_failure = new Transformation_Failed_Exception('Submitted data was expected to be text or number, array given.');
        }
        $dispatcher = $this->config->get_event_dispatcher();
        $model_data = null;
        $norm_data = null;
        $view_data = null;
        try {
            if (null !== $this->transformation_failure) {
                throw $this->transformation_failure;
            }
            // Hook to change content of the data submitted by the browser
            if ($dispatcher->has_listeners(Form_Events::PRE_SUBMIT)) {
                $event = new Pre_Submit_Event($this, $submitted_data);
                $dispatcher->dispatch($event, Form_Events::PRE_SUBMIT);
                $submitted_data = $event->get_data();
            }
            // Check whether the form is compound.
            // This check is preferable over checking the number of children,
            // since forms without children may also be compound.
            // (think of empty collection forms)
            if ($this->config->get_compound()) {
                if (!\is_array($submitted_data ??= [])) {
                    throw new Transformation_Failed_Exception('Compound forms expect an array or NULL on submission.');
                }
                foreach ($this->children as $name => $child) {
                    $is_submitted = \array_key_exists($name, $submitted_data);
                    if ($is_submitted || $clear_missing) {
                        $child->submit($is_submitted ? $submitted_data[$name] : null, $clear_missing);
                        unset($submitted_data[$name]);
                        if (null !== $this->clicked_button) {
                            continue;
                        }
                        if ($child instanceof Clickable_Interface && $child->is_clicked()) {
                            $this->clicked_button = $child;
                            continue;
                        }
                        if (method_exists($child, 'getClickedButton') && null !== $child->get_clicked_button()) {
                            $this->clicked_button = $child->get_clicked_button();
                        }
                    }
                }
                $this->extra_data = $submitted_data;
            }
            // Forms that inherit their parents' data also are not processed,
            // because then it would be too difficult to merge the changes in
            // the child and the parent form. Instead, the parent form also takes
            // changes in the grandchildren (i.e. children of the form that inherits
            // its parent's data) into account.
            // (see InheritDataAwareIterator below)
            if (!$this->inherit_data) {
                // If the form is compound, the view data is merged with the data
                // of the children using the data mapper.
                // If the form is not compound, the view data is assigned to the submitted data.
                $view_data = $this->config->get_compound() ? $this->view_data : $submitted_data;
                if (Form_Util::is_empty($view_data)) {
                    $empty_data = $this->config->get_empty_data();
                    if ($empty_data instanceof \Closure) {
                        $empty_data = $empty_data($this, $view_data);
                    }
                    $view_data = $empty_data;
                }
                // Merge form data from children into existing view data
                // It is not necessary to invoke this method if the form has no children,
                // even if it is compound.
                if (\count($this->children) > 0) {
                    // Use InheritDataAwareIterator to process children of
                    // descendants that inherit this form's data.
                    // These descendants will not be submitted normally (see the check
                    // for $this->config->getInheritData() above)
                    $this->config->get_data_mapper()->map_forms_to_data(new \Recursive_Iterator_Iterator(new Inherit_Data_Aware_Iterator($this->children)), $view_data);
                }
                // Normalize data to unified representation
                $norm_data = $this->view_to_norm($view_data);
                // Hook to change content of the data in the normalized
                // representation
                if ($dispatcher->has_listeners(Form_Events::SUBMIT)) {
                    $event = new Submit_Event($this, $norm_data);
                    $dispatcher->dispatch($event, Form_Events::SUBMIT);
                    $norm_data = $event->get_data();
                }
                // Synchronize representations - must not change the content!
                $model_data = $this->norm_to_model($norm_data);
                $view_data = $this->norm_to_view($norm_data);
            }
        } catch (Transformation_Failed_Exception $e) {
            $this->transformation_failure = $e;
            // If $viewData was not yet set, set it to $submittedData so that
            // the erroneous data is accessible on the form.
            // Forms that inherit data never set any data, because the getters
            // forward to the parent form's getters anyway.
            if (null === $view_data && !$this->inherit_data) {
                $view_data = $submitted_data;
            }
        }
        $this->submitted = true;
        $this->model_data = $model_data;
        $this->norm_data = $norm_data;
        $this->view_data = $view_data;
        if ($dispatcher->has_listeners(Form_Events::POST_SUBMIT)) {
            $event = new Post_Submit_Event($this, $view_data);
            $dispatcher->dispatch($event, Form_Events::POST_SUBMIT);
        }
        return $this;
    }
    public function add_error(Form_Error $error): static
    {
        if (null === $error->get_origin()) {
            $error->set_origin($this);
        }
        if ($this->parent && $this->config->get_error_bubbling()) {
            $this->parent->add_error($error);
        } else {
            $this->errors[] = $error;
        }
        return $this;
    }
    public function is_submitted(): bool
    {
        return $this->submitted;
    }
    public function is_synchronized(): bool
    {
        return null === $this->transformation_failure;
    }
    public function get_transformation_failure(): ?Transformation_Failed_Exception
    {
        return $this->transformation_failure;
    }
    public function is_empty(): bool
    {
        foreach ($this->children as $child) {
            if (!$child->is_empty()) {
                return false;
            }
        }
        if (null !== $is_empty_callback = $this->config->get_is_empty_callback()) {
            return $is_empty_callback($this->model_data);
        }
        return Form_Util::is_empty($this->model_data) || is_countable($this->model_data) && 0 === \count($this->model_data) || $this->model_data instanceof \Traversable && 0 === iterator_count($this->model_data);
    }
    public function is_valid(): bool
    {
        if (!$this->submitted) {
            throw new LogicException('Cannot check if an unsubmitted form is valid. Call Form::isSubmitted() and ensure that it\'s true before calling Form::isValid().');
        }
        if ($this->is_disabled()) {
            return true;
        }
        return 0 === \count($this->get_errors(true));
    }
    /**
     * Returns the button that was used to submit the form.
     */
    public function get_clicked_button(): Form_Interface|Clickable_Interface|null
    {
        if ($this->clicked_button) {
            return $this->clicked_button;
        }
        return $this->parent && method_exists($this->parent, 'getClickedButton') ? $this->parent->get_clicked_button() : null;
    }
    public function get_errors(bool $deep = false, bool $flatten = true): Form_Error_Iterator
    {
        $errors = $this->errors;
        // Copy the errors of nested forms to the $errors array
        if ($deep) {
            foreach ($this as $child) {
                /** @var FormInterface $child */
                if ($child->is_submitted() && $child->is_valid()) {
                    continue;
                }
                $iterator = $child->get_errors(true, $flatten);
                if (0 === \count($iterator)) {
                    continue;
                }
                if ($flatten) {
                    foreach ($iterator as $error) {
                        $errors[] = $error;
                    }
                } else {
                    $errors[] = $iterator;
                }
            }
        }
        return new Form_Error_Iterator($this, $errors);
    }
    public function clear_errors(bool $deep = false): static
    {
        $this->errors = [];
        if ($deep) {
            // Clear errors from children
            foreach ($this as $child) {
                if ($child instanceof Clearable_Errors_Interface) {
                    $child->clear_errors(true);
                }
            }
        }
        return $this;
    }
    public function all(): array
    {
        return iterator_to_array($this->children);
    }
    public function add(Form_Interface|string $child, ?string $type = null, array $options = []): static
    {
        if ($this->submitted) {
            throw new Already_Submitted_Exception('You cannot add children to a submitted form.');
        }
        if (!$this->config->get_compound()) {
            throw new LogicException('You cannot add children to a simple form. Maybe you should set the option "compound" to true?');
        }
        if (!$child instanceof Form_Interface) {
            // Never initialize child forms automatically
            $options['auto_initialize'] = false;
            if (null === $type && null === $this->config->get_data_class()) {
                $type = Text_Type::class;
            }
            if (null === $type) {
                $child = $this->config->get_form_factory()->create_for_property($this->config->get_data_class(), $child, null, $options);
            } else {
                $child = $this->config->get_form_factory()->create_named($child, $type, null, $options);
            }
        } elseif ($child->get_config()->get_auto_initialize()) {
            throw new RuntimeException(\sprintf('Automatic initialization is only supported on root forms. You should set the "auto_initialize" option to false on the field "%s".', $child->get_name()));
        }
        $this->children[$child->get_name()] = $child;
        $child->set_parent($this);
        // If setData() is currently being called, there is no need to call
        // mapDataToForms() here, as mapDataToForms() is called at the end
        // of setData() anyway. Not doing this check leads to an endless
        // recursion when initializing the form lazily and an event listener
        // (such as ResizeFormListener) adds fields depending on the data:
        //
        //  * setData() is called, the form is not initialized yet
        //  * add() is called by the listener (setData() is not complete, so
        //    the form is still not initialized)
        //  * getViewData() is called
        //  * setData() is called since the form is not initialized yet
        //  * ... endless recursion ...
        //
        // Also skip data mapping if setData() has not been called yet.
        // setData() will be called upon form initialization and data mapping
        // will take place by then.
        if (!$this->lock_set_data && $this->default_data_set && !$this->inherit_data) {
            $view_data = $this->get_view_data();
            $this->config->get_data_mapper()->map_data_to_forms($view_data, new \Recursive_Iterator_Iterator(new Inherit_Data_Aware_Iterator(new \ArrayIterator([$child->get_name() => $child]))));
        }
        return $this;
    }
    public function remove(string $name): static
    {
        if ($this->submitted) {
            throw new Already_Submitted_Exception('You cannot remove children from a submitted form.');
        }
        if (isset($this->children[$name])) {
            if (!$this->children[$name]->is_submitted()) {
                $this->children[$name]->set_parent(null);
            }
            unset($this->children[$name]);
        }
        return $this;
    }
    public function has(string $name): bool
    {
        return isset($this->children[$name]);
    }
    public function get(string $name): Form_Interface
    {
        if (isset($this->children[$name])) {
            return $this->children[$name];
        }
        throw new OutOfBoundsException(\sprintf('Child "%s" does not exist.', $name));
    }
    /**
     * Returns whether a child with the given name exists (implements the \ArrayAccess interface).
     *
     * @param string $name The name of the child
     */
    public function offsetExists(mixed $name): bool
    {
        return $this->has($name);
    }
    /**
     * Returns the child with the given name (implements the \ArrayAccess interface).
     *
     * @param string $name The name of the child
     *
     * @throws OutOfBoundsException if the named child does not exist
     */
    public function offsetGet(mixed $name): Form_Interface
    {
        return $this->get($name);
    }
    /**
     * Adds a child to the form (implements the \ArrayAccess interface).
     *
     * @param string        $name  Ignored. The name of the child is used
     * @param FormInterface $child The child to be added
     *
     * @throws AlreadySubmittedException if the form has already been submitted
     * @throws LogicException            when trying to add a child to a non-compound form
     *
     * @see self::add()
     */
    public function offsetSet(mixed $name, mixed $child): void
    {
        $this->add($child);
    }
    /**
     * Removes the child with the given name from the form (implements the \ArrayAccess interface).
     *
     * @param string $name The name of the child to remove
     *
     * @throws AlreadySubmittedException if the form has already been submitted
     */
    public function offsetUnset(mixed $name): void
    {
        $this->remove($name);
    }
    /**
     * Returns the iterator for this group.
     *
     * @return \Traversable<string, FormInterface>
     */
    public function getIterator(): \Traversable
    {
        return $this->children;
    }
    /**
     * Returns the number of form children (implements the \Countable interface).
     */
    public function count(): int
    {
        return \count($this->children);
    }
    public function create_view(?Form_View $parent = null): Form_View
    {
        if (null === $parent && $this->parent) {
            $parent = $this->parent->create_view();
        }
        $type = $this->config->get_type();
        $options = $this->config->get_options();
        // The methods createView(), buildView() and finishView() are called
        // explicitly here in order to be able to override either of them
        // in a custom resolved form type.
        $view = $type->create_view($this, $parent);
        $type->build_view($view, $this, $options);
        foreach ($this->children as $name => $child) {
            $view->children[$name] = $child->create_view($view);
        }
        $this->sort($view->children);
        $type->finish_view($view, $this, $options);
        return $view;
    }
    /**
     * Sorts view fields based on their priority value.
     */
    private function sort(array &$children): void
    {
        $c = [];
        $i = 0;
        $needs_sorting = false;
        foreach ($children as $name => $child) {
            $c[$name] = ['p' => $child->vars['priority'] ?? 0, 'i' => $i++];
            if (0 !== $c[$name]['p']) {
                $needs_sorting = true;
            }
        }
        if (!$needs_sorting) {
            return;
        }
        uksort($children, static fn($a, $b): int => [$c[$b]['p'], $c[$a]['i']] <=> [$c[$a]['p'], $c[$b]['i']]);
    }
    /**
     * Normalizes the underlying data if a model transformer is set.
     *
     * @throws TransformationFailedException If the underlying data cannot be transformed to "normalized" format
     */
    private function model_to_norm(mixed $value): mixed
    {
        try {
            foreach ($this->config->get_model_transformers() as $transformer) {
                $value = $transformer->transform($value);
            }
        } catch (Transformation_Failed_Exception $exception) {
            throw new Transformation_Failed_Exception(\sprintf('Unable to transform data for property path "%s": ', $this->get_property_path()) . $exception->get_message(), $exception->get_code(), $exception, $exception->get_invalid_message(), $exception->get_invalid_message_parameters());
        }
        return $value;
    }
    /**
     * Reverse transforms a value if a model transformer is set.
     *
     * @throws TransformationFailedException If the value cannot be transformed to "model" format
     */
    private function norm_to_model(mixed $value): mixed
    {
        try {
            $transformers = $this->config->get_model_transformers();
            for ($i = \count($transformers) - 1; $i >= 0; --$i) {
                $value = $transformers[$i]->reverse_transform($value);
            }
        } catch (Transformation_Failed_Exception $exception) {
            throw new Transformation_Failed_Exception(\sprintf('Unable to reverse value for property path "%s": ', $this->get_property_path()) . $exception->get_message(), $exception->get_code(), $exception, $exception->get_invalid_message(), $exception->get_invalid_message_parameters());
        }
        return $value;
    }
    /**
     * Transforms the value if a view transformer is set.
     *
     * @throws TransformationFailedException If the normalized value cannot be transformed to "view" format
     */
    private function norm_to_view(mixed $value): mixed
    {
        // Scalar values should  be converted to strings to
        // facilitate differentiation between empty ("") and zero (0).
        // Only do this for simple forms, as the resulting value in
        // compound forms is passed to the data mapper and thus should
        // not be converted to a string before.
        if (!($transformers = $this->config->get_view_transformers()) && !$this->config->get_compound()) {
            return null === $value || \is_scalar($value) ? (string) $value : $value;
        }
        try {
            foreach ($transformers as $transformer) {
                $value = $transformer->transform($value);
            }
        } catch (Transformation_Failed_Exception $exception) {
            throw new Transformation_Failed_Exception(\sprintf('Unable to transform value for property path "%s": ', $this->get_property_path()) . $exception->get_message(), $exception->get_code(), $exception, $exception->get_invalid_message(), $exception->get_invalid_message_parameters());
        }
        return $value;
    }
    /**
     * Reverse transforms a value if a view transformer is set.
     *
     * @throws TransformationFailedException If the submitted value cannot be transformed to "normalized" format
     */
    private function view_to_norm(mixed $value): mixed
    {
        if (!$transformers = $this->config->get_view_transformers()) {
            return '' === $value ? null : $value;
        }
        try {
            for ($i = \count($transformers) - 1; $i >= 0; --$i) {
                $value = $transformers[$i]->reverse_transform($value);
            }
        } catch (Transformation_Failed_Exception $exception) {
            throw new Transformation_Failed_Exception(\sprintf('Unable to reverse value for property path "%s": ', $this->get_property_path()) . $exception->get_message(), $exception->get_code(), $exception, $exception->get_invalid_message(), $exception->get_invalid_message_parameters());
        }
        return $value;
    }
}