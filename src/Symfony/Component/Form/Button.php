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

use Symfony\Component\Form\Exception\Already_Submitted_Exception;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
use Symfony\Component\Property_Access\Property_Path_Interface;
/**
 * A form button.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements \IteratorAggregate<string, FormInterface>
 */
class Button implements \IteratorAggregate, Form_Interface
{
    private ?Form_Interface $parent = null;
    private bool $submitted = false;
    /**
     * Creates a new button from a form configuration.
     */
    public function __construct(private readonly Form_Config_Interface $config)
    {
    }
    /**
     * Unsupported method.
     */
    public function offsetExists(mixed $offset): bool
    {
        return false;
    }
    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function offsetGet(mixed $offset): Form_Interface
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }
    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }
    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }
    public function set_parent(?Form_Interface $parent): static
    {
        if ($this->submitted) {
            throw new Already_Submitted_Exception('You cannot set the parent of a submitted button.');
        }
        $this->parent = $parent;
        return $this;
    }
    public function get_parent(): ?Form_Interface
    {
        return $this->parent;
    }
    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function add(string|Form_Interface $child, ?string $type = null, array $options = []): static
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }
    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function get(string $name): Form_Interface
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
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function remove(string $name): static
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }
    public function all(): array
    {
        return [];
    }
    public function get_errors(bool $deep = false, bool $flatten = true): Form_Error_Iterator
    {
        return new Form_Error_Iterator($this, []);
    }
    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @return $this
     */
    public function set_data(mixed $model_data): static
    {
        // no-op, called during initialization of the form tree
        return $this;
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
    public function get_norm_data(): mixed
    {
        return null;
    }
    /**
     * Unsupported method.
     */
    public function get_view_data(): mixed
    {
        return null;
    }
    /**
     * Unsupported method.
     */
    public function get_extra_data(): array
    {
        return [];
    }
    /**
     * Returns the button's configuration.
     */
    public function get_config(): Form_Config_Interface
    {
        return $this->config;
    }
    /**
     * Returns whether the button is submitted.
     */
    public function is_submitted(): bool
    {
        return $this->submitted;
    }
    /**
     * Returns the name by which the button is identified in forms.
     */
    public function get_name(): string
    {
        return $this->config->get_name();
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
     *
     * @throws BadMethodCallException
     */
    public function add_error(Form_Error $error): static
    {
        throw new BadMethodCallException('Buttons cannot have errors.');
    }
    /**
     * Unsupported method.
     */
    public function is_valid(): bool
    {
        return true;
    }
    /**
     * Unsupported method.
     */
    public function is_required(): bool
    {
        return false;
    }
    public function is_disabled(): bool
    {
        if ($this->parent?->is_disabled()) {
            return true;
        }
        return $this->config->get_disabled();
    }
    /**
     * Unsupported method.
     */
    public function is_empty(): bool
    {
        return true;
    }
    /**
     * Unsupported method.
     */
    public function is_synchronized(): bool
    {
        return true;
    }
    /**
     * Unsupported method.
     */
    public function get_transformation_failure(): ?Transformation_Failed_Exception
    {
        return null;
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function initialize(): static
    {
        throw new BadMethodCallException('Buttons cannot be initialized. Call initialize() on the root form instead.');
    }
    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function handle_request(mixed $request = null): static
    {
        throw new BadMethodCallException('Buttons cannot handle requests. Call handleRequest() on the root form instead.');
    }
    /**
     * Submits data to the button.
     *
     * @return $this
     *
     * @throws AlreadySubmittedException if the button has already been submitted
     */
    public function submit(array|string|null $submitted_data, bool $clear_missing = true): static
    {
        if ($this->submitted) {
            throw new Already_Submitted_Exception('A form can only be submitted once.');
        }
        $this->submitted = true;
        return $this;
    }
    public function get_root(): Form_Interface
    {
        return $this->parent ? $this->parent->get_root() : $this;
    }
    public function is_root(): bool
    {
        return null === $this->parent;
    }
    public function create_view(?Form_View $parent = null): Form_View
    {
        if (null === $parent && $this->parent) {
            $parent = $this->parent->create_view();
        }
        $type = $this->config->get_type();
        $options = $this->config->get_options();
        $view = $type->create_view($this, $parent);
        $type->build_view($view, $this, $options);
        $type->finish_view($view, $this, $options);
        return $view;
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