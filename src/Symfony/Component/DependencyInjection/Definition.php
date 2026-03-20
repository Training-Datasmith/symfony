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
namespace Symfony\Component\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Argument\Bound_Argument;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\OutOfBoundsException;
/**
 * Definition represents a service definition.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Definition
{
    private const DEFAULT_DEPRECATION_TEMPLATE = 'The "%service_id%" service is deprecated. You should stop using it, as it will be removed in the future.';
    private ?string $class = null;
    private ?string $file = null;
    private string|array|null $factory = null;
    private bool $shared = true;
    private array $deprecation = [];
    private array $properties = [];
    private array $calls = [];
    private array $instanceof = [];
    private bool $autoconfigured = false;
    private string|array|null $configurator = null;
    private array $tags = [];
    private bool $public = false;
    private bool $synthetic = false;
    private bool $abstract = false;
    private bool $lazy = false;
    private ?array $decorated_service = null;
    private bool $autowired = false;
    private array $changes = [];
    private array $bindings = [];
    private array $errors = [];
    /**
     * @internal
     *
     * Used to store the name of the inner id when using service decoration together with autowiring
     */
    public ?string $inner_service_id = null;
    /**
     * @internal
     *
     * Used to store the behavior to follow when using service decoration and the decorated service is invalid
     */
    public ?int $decoration_on_invalid = null;
    /**
     * @internal
     *
     * Used to store the priority of the decoration
     */
    public ?int $decoration_priority = null;
    public function __construct(?string $class = null, protected array $arguments = [])
    {
        if (null !== $class) {
            $this->set_class($class);
        }
    }
    /**
     * Returns all changes tracked for the Definition object.
     */
    public function get_changes(): array
    {
        return $this->changes;
    }
    /**
     * Sets the tracked changes for the Definition object.
     *
     * @param array $changes An array of changes for this Definition
     *
     * @return $this
     */
    public function set_changes(array $changes): static
    {
        $this->changes = $changes;
        return $this;
    }
    /**
     * Sets a factory.
     *
     * @param string|array|Reference|null $factory A PHP function, reference or an array containing a class/Reference and a method to call
     *
     * @return $this
     */
    public function set_factory(string|array|Reference|null $factory): static
    {
        $this->changes['factory'] = true;
        if (\is_string($factory) && str_contains($factory, '::')) {
            $factory = explode('::', $factory, 2);
        } elseif ($factory instanceof Reference) {
            $factory = [$factory, '__invoke'];
        }
        $this->factory = $factory;
        return $this;
    }
    /**
     * Gets the factory.
     *
     * @return string|array|null The PHP function or an array containing a class/Reference and a method to call
     */
    public function get_factory(): string|array|null
    {
        return $this->factory;
    }
    /**
     * Sets the service that this service is decorating.
     *
     * @param string|null $id        The decorated service id, use null to remove decoration
     * @param string|null $renamedId The new decorated service id
     *
     * @return $this
     *
     * @throws InvalidArgumentException in case the decorated service id and the new decorated service id are equals
     */
    public function set_decorated_service(?string $id, ?string $renamed_id = null, int $priority = 0, int $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE): static
    {
        if ($renamed_id && $id === $renamed_id) {
            throw new InvalidArgumentException(\sprintf('The decorated service inner name for "%s" must be different than the service name itself.', $id));
        }
        $this->changes['decorated_service'] = true;
        if (null === $id) {
            $this->decorated_service = null;
        } else {
            $this->decorated_service = [$id, $renamed_id, $priority];
            if (Container_Interface::EXCEPTION_ON_INVALID_REFERENCE !== $invalid_behavior) {
                $this->decorated_service[] = $invalid_behavior;
            }
        }
        return $this;
    }
    /**
     * Gets the service that this service is decorating.
     *
     * @return array|null An array composed of the decorated service id, the new id for it and the priority of decoration, null if no service is decorated
     */
    public function get_decorated_service(): ?array
    {
        return $this->decorated_service;
    }
    /**
     * Sets the service class.
     *
     * @return $this
     */
    public function set_class(?string $class): static
    {
        $this->changes['class'] = true;
        $this->class = $class;
        return $this;
    }
    /**
     * Gets the service class.
     */
    public function get_class(): ?string
    {
        return $this->class;
    }
    /**
     * Sets the arguments to pass to the service constructor/factory method.
     *
     * @return $this
     */
    public function set_arguments(array $arguments): static
    {
        $this->arguments = $arguments;
        return $this;
    }
    /**
     * Sets the properties to define when creating the service.
     *
     * @return $this
     */
    public function set_properties(array $properties): static
    {
        $this->properties = $properties;
        return $this;
    }
    /**
     * Gets the properties to define when creating the service.
     */
    public function get_properties(): array
    {
        return $this->properties;
    }
    /**
     * Sets a specific property.
     *
     * @return $this
     */
    public function set_property(string $name, mixed $value): static
    {
        $this->properties[$name] = $value;
        return $this;
    }
    /**
     * Adds an argument to pass to the service constructor/factory method.
     *
     * @return $this
     */
    public function add_argument(mixed $argument): static
    {
        $this->arguments[] = $argument;
        return $this;
    }
    /**
     * Replaces a specific argument.
     *
     * @return $this
     *
     * @throws OutOfBoundsException When the replaced argument does not exist
     */
    public function replace_argument(int|string $index, mixed $argument): static
    {
        if (0 === \count($this->arguments)) {
            throw new OutOfBoundsException(\sprintf('Cannot replace arguments for class "%s" if none have been configured yet.', $this->class));
        }
        if (!\array_key_exists($index, $this->arguments)) {
            throw new OutOfBoundsException(\sprintf('The argument "%s" doesn\'t exist in class "%s".', $index, $this->class));
        }
        $this->arguments[$index] = $argument;
        return $this;
    }
    /**
     * Sets a specific argument.
     *
     * @return $this
     */
    public function set_argument(int|string $key, mixed $value): static
    {
        $this->arguments[$key] = $value;
        return $this;
    }
    /**
     * Gets the arguments to pass to the service constructor/factory method.
     */
    public function get_arguments(): array
    {
        return $this->arguments;
    }
    /**
     * Gets an argument to pass to the service constructor/factory method.
     *
     * @throws OutOfBoundsException When the argument does not exist
     */
    public function get_argument(int|string $index): mixed
    {
        if (!\array_key_exists($index, $this->arguments)) {
            throw new OutOfBoundsException(\sprintf('The argument "%s" doesn\'t exist in class "%s".', $index, $this->class));
        }
        return $this->arguments[$index];
    }
    /**
     * Sets the methods to call after service initialization.
     *
     * @return $this
     */
    public function set_method_calls(array $calls = []): static
    {
        $this->calls = [];
        foreach ($calls as $call) {
            $this->add_method_call($call[0], $call[1], $call[2] ?? false);
        }
        return $this;
    }
    /**
     * Adds a method to call after service initialization.
     *
     * @param string $method       The method name to call
     * @param array  $arguments    An array of arguments to pass to the method call
     * @param bool   $returnsClone Whether the call returns the service instance or not
     *
     * @return $this
     *
     * @throws InvalidArgumentException on empty $method param
     */
    public function add_method_call(string $method, array $arguments = [], bool $returns_clone = false): static
    {
        if (!$method) {
            throw new InvalidArgumentException('Method name cannot be empty.');
        }
        $this->calls[] = $returns_clone ? [$method, $arguments, true] : [$method, $arguments];
        return $this;
    }
    /**
     * Removes a method to call after service initialization.
     *
     * @return $this
     */
    public function remove_method_call(string $method): static
    {
        foreach ($this->calls as $i => $call) {
            if ($call[0] === $method) {
                unset($this->calls[$i]);
            }
        }
        return $this;
    }
    /**
     * Check if the current definition has a given method to call after service initialization.
     */
    public function has_method_call(string $method): bool
    {
        foreach ($this->calls as $call) {
            if ($call[0] === $method) {
                return true;
            }
        }
        return false;
    }
    /**
     * Gets the methods to call after service initialization.
     */
    public function get_method_calls(): array
    {
        return $this->calls;
    }
    /**
     * Sets the definition templates to conditionally apply on the current definition, keyed by parent interface/class.
     *
     * @param ChildDefinition[] $instanceof
     *
     * @return $this
     */
    public function set_instanceof_conditionals(array $instanceof): static
    {
        $this->instanceof = $instanceof;
        return $this;
    }
    /**
     * Gets the definition templates to conditionally apply on the current definition, keyed by parent interface/class.
     *
     * @return ChildDefinition[]
     */
    public function get_instanceof_conditionals(): array
    {
        return $this->instanceof;
    }
    /**
     * Sets whether or not instanceof conditionals should be prepended with a global set.
     *
     * @return $this
     */
    public function set_autoconfigured(bool $autoconfigured): static
    {
        $this->changes['autoconfigured'] = true;
        $this->autoconfigured = $autoconfigured;
        return $this;
    }
    public function is_autoconfigured(): bool
    {
        return $this->autoconfigured;
    }
    /**
     * Sets tags for this definition.
     *
     * @return $this
     */
    public function set_tags(array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }
    /**
     * Returns all tags.
     */
    public function get_tags(): array
    {
        return $this->tags;
    }
    /**
     * Gets a tag by name.
     */
    public function get_tag(string $name): array
    {
        return $this->tags[$name] ?? [];
    }
    /**
     * Adds a tag for this definition.
     *
     * @return $this
     */
    public function add_tag(string $name, array $attributes = []): static
    {
        $this->tags[$name][] = $attributes;
        return $this;
    }
    /**
     * Adds a "resource" tag to the definition and marks it as excluded.
     *
     * These definitions should be processed using {@see ContainerBuilder::findTaggedResourceIds()}
     *
     * @return $this
     */
    public function add_resource_tag(string $name, array $attributes = []): static
    {
        return $this->add_tag($name, $attributes)->add_tag('container.excluded', ['source' => \sprintf('by tag "%s"', $name)]);
    }
    /**
     * Whether this definition has a tag with the given name.
     */
    public function has_tag(string $name): bool
    {
        return isset($this->tags[$name]);
    }
    /**
     * Clears all tags for a given name.
     *
     * @return $this
     */
    public function clear_tag(string $name): static
    {
        unset($this->tags[$name]);
        return $this;
    }
    /**
     * Clears the tags for this definition.
     *
     * @return $this
     */
    public function clear_tags(): static
    {
        $this->tags = [];
        return $this;
    }
    /**
     * Sets a file to require before creating the service.
     *
     * @return $this
     */
    public function set_file(?string $file): static
    {
        $this->changes['file'] = true;
        $this->file = $file;
        return $this;
    }
    /**
     * Gets the file to require before creating the service.
     */
    public function get_file(): ?string
    {
        return $this->file;
    }
    /**
     * Sets if the service must be shared or not.
     *
     * @return $this
     */
    public function set_shared(bool $shared): static
    {
        $this->changes['shared'] = true;
        $this->shared = $shared;
        return $this;
    }
    /**
     * Whether this service is shared.
     */
    public function is_shared(): bool
    {
        return $this->shared;
    }
    /**
     * Sets the visibility of this service.
     *
     * @return $this
     */
    public function set_public(bool $boolean): static
    {
        $this->changes['public'] = true;
        $this->public = $boolean;
        return $this;
    }
    /**
     * Whether this service is public facing.
     */
    public function is_public(): bool
    {
        return $this->public;
    }
    /**
     * Whether this service is private.
     */
    public function is_private(): bool
    {
        return !$this->public;
    }
    /**
     * Sets the lazy flag of this service.
     *
     * @return $this
     */
    public function set_lazy(bool $lazy): static
    {
        $this->changes['lazy'] = true;
        $this->lazy = $lazy;
        return $this;
    }
    /**
     * Whether this service is lazy.
     */
    public function is_lazy(): bool
    {
        return $this->lazy;
    }
    /**
     * Sets whether this definition is synthetic, that is not constructed by the
     * container, but dynamically injected.
     *
     * @return $this
     */
    public function set_synthetic(bool $boolean): static
    {
        $this->synthetic = $boolean;
        if (!isset($this->changes['public'])) {
            $this->set_public(true);
        }
        return $this;
    }
    /**
     * Whether this definition is synthetic, that is not constructed by the
     * container, but dynamically injected.
     */
    public function is_synthetic(): bool
    {
        return $this->synthetic;
    }
    /**
     * Whether this definition is abstract, that means it merely serves as a
     * template for other definitions.
     *
     * @return $this
     */
    public function set_abstract(bool $boolean): static
    {
        $this->abstract = $boolean;
        return $this;
    }
    /**
     * Whether this definition is abstract, that means it merely serves as a
     * template for other definitions.
     */
    public function is_abstract(): bool
    {
        return $this->abstract;
    }
    /**
     * Whether this definition is deprecated, that means it should not be called
     * anymore.
     *
     * @param string $package The name of the composer package that is triggering the deprecation
     * @param string $version The version of the package that introduced the deprecation
     * @param string $message The deprecation message to use
     *
     * @return $this
     *
     * @throws InvalidArgumentException when the message template is invalid
     */
    public function set_deprecated(string $package, string $version, string $message): static
    {
        if ('' !== $message) {
            if (preg_match('#[\r\n]|\*/#', $message)) {
                throw new InvalidArgumentException('Invalid characters found in deprecation template.');
            }
            if (!str_contains($message, '%service_id%')) {
                throw new InvalidArgumentException('The deprecation template must contain the "%service_id%" placeholder.');
            }
        }
        $this->changes['deprecated'] = true;
        $this->deprecation = ['package' => $package, 'version' => $version, 'message' => $message ?: self::DEFAULT_DEPRECATION_TEMPLATE];
        return $this;
    }
    /**
     * Whether this definition is deprecated, that means it should not be called
     * anymore.
     */
    public function is_deprecated(): bool
    {
        return (bool) $this->deprecation;
    }
    /**
     * @param string $id Service id relying on this definition
     */
    public function get_deprecation(string $id): array
    {
        return ['package' => $this->deprecation['package'], 'version' => $this->deprecation['version'], 'message' => str_replace('%service_id%', $id, $this->deprecation['message'])];
    }
    /**
     * Sets a configurator to call after the service is fully initialized.
     *
     * @param string|array|Reference|null $configurator A PHP function, reference or an array containing a class/Reference and a method to call
     *
     * @return $this
     */
    public function set_configurator(string|array|Reference|null $configurator): static
    {
        $this->changes['configurator'] = true;
        if (\is_string($configurator) && str_contains($configurator, '::')) {
            $configurator = explode('::', $configurator, 2);
        } elseif ($configurator instanceof Reference) {
            $configurator = [$configurator, '__invoke'];
        }
        $this->configurator = $configurator;
        return $this;
    }
    /**
     * Gets the configurator to call after the service is fully initialized.
     */
    public function get_configurator(): string|array|null
    {
        return $this->configurator;
    }
    /**
     * Is the definition autowired?
     */
    public function is_autowired(): bool
    {
        return $this->autowired;
    }
    /**
     * Enables/disables autowiring.
     *
     * @return $this
     */
    public function set_autowired(bool $autowired): static
    {
        $this->changes['autowired'] = true;
        $this->autowired = $autowired;
        return $this;
    }
    /**
     * Gets bindings.
     *
     * @return BoundArgument[]
     */
    public function get_bindings(): array
    {
        return $this->bindings;
    }
    /**
     * Sets bindings.
     *
     * Bindings map $named or FQCN arguments to values that should be
     * injected in the matching parameters (of the constructor, of methods
     * called and of controller actions).
     *
     * @return $this
     */
    public function set_bindings(array $bindings): static
    {
        foreach ($bindings as $key => $binding) {
            if (0 < strpos((string) $key, '$') && $key !== $k = preg_replace('/[ \t]*\$/', ' $', (string) $key)) {
                unset($bindings[$key]);
                $bindings[$key = $k] = $binding;
            }
            if (!$binding instanceof Bound_Argument) {
                $bindings[$key] = new Bound_Argument($binding);
            }
        }
        $this->bindings = $bindings;
        return $this;
    }
    /**
     * Add an error that occurred when building this Definition.
     *
     * @return $this
     */
    public function add_error(string|\Closure|self $error): static
    {
        if ($error instanceof self) {
            $this->errors = array_merge($this->errors, $error->errors);
        } else {
            $this->errors[] = $error;
        }
        return $this;
    }
    /**
     * Returns any errors that occurred while building this Definition.
     */
    public function get_errors(): array
    {
        foreach ($this->errors as $i => $error) {
            if ($error instanceof \Closure) {
                $this->errors[$i] = (string) $error();
            } elseif (!\is_string($error)) {
                $this->errors[$i] = (string) $error;
            }
        }
        return $this->errors;
    }
    public function has_errors(): bool
    {
        return (bool) $this->errors;
    }
    public function __serialize(): array
    {
        $data = [];
        foreach ((array) $this as $k => $v) {
            if (false !== $i = strrpos((string) $k, "\x00")) {
                $k = substr((string) $k, 1 + $i);
            }
            if (!$v xor 'shared' === $k) {
                continue;
            }
            $data[$k] = $v;
        }
        return $data;
    }
}