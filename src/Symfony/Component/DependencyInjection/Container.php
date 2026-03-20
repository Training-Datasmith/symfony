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

use Symfony\Component\Dependency_Injection\Argument\Rewindable_Generator;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator as ArgumentServiceLocator;
use Symfony\Component\Dependency_Injection\Exception\Env_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\Parameter_Circular_Reference_Exception;
use Symfony\Component\Dependency_Injection\Exception\Parameter_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Exception\Service_Circular_Reference_Exception;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Env_Placeholder_Parameter_Bag;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Frozen_Parameter_Bag;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag_Interface;
use Symfony\Contracts\Service\Reset_Interface;
// Help opcache.preload discover always-needed symbols
class_exists(Rewindable_Generator::class);
class_exists(Argument_Service_Locator::class);
/**
 * Container is a dependency injection container.
 *
 * It gives access to object instances (services).
 * Services and parameters are simple key/pair stores.
 * The container can have five possible behaviors when a service
 * does not exist (or is not initialized for the last case):
 *
 *  * EXCEPTION_ON_INVALID_REFERENCE: Throws an exception at compilation time (the default)
 *  * NULL_ON_INVALID_REFERENCE:      Returns null
 *  * IGNORE_ON_INVALID_REFERENCE:    Ignores the wrapping command asking for the reference
 *                                    (for instance, ignore a setter if the service does not exist)
 *  * IGNORE_ON_UNINITIALIZED_REFERENCE: Ignores/returns null for uninitialized services or invalid references
 *  * RUNTIME_EXCEPTION_ON_INVALID_REFERENCE: Throws an exception at runtime
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Container implements Container_Interface, Reset_Interface
{
    protected array $services = [];
    protected array $privates = [];
    protected array $file_map = [];
    protected array $method_map = [];
    protected array $factories = [];
    protected array $aliases = [];
    protected array $loading = [];
    protected array $resolving = [];
    protected array $synthetic_ids = [];
    private array $env_cache = [];
    private bool $compiled = false;
    private \Closure $get_env;
    private static \Closure $make;
    public function __construct(protected ?Parameter_Bag_Interface $parameter_bag = new Env_Placeholder_Parameter_Bag())
    {
    }
    /**
     * Compiles the container.
     *
     * This method does two things:
     *
     *  * Parameter values are resolved;
     *  * The parameter bag is frozen.
     */
    public function compile(): void
    {
        $this->parameter_bag->resolve();
        $this->parameter_bag = new Frozen_Parameter_Bag($this->parameter_bag->all(), $this->parameter_bag instanceof Parameter_Bag ? $this->parameter_bag->all_deprecated() : [], $this->parameter_bag instanceof Parameter_Bag ? $this->parameter_bag->all_non_empty() : []);
        $this->compiled = true;
    }
    /**
     * Returns true if the container is compiled.
     */
    public function is_compiled(): bool
    {
        return $this->compiled;
    }
    /**
     * Gets the service container parameter bag.
     */
    public function get_parameter_bag(): Parameter_Bag_Interface
    {
        return $this->parameter_bag;
    }
    /**
     * Gets a parameter.
     *
     * @throws ParameterNotFoundException if the parameter is not defined
     */
    public function get_parameter(string $name): array|bool|string|int|float|\Unit_Enum|null
    {
        return $this->parameter_bag->get($name);
    }
    public function has_parameter(string $name): bool
    {
        return $this->parameter_bag->has($name);
    }
    public function set_parameter(string $name, array|bool|string|int|float|\Unit_Enum|null $value): void
    {
        $this->parameter_bag->set($name, $value);
    }
    /**
     * Sets a service.
     *
     * Setting a synthetic service to null resets it: has() returns false and get()
     * behaves in the same way as if the service was never created.
     */
    public function set(string $id, ?object $service): void
    {
        // Runs the internal initializer; used by the dumped container to include always-needed files
        if (isset($this->privates['service_container']) && $this->privates['service_container'] instanceof \Closure) {
            $initialize = $this->privates['service_container'];
            unset($this->privates['service_container']);
            $initialize($this);
        }
        if ('service_container' === $id) {
            throw new InvalidArgumentException('You cannot set service "service_container".');
        }
        if (!(isset($this->file_map[$id]) || isset($this->method_map[$id]))) {
            if (isset($this->synthetic_ids[$id]) || !isset($this->get_removed_ids()[$id])) {
                // no-op
            } elseif (null === $service) {
                throw new InvalidArgumentException(\sprintf('The "%s" service is private, you cannot unset it.', $id));
            } else {
                throw new InvalidArgumentException(\sprintf('The "%s" service is private, you cannot replace it.', $id));
            }
        } elseif (isset($this->services[$id])) {
            throw new InvalidArgumentException(\sprintf('The "%s" service is already initialized, you cannot replace it.', $id));
        }
        if (isset($this->aliases[$id])) {
            unset($this->aliases[$id]);
        }
        if (null === $service) {
            unset($this->services[$id]);
            return;
        }
        $this->services[$id] = $service;
    }
    public function has(string $id): bool
    {
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }
        if (isset($this->services[$id])) {
            return true;
        }
        if ('service_container' === $id) {
            return true;
        }
        return isset($this->file_map[$id]) || isset($this->method_map[$id]);
    }
    /**
     * Gets a service.
     *
     * @throws ServiceCircularReferenceException When a circular reference is detected
     * @throws ServiceNotFoundException          When the service is not defined
     *
     * @see Reference
     */
    public function get(string $id, int $invalid_behavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
    {
        return $this->services[$id] ?? $this->services[$id = $this->aliases[$id] ?? $id] ?? ('service_container' === $id ? $this : ($this->factories[$id] ?? self::$make ??= self::make(...))($this, $id, $invalid_behavior));
    }
    /**
     * Creates a service.
     *
     * As a separate method to allow "get()" to use the really fast `??` operator.
     */
    private static function make(self $container, string $id, int $invalid_behavior): ?object
    {
        if (isset($container->loading[$id])) {
            throw new Service_Circular_Reference_Exception($id, array_merge(array_keys($container->loading), [$id]));
        }
        $container->loading[$id] = true;
        try {
            if (isset($container->file_map[$id])) {
                return 4 === $invalid_behavior ? null : $container->load($container->file_map[$id]);
            }
            if (isset($container->method_map[$id])) {
                return 4 === $invalid_behavior ? null : $container->{$container->method_map[$id]}($container);
            }
        } catch (\Exception $e) {
            unset($container->services[$id]);
            throw $e;
        } finally {
            unset($container->loading[$id]);
        }
        if (self::EXCEPTION_ON_INVALID_REFERENCE === $invalid_behavior) {
            if (!$id) {
                throw new Service_Not_Found_Exception($id);
            }
            if (isset($container->synthetic_ids[$id])) {
                throw new Service_Not_Found_Exception($id, null, null, [], \sprintf('The "%s" service is synthetic, it needs to be set at boot time before it can be used.', $id));
            }
            if (isset($container->get_removed_ids()[$id])) {
                throw new Service_Not_Found_Exception($id, null, null, [], \sprintf('The "%s" service or alias has been removed or inlined when the container was compiled. You should either make it public, or stop using the container directly and use dependency injection instead.', $id));
            }
            $alternatives = [];
            foreach ($container->get_service_ids() as $known_id) {
                if ('' === $known_id) {
                    continue;
                }
                if ('.' === $known_id[0]) {
                    continue;
                }
                $lev = levenshtein($id, $known_id);
                if ($lev <= \strlen($id) / 3 || str_contains($known_id, $id)) {
                    $alternatives[] = $known_id;
                }
            }
            throw new Service_Not_Found_Exception($id, null, null, $alternatives);
        }
        return null;
    }
    /**
     * Returns true if the given service has actually been initialized.
     */
    public function initialized(string $id): bool
    {
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }
        if ('service_container' === $id) {
            return false;
        }
        return isset($this->services[$id]);
    }
    public function reset(): void
    {
        $services = $this->services + $this->privates;
        foreach ($services as $service) {
            try {
                if ($service instanceof Reset_Interface) {
                    $service->reset();
                }
            } catch (\Throwable) {
                continue;
            }
        }
        $this->env_cache = $this->services = $this->factories = $this->privates = [];
    }
    /**
     * @internal
     */
    public function reset_env_cache(): void
    {
        $this->env_cache = [];
    }
    /**
     * Gets all service ids.
     *
     * @return string[]
     */
    public function get_service_ids(): array
    {
        return array_map(strval(...), array_unique(array_merge(['service_container'], array_keys($this->file_map), array_keys($this->method_map), array_keys($this->aliases), array_keys($this->services))));
    }
    /**
     * Gets service ids that existed at compile time.
     */
    public function get_removed_ids(): array
    {
        return [];
    }
    /**
     * Camelizes a string.
     */
    public static function camelize(string $id): string
    {
        return strtr(ucwords(strtr($id, ['_' => ' ', '.' => '_ ', '\\' => '_ '])), [' ' => '']);
    }
    /**
     * A string to underscore.
     */
    public static function underscore(string $id): string
    {
        return strtolower((string) preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], ['\1_\2', '\1_\2'], str_replace('_', '.', $id)));
    }
    /**
     * Creates a service by requiring its factory file.
     */
    protected function load(string $file): mixed
    {
        return require $file;
    }
    /**
     * Fetches a variable from the environment.
     *
     * @throws EnvNotFoundException When the environment variable is not found and has no default value
     */
    protected function get_env(string $name): mixed
    {
        if (isset($this->resolving[$env_name = "env({$name})"])) {
            throw new Parameter_Circular_Reference_Exception(array_keys($this->resolving));
        }
        if (isset($this->env_cache[$name]) || \array_key_exists($name, $this->env_cache)) {
            return $this->env_cache[$name];
        }
        if (!$this->has($id = 'container.env_var_processors_locator')) {
            $this->set($id, new Service_Locator([]));
        }
        $this->get_env ??= $this->get_env(...);
        $processors = $this->get($id);
        if (false !== $i = strpos($name, ':')) {
            $prefix = substr($name, 0, $i);
            $local_name = substr($name, 1 + $i);
        } else {
            $prefix = 'string';
            $local_name = $name;
        }
        $processor = $processors->has($prefix) ? $processors->get($prefix) : new Env_Var_Processor($this);
        if (false === $i) {
            $prefix = '';
        }
        $this->resolving[$env_name] = true;
        try {
            return $this->env_cache[$name] = $processor->get_env($prefix, $local_name, $this->get_env);
        } finally {
            unset($this->resolving[$env_name]);
        }
    }
    /**
     * @internal
     */
    final protected function get_service(string|false $registry, string $id, ?string $method, string|bool $load): mixed
    {
        if ('service_container' === $id) {
            return $this;
        }
        if (\is_string($load)) {
            throw new RuntimeException($load);
        }
        if (null === $method) {
            return false !== $registry ? $this->{$registry}[$id] ?? null : null;
        }
        if (false !== $registry) {
            return $this->{$registry}[$id] ??= $load ? $this->load($method) : $this->{$method}($this);
        }
        if (!$load) {
            return $this->{$method}($this);
        }
        return ($factory = $this->factories[$id] ?? $this->factories['service_container'][$id] ?? null) ? $factory($this) : $this->load($method);
    }
    private function __clone()
    {
    }
}