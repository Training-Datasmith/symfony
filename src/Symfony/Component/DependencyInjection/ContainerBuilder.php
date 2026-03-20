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

use Composer\Autoload\Class_Loader;
use Composer\Installed_Versions;
use Symfony\Component\Config\Resource\Class_Existence_Resource;
use Symfony\Component\Config\Resource\Composer_Resource;
use Symfony\Component\Config\Resource\Directory_Resource;
use Symfony\Component\Config\Resource\File_Existence_Resource;
use Symfony\Component\Config\Resource\File_Resource;
use Symfony\Component\Config\Resource\Glob_Resource;
use Symfony\Component\Config\Resource\Reflection_Class_Resource;
use Symfony\Component\Config\Resource\Resource_Interface;
use Symfony\Component\Dependency_Injection\Argument\Abstract_Argument;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Lazy_Closure;
use Symfony\Component\Dependency_Injection\Argument\Rewindable_Generator;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Attribute\Target;
use Symfony\Component\Dependency_Injection\Compiler\Compiler;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Pass_Config;
use Symfony\Component\Dependency_Injection\Compiler\Resolve_Env_Placeholders_Pass;
use Symfony\Component\Dependency_Injection\Exception\BadMethodCallException;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Exception\Parameter_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Exception\Service_Circular_Reference_Exception;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Extension\Extension_Interface;
use Symfony\Component\Dependency_Injection\Lazy_Proxy\Instantiator\Instantiator_Interface;
use Symfony\Component\Dependency_Injection\Lazy_Proxy\Instantiator\Lazy_Service_Instantiator;
use Symfony\Component\Dependency_Injection\Lazy_Proxy\Instantiator\Real_Service_Instantiator;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Env_Placeholder_Parameter_Bag;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag_Interface;
use Symfony\Component\Error_Handler\Debug_Class_Loader;
use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Expression_Language\Expression_Function_Provider_Interface;
/**
 * ContainerBuilder is a DI container that provides an API to easily describe services.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Container_Builder extends Container implements Tagged_Container_Interface
{
    /**
     * @var array<string, ExtensionInterface>
     */
    private array $extensions = [];
    /**
     * @var array<string, ExtensionInterface>
     */
    private array $extensions_by_ns = [];
    /**
     * @var array<string, Definition>
     */
    private array $definitions = [];
    /**
     * @var array<string, Alias>
     */
    private array $alias_definitions = [];
    /**
     * @var array<string, ResourceInterface>
     */
    private array $resources = [];
    /**
     * @var array<string, array<array<string, mixed>>>
     */
    private array $extension_configs = [];
    private Compiler $compiler;
    private bool $track_resources;
    private Instantiator_Interface $proxy_instantiator;
    private Expression_Language $expression_language;
    /**
     * @var ExpressionFunctionProviderInterface[]
     */
    private array $expression_language_providers = [];
    /**
     * @var string[] with tag names used by findTaggedServiceIds
     */
    private array $used_tags = [];
    /**
     * @var string[][] a map of env var names to their placeholders
     */
    private array $env_placeholders = [];
    /**
     * @var int[] a map of env vars to their resolution counter
     */
    private array $env_counters = [];
    /**
     * @var string[] the list of vendor directories
     */
    private array $vendors;
    /**
     * @var array<string, bool> whether a path is in a vendor directory
     */
    private array $paths_in_vendor = [];
    /**
     * @var array<string, ChildDefinition>
     */
    private array $autoconfigured_instanceof = [];
    /**
     * @var array<string, callable[]>
     */
    private array $autoconfigured_attributes = [];
    /**
     * @var array<string, bool>
     */
    private array $removed_ids = [];
    /**
     * @var array<int, bool>
     */
    private array $removed_binding_ids = [];
    private const INTERNAL_TYPES = ['int' => true, 'float' => true, 'string' => true, 'bool' => true, 'resource' => true, 'object' => true, 'array' => true, 'null' => true, 'callable' => true, 'iterable' => true, 'mixed' => true];
    public function __construct(?Parameter_Bag_Interface $parameter_bag = null)
    {
        parent::__construct($parameter_bag);
        $this->track_resources = interface_exists(Resource_Interface::class);
        $this->set_definition('service_container', (new Definition(Container_Interface::class))->set_synthetic(true)->set_public(true));
    }
    /**
     * @var array<string, \ReflectionClass>
     */
    private array $class_reflectors;
    /**
     * Sets the track resources flag.
     *
     * If you are not using the loaders and therefore don't want
     * to depend on the Config component, set this flag to false.
     */
    public function set_resource_tracking(bool $track): void
    {
        $this->track_resources = $track;
    }
    /**
     * Checks if resources are tracked.
     */
    public function is_tracking_resources(): bool
    {
        return $this->track_resources;
    }
    /**
     * Sets the instantiator to be used when fetching proxies.
     */
    public function set_proxy_instantiator(Instantiator_Interface $proxy_instantiator): void
    {
        $this->proxy_instantiator = $proxy_instantiator;
    }
    public function register_extension(Extension_Interface $extension): void
    {
        $this->extensions[$extension->get_alias()] = $extension;
    }
    /**
     * Returns an extension by alias or namespace.
     *
     * @throws LogicException if the extension is not registered
     */
    public function get_extension(string $name): Extension_Interface
    {
        if (isset($this->extensions[$name])) {
            return $this->extensions[$name];
        }
        if (isset($this->extensions_by_ns[$name])) {
            return $this->extensions_by_ns[$name];
        }
        throw new LogicException(\sprintf('Container extension "%s" is not registered.', $name));
    }
    /**
     * Returns all registered extensions.
     *
     * @return array<string, ExtensionInterface>
     */
    public function get_extensions(): array
    {
        return $this->extensions;
    }
    /**
     * Checks if we have an extension.
     */
    public function has_extension(string $name): bool
    {
        return isset($this->extensions[$name]) || isset($this->extensions_by_ns[$name]);
    }
    /**
     * Returns an array of resources loaded to build this configuration.
     *
     * @return ResourceInterface[]
     */
    public function get_resources(): array
    {
        return array_values($this->resources);
    }
    /**
     * @return $this
     */
    public function add_resource(Resource_Interface $resource): static
    {
        if (!$this->track_resources) {
            return $this;
        }
        if ($resource instanceof Glob_Resource && $this->in_vendors($resource->get_prefix())) {
            return $this;
        }
        if ($resource instanceof File_Existence_Resource && $this->in_vendors($resource->get_resource())) {
            return $this;
        }
        if ($resource instanceof File_Resource && $this->in_vendors($resource->get_resource())) {
            return $this;
        }
        if ($resource instanceof Directory_Resource && $this->in_vendors($resource->get_resource())) {
            return $this;
        }
        if (!$resource instanceof Class_Existence_Resource) {
            $this->resources[(string) $resource] = $resource;
            return $this;
        }
        $class = $resource->get_resource();
        if (!(new Class_Existence_Resource($class, false))->is_fresh(1)) {
            if (!$this->in_vendors((new \ReflectionClass($class))->get_file_name())) {
                $this->resources[$class] = $resource;
            }
            return $this;
        }
        $in_vendor = true;
        foreach (spl_autoload_functions() as $autoloader) {
            if (!\is_array($autoloader)) {
                $in_vendor = false;
                break;
            }
            if ($autoloader[0] instanceof Debug_Class_Loader) {
                $autoloader = $autoloader[0]->get_class_loader();
            }
            if (!\is_array($autoloader) || !$autoloader[0] instanceof Class_Loader) {
                $in_vendor = false;
                break;
            }
            foreach ($autoloader[0]->get_prefixes_psr4() as $prefix => $dirs) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }
                foreach ($dirs as $dir) {
                    if (!$dir = realpath($dir)) {
                        continue;
                    }
                    if (!$in_vendor = $this->in_vendors($dir)) {
                        break 3;
                    }
                }
            }
        }
        if (!$in_vendor) {
            $this->resources[$class] = $resource;
        }
        return $this;
    }
    /**
     * Sets the resources for this configuration.
     *
     * @param array<string, ResourceInterface> $resources
     *
     * @return $this
     */
    public function set_resources(array $resources): static
    {
        if (!$this->track_resources) {
            return $this;
        }
        $this->resources = $resources;
        return $this;
    }
    /**
     * Adds the object class hierarchy as resources.
     *
     * @param object|string $object An object instance or class name
     *
     * @return $this
     */
    public function add_object_resource(object|string $object): static
    {
        if ($this->track_resources) {
            if (\is_object($object)) {
                $object = $object::class;
            }
            $class = $this->class_reflectors[$object] ??= new \ReflectionClass($object);
            foreach ($class->get_interface_names() as $name) {
                $file = ($this->class_reflectors[$name] ??= new \ReflectionClass($name))->get_file_name();
                if (false !== $file && file_exists($file)) {
                    $this->file_exists($file);
                }
            }
            do {
                $file = $class->get_file_name();
                if (false !== $file && file_exists($file)) {
                    $this->file_exists($file);
                }
                foreach ($class->get_trait_names() as $name) {
                    $this->add_object_resource($name);
                }
            } while ($class = $class->get_parent_class());
        }
        return $this;
    }
    /**
     * Retrieves the requested reflection class and registers it for resource tracking.
     *
     * @throws \ReflectionException when a parent class/interface/trait is not found and $throw is true
     *
     * @final
     */
    public function get_reflection_class(?string $class, bool $throw = true): ?\ReflectionClass
    {
        if (!$class = $this->get_parameter_bag()->resolve_value($class)) {
            return null;
        }
        if (isset(self::INTERNAL_TYPES[$class])) {
            return null;
        }
        $resource = $class_reflector = null;
        try {
            if (isset($this->class_reflectors[$class])) {
                $class_reflector = $this->class_reflectors[$class];
            } elseif (class_exists(Class_Existence_Resource::class)) {
                $resource = new Class_Existence_Resource($class, false);
                $class_reflector = $resource->is_fresh(0) ? false : new \ReflectionClass($class);
            } else {
                $class_reflector = class_exists($class) || interface_exists($class, false) ? new \ReflectionClass($class) : false;
            }
        } catch (\Reflection_Exception $e) {
            if ($throw) {
                throw $e;
            }
        }
        if ($this->track_resources) {
            if (!$class_reflector) {
                $this->add_resource($resource ?? new Class_Existence_Resource($class, false));
            } elseif (!$class_reflector->is_internal()) {
                $path = $class_reflector->get_file_name();
                if (!$this->in_vendors($path)) {
                    $this->add_resource(new Reflection_Class_Resource($class_reflector, $this->vendors));
                }
            }
            $this->class_reflectors[$class] = $class_reflector;
        }
        return $class_reflector ?: null;
    }
    /**
     * Checks whether the requested file or directory exists and registers the result for resource tracking.
     *
     * @param string      $path          The file or directory path for which to check the existence
     * @param bool|string $trackContents Whether to track contents of the given resource. If a string is passed,
     *                                   it will be used as pattern for tracking contents of the requested directory
     *
     * @final
     */
    public function file_exists(string $path, bool|string $track_contents = true): bool
    {
        $exists = file_exists($path);
        if (!$this->track_resources || $this->in_vendors($path)) {
            return $exists;
        }
        if (!$exists) {
            $this->add_resource(new File_Existence_Resource($path));
            return false;
        }
        if (is_dir($path)) {
            if ($track_contents) {
                $this->add_resource(new Directory_Resource($path, \is_string($track_contents) ? $track_contents : null));
            } else {
                $this->add_resource(new Glob_Resource($path, '/*', false));
            }
        } elseif ($track_contents) {
            $this->add_resource(new File_Resource($path));
        }
        return true;
    }
    /**
     * Loads the configuration for an extension.
     *
     * @param string                    $extension The extension alias or namespace
     * @param array<string, mixed>|null $values    An array of values that customizes the extension
     *
     * @return $this
     *
     * @throws BadMethodCallException When this ContainerBuilder is compiled
     * @throws \LogicException        if the extension is not registered
     */
    public function load_from_extension(string $extension, ?array $values = null): static
    {
        if ($this->is_compiled()) {
            throw new BadMethodCallException('Cannot load from an extension on a compiled container.');
        }
        $namespace = $this->get_extension($extension)->get_alias();
        $this->extension_configs[$namespace][] = $values ?? [];
        return $this;
    }
    /**
     * Adds a compiler pass.
     *
     * @param string $type     The type of compiler pass
     * @param int    $priority Used to sort the passes
     *
     * @return $this
     */
    public function add_compiler_pass(Compiler_Pass_Interface $pass, string $type = Pass_Config::TYPE_BEFORE_OPTIMIZATION, int $priority = 0): static
    {
        $this->get_compiler()->add_pass($pass, $type, $priority);
        $this->add_object_resource($pass);
        return $this;
    }
    /**
     * Returns the compiler pass config which can then be modified.
     */
    public function get_compiler_pass_config(): Pass_Config
    {
        return $this->get_compiler()->get_pass_config();
    }
    /**
     * Returns the compiler.
     */
    public function get_compiler(): Compiler
    {
        return $this->compiler ??= new Compiler();
    }
    /**
     * Sets a service.
     *
     * @throws BadMethodCallException When this ContainerBuilder is compiled
     */
    public function set(string $id, ?object $service): void
    {
        if ($this->is_compiled() && (isset($this->definitions[$id]) && !$this->definitions[$id]->is_synthetic())) {
            // setting a synthetic service on a compiled container is alright
            throw new BadMethodCallException(\sprintf('Setting service "%s" for an unknown or non-synthetic service definition on a compiled container is not allowed.', $id));
        }
        unset($this->definitions[$id], $this->alias_definitions[$id], $this->removed_ids[$id]);
        parent::set($id, $service);
    }
    /**
     * Removes a service definition.
     */
    public function remove_definition(string $id): void
    {
        if (isset($this->definitions[$id])) {
            unset($this->definitions[$id]);
            if ('.' !== ($id[0] ?? '-')) {
                $this->removed_ids[$id] = true;
            }
        }
    }
    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || isset($this->alias_definitions[$id]) || parent::has($id);
    }
    /**
     * @throws InvalidArgumentException          when no definitions are available
     * @throws ServiceCircularReferenceException When a circular reference is detected
     * @throws ServiceNotFoundException          When the service is not defined
     * @throws \Exception
     *
     * @see Reference
     */
    public function get(string $id, int $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE): ?object
    {
        if ($this->is_compiled() && isset($this->removed_ids[$id])) {
            return Container_Interface::EXCEPTION_ON_INVALID_REFERENCE >= $invalid_behavior ? parent::get($id) : null;
        }
        return $this->do_get($id, $invalid_behavior);
    }
    private function do_get(string $id, int $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE, ?array &$inline_services = null, bool $is_constructor_argument = false): mixed
    {
        if (isset($inline_services[$id])) {
            return $inline_services[$id];
        }
        if (null === $inline_services) {
            $is_constructor_argument = true;
            $inline_services = [];
        }
        try {
            if (Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE === $invalid_behavior) {
                return $this->privates[$id] ?? parent::get($id, $invalid_behavior);
            }
            if (null !== $service = $this->privates[$id] ?? parent::get($id, Container_Interface::NULL_ON_INVALID_REFERENCE)) {
                return $service;
            }
        } catch (Service_Circular_Reference_Exception $e) {
            if ($is_constructor_argument) {
                throw $e;
            }
        }
        if (!isset($this->definitions[$id]) && isset($this->alias_definitions[$id])) {
            $alias = $this->alias_definitions[$id];
            if ($alias->is_deprecated()) {
                $deprecation = $alias->get_deprecation($id);
                trigger_deprecation($deprecation['package'], $deprecation['version'], $deprecation['message']);
            }
            return $this->do_get((string) $alias, $invalid_behavior, $inline_services, $is_constructor_argument);
        }
        try {
            $definition = $this->get_definition($id);
        } catch (Service_Not_Found_Exception $e) {
            if (Container_Interface::EXCEPTION_ON_INVALID_REFERENCE < $invalid_behavior) {
                return null;
            }
            throw $e;
        }
        if ($definition->has_errors() && $e = $definition->get_errors()) {
            throw new RuntimeException(reset($e));
        }
        if ($is_constructor_argument) {
            $this->loading[$id] = true;
        }
        try {
            return $this->create_service($definition, $inline_services, $is_constructor_argument, $id);
        } finally {
            if ($is_constructor_argument) {
                unset($this->loading[$id]);
            }
        }
    }
    /**
     * Merges a ContainerBuilder with the current ContainerBuilder configuration.
     *
     * Service definitions overrides the current defined ones.
     *
     * But for parameters, they are overridden by the current ones. It allows
     * the parameters passed to the container constructor to have precedence
     * over the loaded ones.
     *
     *     $container = new ContainerBuilder(new ParameterBag(['foo' => 'bar']));
     *     $loader = new LoaderXXX($container);
     *     $loader->load('resource_name');
     *     $container->register('foo', 'stdClass');
     *
     * In the above example, even if the loaded resource defines a foo
     * parameter, the value will still be 'bar' as defined in the ContainerBuilder
     * constructor.
     *
     * @throws BadMethodCallException When this ContainerBuilder is compiled
     */
    public function merge(self $container): void
    {
        if ($this->is_compiled()) {
            throw new BadMethodCallException('Cannot merge on a compiled container.');
        }
        foreach ($container->get_definitions() as $id => $definition) {
            if (!$definition->has_tag('container.excluded') || !$this->has($id)) {
                $this->set_definition($id, $definition);
            }
        }
        $this->add_aliases($container->get_aliases());
        $parameter_bag = $this->get_parameter_bag();
        $other_bag = $container->get_parameter_bag();
        $parameter_bag->add($other_bag->all());
        if ($parameter_bag instanceof Parameter_Bag && $other_bag instanceof Parameter_Bag) {
            foreach ($other_bag->all_deprecated() as $name => $deprecated) {
                $parameter_bag->deprecate($name, ...$deprecated);
            }
            foreach ($other_bag->all_non_empty() as $name => $message) {
                $parameter_bag->cannot_be_empty($name, $message);
            }
        }
        if ($this->track_resources) {
            foreach ($container->get_resources() as $resource) {
                $this->add_resource($resource);
            }
        }
        foreach ($this->extensions as $name => $extension) {
            if (!isset($this->extension_configs[$name])) {
                $this->extension_configs[$name] = [];
            }
            $this->extension_configs[$name] = array_merge($this->extension_configs[$name], $container->get_extension_config($name));
        }
        if ($parameter_bag instanceof Env_Placeholder_Parameter_Bag && $other_bag instanceof Env_Placeholder_Parameter_Bag) {
            $env_placeholders = $other_bag->get_env_placeholders();
            $parameter_bag->merge_env_placeholders($other_bag);
        } else {
            $env_placeholders = [];
        }
        foreach ($container->env_counters as $env => $count) {
            if (!$count && !isset($env_placeholders[$env])) {
                continue;
            }
            if (!isset($this->env_counters[$env])) {
                $this->env_counters[$env] = $count;
            } else {
                $this->env_counters[$env] += $count;
            }
        }
        foreach ($container->get_autoconfigured_instanceof() as $interface => $child_definition) {
            if (isset($this->autoconfigured_instanceof[$interface])) {
                throw new InvalidArgumentException(\sprintf('"%s" has already been autoconfigured and merge() does not support merging autoconfiguration for the same class/interface.', $interface));
            }
            $this->autoconfigured_instanceof[$interface] = $child_definition;
        }
        foreach ($container->get_attribute_autoconfigurators() as $attribute => $configurators) {
            $this->autoconfigured_attributes[$attribute] = array_merge($this->autoconfigured_attributes[$attribute] ?? [], $configurators);
        }
    }
    /**
     * Returns the configuration array for the given extension.
     *
     * @return array<array<string, mixed>>
     */
    public function get_extension_config(string $name): array
    {
        if (!isset($this->extension_configs[$name])) {
            $this->extension_configs[$name] = [];
        }
        return $this->extension_configs[$name];
    }
    /**
     * Prepends a config array to the configs of the given extension.
     *
     * @param array<string, mixed> $config
     */
    public function prepend_extension_config(string $name, array $config): void
    {
        if (!isset($this->extension_configs[$name])) {
            $this->extension_configs[$name] = [];
        }
        array_unshift($this->extension_configs[$name], $config);
    }
    /**
     * Deprecates a service container parameter.
     *
     * @throws ParameterNotFoundException if the parameter is not defined
     */
    public function deprecate_parameter(string $name, string $package, string $version, string $message = 'The parameter "%s" is deprecated.'): void
    {
        if (!$this->parameter_bag instanceof Parameter_Bag) {
            throw new BadMethodCallException(\sprintf('The parameter bag must be an instance of "%s" to call "%s".', Parameter_Bag::class, __METHOD__));
        }
        $this->parameter_bag->deprecate($name, $package, $version, $message);
    }
    public function parameter_cannot_be_empty(string $name, string $message): void
    {
        if (!$this->parameter_bag instanceof Parameter_Bag) {
            throw new BadMethodCallException(\sprintf('The parameter bag must be an instance of "%s" to call "%s()".', Parameter_Bag::class, __METHOD__));
        }
        $this->parameter_bag->cannot_be_empty($name, $message);
    }
    /**
     * Compiles the container.
     *
     * This method passes the container to compiler
     * passes whose job is to manipulate and optimize
     * the container.
     *
     * The main compiler passes roughly do four things:
     *
     *  * The extension configurations are merged;
     *  * Parameter values are resolved;
     *  * The parameter bag is frozen;
     *  * Extension loading is disabled.
     *
     * @param bool $resolveEnvPlaceholders Whether %env()% parameters should be resolved at build time using
     *                                     the current env var values (true), or be resolved at runtime based
     *                                     on the environment (false). In general, this should be set to "true"
     *                                     when you want to use the current ContainerBuilder directly, and to
     *                                     "false" when the container is dumped instead.
     */
    public function compile(bool $resolve_env_placeholders = false): void
    {
        $compiler = $this->get_compiler();
        if ($this->track_resources) {
            foreach ($compiler->get_pass_config()->get_passes() as $pass) {
                $this->add_object_resource($pass);
            }
        }
        $bag = $this->get_parameter_bag();
        if ($resolve_env_placeholders && $bag instanceof Env_Placeholder_Parameter_Bag) {
            $compiler->add_pass(new Resolve_Env_Placeholders_Pass(), Pass_Config::TYPE_AFTER_REMOVING, -1000);
        }
        $compiler->compile($this);
        foreach ($this->definitions as $id => $definition) {
            if ($this->track_resources && $definition->is_lazy()) {
                $this->get_reflection_class($definition->get_class());
            }
        }
        $this->extension_configs = [];
        if ($bag instanceof Env_Placeholder_Parameter_Bag) {
            if ($resolve_env_placeholders) {
                $this->parameter_bag = new Parameter_Bag($this->resolve_env_placeholders($this->escape_parameters($bag->all()), true));
            }
            $this->env_placeholders = $bag->get_env_placeholders();
        }
        parent::compile();
        foreach ($this->definitions + $this->alias_definitions as $id => $definition) {
            if ('.' === ($id[0] ?? '-')) {
                continue;
            }
            if ($definition->is_private()) {
                $this->removed_ids[$id] = true;
            }
        }
    }
    public function get_service_ids(): array
    {
        return array_map(strval(...), array_unique(array_merge(array_keys($this->get_definitions()), array_keys($this->alias_definitions), parent::get_service_ids())));
    }
    /**
     * Gets removed service or alias ids.
     *
     * @return array<string, bool>
     */
    public function get_removed_ids(): array
    {
        return $this->removed_ids;
    }
    /**
     * Adds the service aliases.
     *
     * @param array<string, string|Alias> $aliases
     */
    public function add_aliases(array $aliases): void
    {
        foreach ($aliases as $alias => $id) {
            $this->set_alias($alias, $id);
        }
    }
    /**
     * Sets the service aliases.
     *
     * @param array<string, string|Alias> $aliases
     */
    public function set_aliases(array $aliases): void
    {
        $this->alias_definitions = [];
        $this->add_aliases($aliases);
    }
    /**
     * Sets an alias for an existing service.
     *
     * @throws InvalidArgumentException if the id is not a string or an Alias
     * @throws InvalidArgumentException if the alias is for itself
     */
    public function set_alias(string $alias, string|Alias $id): Alias
    {
        if ('' === $alias || '\\' === $alias[-1] || \strlen($alias) !== strcspn($alias, "\x00\r\n'")) {
            throw new InvalidArgumentException(\sprintf('Invalid alias id: "%s".', $alias));
        }
        if (\is_string($id)) {
            $id = new Alias($id);
        }
        if ($alias === (string) $id) {
            throw new InvalidArgumentException(\sprintf('An alias cannot reference itself, got a circular reference on "%s".', $alias));
        }
        unset($this->definitions[$alias], $this->removed_ids[$alias]);
        return $this->alias_definitions[$alias] = $id;
    }
    public function remove_alias(string $alias): void
    {
        if (isset($this->alias_definitions[$alias])) {
            unset($this->alias_definitions[$alias]);
            if ('.' !== ($alias[0] ?? '-')) {
                $this->removed_ids[$alias] = true;
            }
        }
    }
    public function has_alias(string $id): bool
    {
        return isset($this->alias_definitions[$id]);
    }
    /**
     * @return array<string, Alias>
     */
    public function get_aliases(): array
    {
        return $this->alias_definitions;
    }
    /**
     * @throws InvalidArgumentException if the alias does not exist
     */
    public function get_alias(string $id): Alias
    {
        if (!isset($this->alias_definitions[$id])) {
            throw new InvalidArgumentException(\sprintf('The service alias "%s" does not exist.', $id));
        }
        return $this->alias_definitions[$id];
    }
    /**
     * Registers a service definition.
     *
     * This method allows for simple registration of service definition
     * with a fluid interface.
     */
    public function register(string $id, ?string $class = null): Definition
    {
        return $this->set_definition($id, new Definition($class));
    }
    /**
     * This method provides a fluid interface for easily registering a child
     * service definition of the given parent service.
     */
    public function register_child(string $id, string $parent): Child_Definition
    {
        return $this->set_definition($id, new Child_Definition($parent));
    }
    /**
     * Registers an autowired service definition.
     *
     * This method implements a shortcut for using setDefinition() with
     * an autowired definition.
     */
    public function autowire(string $id, ?string $class = null): Definition
    {
        return $this->set_definition($id, (new Definition($class))->set_autowired(true));
    }
    /**
     * Adds the service definitions.
     *
     * @param array<string, Definition> $definitions
     */
    public function add_definitions(array $definitions): void
    {
        foreach ($definitions as $id => $definition) {
            $this->set_definition($id, $definition);
        }
    }
    /**
     * Sets the service definitions.
     *
     * @param array<string, Definition> $definitions
     */
    public function set_definitions(array $definitions): void
    {
        $this->definitions = [];
        $this->add_definitions($definitions);
    }
    /**
     * Gets all service definitions.
     *
     * @return array<string, Definition>
     */
    public function get_definitions(): array
    {
        return $this->definitions;
    }
    /**
     * Sets a service definition.
     *
     * @throws BadMethodCallException When this ContainerBuilder is compiled
     */
    public function set_definition(string $id, Definition $definition): Definition
    {
        if ($this->is_compiled()) {
            throw new BadMethodCallException('Adding definition to a compiled container is not allowed.');
        }
        if ('' === $id || '\\' === $id[-1] || \strlen($id) !== strcspn($id, "\x00\r\n'")) {
            throw new InvalidArgumentException(\sprintf('Invalid service id: "%s".', $id));
        }
        unset($this->alias_definitions[$id], $this->removed_ids[$id]);
        return $this->definitions[$id] = $definition;
    }
    /**
     * Returns true if a service definition exists under the given identifier.
     */
    public function has_definition(string $id): bool
    {
        return isset($this->definitions[$id]);
    }
    /**
     * Gets a service definition.
     *
     * @throws ServiceNotFoundException if the service definition does not exist
     */
    public function get_definition(string $id): Definition
    {
        if (!isset($this->definitions[$id])) {
            throw new Service_Not_Found_Exception($id);
        }
        return $this->definitions[$id];
    }
    /**
     * Gets a service definition by id or alias.
     *
     * The method "unaliases" recursively to return a Definition instance.
     *
     * @throws ServiceNotFoundException if the service definition does not exist
     */
    public function find_definition(string $id): Definition
    {
        $seen = [];
        while (isset($this->alias_definitions[$id])) {
            $id = (string) $this->alias_definitions[$id];
            if (isset($seen[$id])) {
                $seen = array_values($seen);
                $seen = \array_slice($seen, array_search($id, $seen));
                $seen[] = $id;
                throw new Service_Circular_Reference_Exception($id, $seen);
            }
            $seen[$id] = $id;
        }
        return $this->get_definition($id);
    }
    /**
     * Creates a service for a service definition.
     *
     * @throws RuntimeException         When the factory definition is incomplete
     * @throws RuntimeException         When the service is a synthetic service
     * @throws InvalidArgumentException When configure callable is not callable
     */
    private function create_service(Definition $definition, array &$inline_services, bool $is_constructor_argument = false, ?string $id = null, bool|object $try_proxy = true): mixed
    {
        if (null === $id && isset($inline_services[$h = spl_object_hash($definition)])) {
            return $inline_services[$h];
        }
        if ($definition instanceof Child_Definition) {
            throw new RuntimeException(\sprintf('Constructing service "%s" from a parent definition is not supported at build time.', $id));
        }
        if ($definition->is_synthetic()) {
            throw new RuntimeException(\sprintf('You have requested a synthetic service ("%s"). The DIC does not know how to construct this service.', $id));
        }
        if ($definition->is_deprecated()) {
            $deprecation = $definition->get_deprecation($id);
            trigger_deprecation($deprecation['package'], $deprecation['version'], $deprecation['message']);
        }
        $parameter_bag = $this->get_parameter_bag();
        $class = $parameter_bag->resolve_value($definition->get_class()) ?: (['Closure', 'fromCallable'] === $definition->get_factory() ? 'Closure' : null);
        if (['Closure', 'fromCallable'] === $definition->get_factory() && ('Closure' !== $class || $definition->is_lazy())) {
            $callable = $parameter_bag->unescape_value($parameter_bag->resolve_value($definition->get_argument(0)));
            if ($callable instanceof Reference || $callable instanceof Definition) {
                $callable = [$callable, '__invoke'];
            }
            if (\is_array($callable) && ('Closure' !== $class || $callable[0] instanceof Reference || $callable[0] instanceof Definition && !isset($inline_services[spl_object_hash($callable[0])]))) {
                $initializer = function () use ($callable, &$inline_services) {
                    return $this->do_resolve_services($callable[0], $inline_services);
                };
                $proxy = eval('return ' . Lazy_Closure::get_code('$initializer', $callable, $class, $this, $id) . ';');
                $this->share_service($definition, $proxy, $id, $inline_services);
                return $proxy;
            }
        }
        if (true === $try_proxy && $definition->is_lazy() && ['Closure', 'fromCallable'] !== $definition->get_factory() && !$try_proxy = !($proxy = $this->proxy_instantiator ??= new Lazy_Service_Instantiator()) || $proxy instanceof Real_Service_Instantiator) {
            $proxy = $proxy->instantiate_proxy($this, (clone $definition)->set_class($class)->set_tags(($definition->has_tag('proxy') ? ['proxy' => $parameter_bag->resolve_value($definition->get_tag('proxy'))] : []) + $definition->get_tags()), $id, function (bool|object $proxy = false) use ($definition, &$inline_services, $id) {
                return $this->create_service($definition, $inline_services, true, $id, $proxy);
            });
            $this->share_service($definition, $proxy, $id, $inline_services);
            return $proxy;
        }
        if (null !== $definition->get_file()) {
            require_once $parameter_bag->resolve_value($definition->get_file());
        }
        $arguments = $definition->get_arguments();
        if (null !== $factory = $definition->get_factory()) {
            if (\is_array($factory)) {
                $factory = [$this->do_resolve_services($parameter_bag->resolve_value($factory[0]), $inline_services, $is_constructor_argument), $factory[1]];
            } elseif (!\is_string($factory)) {
                throw new RuntimeException(\sprintf('Cannot create service "%s" because of invalid factory.', $id));
            } elseif (str_starts_with($factory, '@=')) {
                $factory = fn(Service_Locator $arguments): mixed => $this->get_expression_language()->evaluate(substr($factory, 2), ['container' => $this, 'args' => $arguments]);
                $arguments = [new Service_Locator_Argument($arguments)];
            }
        }
        $arguments = $this->do_resolve_services($parameter_bag->unescape_value($parameter_bag->resolve_value($arguments)), $inline_services, $is_constructor_argument);
        if (null !== $id && $definition->is_shared() && (isset($this->services[$id]) || isset($this->privates[$id])) && (true === $try_proxy || !$definition->is_lazy())) {
            return $this->services[$id] ?? $this->privates[$id];
        }
        if (!array_is_list($arguments)) {
            $arguments = array_combine(array_map(static fn(int|string $k): ?string => preg_replace('/^.*\$/', '', (string) $k), array_keys($arguments)), $arguments);
        }
        if (null !== $factory) {
            $service = $factory(...$arguments);
            if (!$definition->is_deprecated() && \is_array($factory) && \is_string($factory[0])) {
                $r = $this->class_reflectors[$factory[0]] ??= new \ReflectionClass($factory[0]);
                if (str_contains($r->get_doc_comment() ?: '', "\n * @deprecated ")) {
                    trigger_deprecation('', '', 'The "%s" service relies on the deprecated "%s" factory class. It should either be deprecated or its factory upgraded.', $id, $r->name);
                }
            }
        } else {
            $r = $this->class_reflectors[$class] ??= new \ReflectionClass($class);
            if (\is_object($try_proxy)) {
                if ($r->get_constructor()) {
                    $try_proxy->__construct(...$arguments);
                }
                $service = $try_proxy;
            } else {
                $service = $r->get_constructor() ? $r->new_instance_args($arguments) : $r->new_instance();
            }
            if (!$definition->is_deprecated() && str_contains($r->get_doc_comment() ?: '', "\n * @deprecated ")) {
                trigger_deprecation('', '', 'The "%s" service relies on the deprecated "%s" class. It should either be deprecated or its implementation upgraded.', $id, $r->name);
            }
        }
        $last_wither_index = null;
        foreach ($definition->get_method_calls() as $k => $call) {
            if ($call[2] ?? false) {
                $last_wither_index = $k;
            }
        }
        if (null === $last_wither_index && (true === $try_proxy || !$definition->is_lazy())) {
            // share only if proxying failed, or if not a proxy, and if no withers are found
            $this->share_service($definition, $service, $id, $inline_services);
        }
        $properties = $this->do_resolve_services($parameter_bag->unescape_value($parameter_bag->resolve_value($definition->get_properties())), $inline_services);
        foreach ($properties as $name => $value) {
            $service->{$name} = $value;
        }
        foreach ($definition->get_method_calls() as $k => $call) {
            $service = $this->call_method($service, $call, $inline_services);
            if ($last_wither_index === $k && (true === $try_proxy || !$definition->is_lazy())) {
                // share only if proxying failed, or if not a proxy, and this is the last wither
                $this->share_service($definition, $service, $id, $inline_services);
            }
        }
        if ($callable = $definition->get_configurator()) {
            if (\is_array($callable)) {
                $callable[0] = $parameter_bag->resolve_value($callable[0]);
                if ($callable[0] instanceof Reference) {
                    $callable[0] = $this->do_get((string) $callable[0], $callable[0]->get_invalid_behavior(), $inline_services);
                } elseif ($callable[0] instanceof Definition) {
                    $callable[0] = $this->create_service($callable[0], $inline_services);
                }
            }
            if (!\is_callable($callable)) {
                throw new InvalidArgumentException(\sprintf('The configure callable for class "%s" is not a callable.', get_debug_type($service)));
            }
            $callable($service);
        }
        return $service;
    }
    /**
     * Replaces service references by the real service instance and evaluates expressions.
     *
     * @return mixed The same value with all service references replaced by
     *               the real service instances and all expressions evaluated
     */
    public function resolve_services(mixed $value): mixed
    {
        return $this->do_resolve_services($value);
    }
    private function do_resolve_services(mixed $value, array &$inline_services = [], bool $is_constructor_argument = false): mixed
    {
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->do_resolve_services($v, $inline_services, $is_constructor_argument);
            }
        } elseif ($value instanceof Service_Closure_Argument) {
            $reference = $value->get_values()[0];
            $value = fn(): mixed => $this->resolve_services($reference);
        } elseif ($value instanceof Iterator_Argument) {
            $value = new Rewindable_Generator(function () use ($value, &$inline_services) {
                foreach ($value->get_values() as $k => $v) {
                    foreach (self::get_service_conditionals($v) as $s) {
                        if (!$this->has($s)) {
                            continue 2;
                        }
                    }
                    foreach (self::get_initialized_conditionals($v) as $s) {
                        if (!$this->do_get($s, Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE, $inline_services)) {
                            continue 2;
                        }
                    }
                    yield $k => $this->do_resolve_services($v, $inline_services);
                }
            }, function () use ($value): int {
                $count = 0;
                foreach ($value->get_values() as $v) {
                    foreach (self::get_service_conditionals($v) as $s) {
                        if (!$this->has($s)) {
                            continue 2;
                        }
                    }
                    foreach (self::get_initialized_conditionals($v) as $s) {
                        if (!$this->do_get($s, Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE)) {
                            continue 2;
                        }
                    }
                    ++$count;
                }
                return $count;
            });
        } elseif ($value instanceof Service_Locator_Argument) {
            $refs = $types = [];
            foreach ($value->get_values() as $k => $v) {
                $refs[$k] = [$v, null];
                $types[$k] = $v instanceof Typed_Reference ? $v->get_type() : '?';
            }
            $value = new Service_Locator($this->resolve_services(...), $refs, $types);
        } elseif ($value instanceof Reference) {
            $value = $this->do_get((string) $value, $value->get_invalid_behavior(), $inline_services, $is_constructor_argument);
        } elseif ($value instanceof Definition) {
            $value = $this->create_service($value, $inline_services, $is_constructor_argument);
        } elseif ($value instanceof Parameter) {
            $value = $this->get_parameter((string) $value);
        } elseif ($value instanceof Expression) {
            $value = $this->get_expression_language()->evaluate($value, ['container' => $this]);
        } elseif ($value instanceof Abstract_Argument) {
            throw new RuntimeException($value->get_text_with_context());
        }
        return $value;
    }
    /**
     * Returns service ids for a given tag.
     *
     * Example:
     *
     *     $container->register('foo')->addTag('my.tag', ['hello' => 'world']);
     *
     *     $serviceIds = $container->findTaggedServiceIds('my.tag');
     *     foreach ($serviceIds as $serviceId => $tags) {
     *         foreach ($tags as $tag) {
     *             echo $tag['hello'];
     *         }
     *     }
     *
     * @return array<string, array> An array of tags with the tagged service as key, holding a list of attribute arrays
     */
    public function find_tagged_service_ids(string $name, bool $throw_on_abstract = false): array
    {
        $this->used_tags[] = $name;
        $tags = [];
        foreach ($this->get_definitions() as $id => $definition) {
            if ($definition->has_tag($name) && !$definition->has_tag('container.excluded')) {
                if ($throw_on_abstract && $definition->is_abstract()) {
                    throw new InvalidArgumentException(\sprintf('The service "%s" tagged "%s" must not be abstract.', $id, $name));
                }
                $tags[$id] = $definition->get_tag($name);
            }
        }
        return $tags;
    }
    /**
     * Returns service ids for a given tag, asserting they have the "container.excluded" tag.
     *
     *  Example:
     *
     *      $container->register('foo')->addResourceTag('my.tag', ['hello' => 'world'])
     *
     *      $serviceIds = $container->findTaggedResourceIds('my.tag');
     *      foreach ($serviceIds as $serviceId => $tags) {
     *          foreach ($tags as $tag) {
     *              echo $tag['hello'];
     *          }
     *      }
     *
     * @return array<string, array> An array of tags with the tagged service as key, holding a list of attribute arrays
     */
    public function find_tagged_resource_ids(string $tag_name, bool $throw_on_abstract = true): array
    {
        $this->used_tags[] = $tag_name;
        $tags = [];
        foreach ($this->get_definitions() as $id => $definition) {
            if (!$definition->has_tag($tag_name)) {
                continue;
            }
            if (!$definition->has_tag('container.excluded')) {
                throw new InvalidArgumentException(\sprintf('The resource "%s" tagged "%s" is missing the "container.excluded" tag.', $id, $tag_name));
            }
            $class = $this->parameter_bag->resolve_value($definition->get_class());
            if (!$class || $throw_on_abstract && $definition->is_abstract()) {
                throw new InvalidArgumentException(\sprintf('The resource "%s" tagged "%s" must have a class and not be abstract.', $id, $tag_name));
            }
            if ($definition->get_class() !== $class) {
                $definition->set_class($class);
            }
            $tags[$id] = $definition->get_tag($tag_name);
        }
        return $tags;
    }
    /**
     * Returns all tags the defined services use.
     *
     * @return string[]
     */
    public function find_tags(): array
    {
        $tags = [];
        foreach ($this->get_definitions() as $definition) {
            $tags[] = array_keys($definition->get_tags());
        }
        return array_unique(array_merge([], ...$tags));
    }
    /**
     * Returns all tags not queried by findTaggedServiceIds.
     *
     * @return string[]
     */
    public function find_unused_tags(): array
    {
        return array_values(array_diff($this->find_tags(), $this->used_tags));
    }
    public function add_expression_language_provider(Expression_Function_Provider_Interface $provider): void
    {
        $this->expression_language_providers[] = $provider;
    }
    /**
     * @return ExpressionFunctionProviderInterface[]
     */
    public function get_expression_language_providers(): array
    {
        return $this->expression_language_providers;
    }
    /**
     * Returns a ChildDefinition that will be used for autoconfiguring the interface/class.
     */
    public function register_for_autoconfiguration(string $interface): Child_Definition
    {
        if (!isset($this->autoconfigured_instanceof[$interface])) {
            $this->autoconfigured_instanceof[$interface] = new Child_Definition('');
        }
        return $this->autoconfigured_instanceof[$interface];
    }
    /**
     * Registers an attribute that will be used for autoconfiguring annotated classes.
     *
     * The third argument passed to the callable is the reflector of the
     * class/method/property/parameter that the attribute targets. Using one or many of
     * \ReflectionClass|\ReflectionMethod|\ReflectionProperty|\ReflectionParameter as a type-hint
     * for this argument allows filtering which attributes should be passed to the callable.
     *
     * @template T
     *
     * @param class-string<T>                                $attributeClass
     * @param callable(ChildDefinition, T, \Reflector): void $configurator
     */
    public function register_attribute_for_autoconfiguration(string $attribute_class, callable $configurator): void
    {
        $this->autoconfigured_attributes[$attribute_class][] = $configurator;
    }
    /**
     * Registers an autowiring alias that only binds to a specific argument name.
     *
     * The argument name is derived from $name if provided (from $id otherwise)
     * using camel case: "foo.bar" or "foo_bar" creates an alias bound to
     * "$fooBar"-named arguments with $type as type-hint. Such arguments will
     * receive the service $id when autowiring is used.
     */
    public function register_alias_for_argument(string $id, string $type, ?string $name = null, ?string $target = null): Alias
    {
        $parsed_name = (new Target($name ??= $id))->get_parsed_name();
        $target ??= $name;
        if (!preg_match('/^[a-zA-Z_\x7f-\xff]/', $parsed_name)) {
            if ($id !== $name) {
                $id = \sprintf(' for service "%s"', $id);
            }
            throw new InvalidArgumentException(\sprintf('Invalid argument name "%s"' . $id . ': the first character must be a letter.', $name));
        }
        if ($parsed_name !== $target) {
            $this->set_alias('.' . $type . ' $' . $target, $type . ' $' . $parsed_name);
        }
        return $this->set_alias($type . ' $' . $parsed_name, $id);
    }
    /**
     * Returns an array of ChildDefinition[] keyed by interface.
     *
     * @return array<string, ChildDefinition>
     */
    public function get_autoconfigured_instanceof(): array
    {
        return $this->autoconfigured_instanceof;
    }
    /**
     * @return array<class-string, callable[]>
     */
    public function get_attribute_autoconfigurators(): array
    {
        return $this->autoconfigured_attributes;
    }
    /**
     * Resolves env parameter placeholders in a string or an array.
     *
     * @param string|true|null $format    A sprintf() format returning the replacement for each env var name or
     *                                    null to resolve back to the original "%env(VAR)%" format or
     *                                    true to resolve to the actual values of the referenced env vars
     * @param array            &$usedEnvs Env vars found while resolving are added to this array
     *
     * @return mixed The value with env parameters resolved if a string or an array is passed
     */
    public function resolve_env_placeholders(mixed $value, string|bool|null $format = null, ?array &$used_envs = null): mixed
    {
        $bag = $this->get_parameter_bag();
        if (true === $format ??= '%%env(%s)%%') {
            $value = $bag->resolve_value($value);
        }
        if ($value instanceof Definition) {
            $value = (array) $value;
        }
        if (\is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[\is_string($k) ? $this->resolve_env_placeholders($k, $format, $used_envs) : $k] = $this->resolve_env_placeholders($v, $format, $used_envs);
            }
            return $result;
        }
        if (!\is_string($value) || 38 > \strlen($value) || false === stripos($value, 'env_')) {
            return $value;
        }
        $env_placeholders = $bag instanceof Env_Placeholder_Parameter_Bag ? $bag->get_env_placeholders() : $this->env_placeholders;
        $completed = false;
        preg_match_all('/env_[a-f0-9]{16}_\w+_[a-f0-9]{32}/Ui', $value, $matches);
        $used_placeholders = array_flip($matches[0]);
        foreach ($env_placeholders as $env => $placeholders) {
            foreach ($placeholders as $placeholder) {
                if (isset($used_placeholders[$placeholder])) {
                    if (true === $format) {
                        $resolved = $bag->escape_value($this->get_env($env));
                    } else {
                        $resolved = \sprintf($format, $env);
                    }
                    if ($placeholder === $value) {
                        $value = $resolved;
                        $completed = true;
                    } else {
                        if (!\is_string($resolved) && !is_numeric($resolved)) {
                            throw new RuntimeException(\sprintf('A string value must be composed of strings and/or numbers, but found parameter "env(%s)" of type "%s" inside string value "%s".', $env, get_debug_type($resolved), $this->resolve_env_placeholders($value)));
                        }
                        $value = str_ireplace($placeholder, $resolved, $value);
                    }
                    $used_envs[$env] = $env;
                    $this->env_counters[$env] = isset($this->env_counters[$env]) ? 1 + $this->env_counters[$env] : 1;
                    if ($completed) {
                        break 2;
                    }
                }
            }
        }
        return $value;
    }
    /**
     * Get statistics about env usage.
     *
     * @return int[] The number of time each env vars has been resolved
     */
    public function get_env_counters(): array
    {
        $bag = $this->get_parameter_bag();
        $env_placeholders = $bag instanceof Env_Placeholder_Parameter_Bag ? $bag->get_env_placeholders() : $this->env_placeholders;
        foreach ($env_placeholders as $env => $placeholders) {
            if (!isset($this->env_counters[$env])) {
                $this->env_counters[$env] = 0;
            }
        }
        return $this->env_counters;
    }
    /**
     * @final
     */
    public function log(Compiler_Pass_Interface $pass, string $message): void
    {
        $this->get_compiler()->log($pass, $this->resolve_env_placeholders($message));
    }
    /**
     * Checks whether a class is available and will remain available in the "no-dev" mode of Composer.
     *
     * When parent packages are provided and if any of them is in dev-only mode,
     * the class will be considered available even if it is also in dev-only mode.
     *
     * @throws \LogicException If dependencies have been installed with Composer 1
     */
    final public static function will_be_available(string $package, string $class, array $parent_packages): bool
    {
        if (!class_exists(Installed_Versions::class)) {
            throw new \LogicException(\sprintf('Calling "%s" when dependencies have been installed with Composer 1 is not supported. Consider upgrading to Composer 2.', __METHOD__));
        }
        if (!class_exists($class) && !interface_exists($class, false) && !trait_exists($class, false)) {
            return false;
        }
        if (!Installed_Versions::is_installed($package) || Installed_Versions::is_installed($package, false)) {
            return true;
        }
        // the package is installed but in dev-mode only, check if this applies to one of the parent packages too
        $root_package = Installed_Versions::get_root_package()['name'] ?? '';
        if ('symfony/symfony' === $root_package) {
            return true;
        }
        foreach ($parent_packages as $parent_package) {
            if ($root_package === $parent_package || Installed_Versions::is_installed($parent_package) && !Installed_Versions::is_installed($parent_package, false)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Gets removed binding ids.
     *
     * @return array<int, bool>
     *
     * @internal
     */
    public function get_removed_binding_ids(): array
    {
        return $this->removed_binding_ids;
    }
    /**
     * Removes bindings for a service.
     *
     * @internal
     */
    public function remove_bindings(string $id): void
    {
        if ($this->has_definition($id)) {
            foreach ($this->get_definition($id)->get_bindings() as $binding) {
                [, $binding_id] = $binding->get_values();
                $this->removed_binding_ids[(int) $binding_id] = true;
            }
        }
    }
    /**
     * @return string[]
     *
     * @internal
     */
    public static function get_service_conditionals(mixed $value): array
    {
        $services = [];
        if (\is_array($value)) {
            foreach ($value as $v) {
                $services = array_unique(array_merge($services, self::get_service_conditionals($v)));
            }
        } elseif ($value instanceof Reference && Container_Interface::IGNORE_ON_INVALID_REFERENCE === $value->get_invalid_behavior()) {
            $services[] = (string) $value;
        }
        return $services;
    }
    /**
     * @return string[]
     *
     * @internal
     */
    public static function get_initialized_conditionals(mixed $value): array
    {
        $services = [];
        if (\is_array($value)) {
            foreach ($value as $v) {
                $services = array_unique(array_merge($services, self::get_initialized_conditionals($v)));
            }
        } elseif ($value instanceof Reference && Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE === $value->get_invalid_behavior()) {
            $services[] = (string) $value;
        }
        return $services;
    }
    /**
     * Computes a reasonably unique hash of a serializable value.
     */
    public static function hash(mixed $value): string
    {
        $hash = substr(base64_encode(hash('xxh128', serialize($value), true)), 0, 7);
        return str_replace(['/', '+'], ['.', '_'], $hash);
    }
    protected function get_env(string $name): mixed
    {
        $value = parent::get_env($name);
        $bag = $this->get_parameter_bag();
        if (!\is_string($value) || !$bag instanceof Env_Placeholder_Parameter_Bag) {
            return $value;
        }
        $env_placeholders = $bag->get_env_placeholders();
        if (isset($env_placeholders[$name][$value])) {
            $bag = new Parameter_Bag($bag->all());
            return $bag->unescape_value($bag->get("env({$name})"));
        }
        foreach ($env_placeholders as $env => $placeholders) {
            if (isset($placeholders[$value])) {
                return $this->get_env($env);
            }
        }
        $this->resolving["env({$name})"] = true;
        try {
            return $bag->unescape_value($this->resolve_env_placeholders($bag->escape_value($value), true));
        } finally {
            unset($this->resolving["env({$name})"]);
        }
    }
    private function call_method(object $service, array $call, array &$inline_services): mixed
    {
        foreach (self::get_service_conditionals($call[1]) as $s) {
            if (!$this->has($s)) {
                return $service;
            }
        }
        foreach (self::get_initialized_conditionals($call[1]) as $s) {
            if (!$this->do_get($s, Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE, $inline_services)) {
                return $service;
            }
        }
        $result = $service->{$call[0]}(...$this->do_resolve_services($this->get_parameter_bag()->unescape_value($this->get_parameter_bag()->resolve_value($call[1])), $inline_services));
        return empty($call[2]) ? $service : $result;
    }
    private function share_service(Definition $definition, mixed $service, ?string $id, array &$inline_services): void
    {
        $inline_services[$id ?? spl_object_hash($definition)] = $service;
        if (null !== $id && $definition->is_shared()) {
            if ($definition->is_private() && $this->is_compiled()) {
                $this->privates[$id] = $service;
            } else {
                $this->services[$id] = $service;
            }
            unset($this->loading[$id]);
        }
    }
    private function get_expression_language(): Expression_Language
    {
        if (!isset($this->expression_language)) {
            if (!class_exists(Expression::class)) {
                throw new LogicException('Expressions cannot be used without the ExpressionLanguage component. Try running "composer require symfony/expression-language".');
            }
            $this->expression_language = new Expression_Language(null, $this->expression_language_providers, null, $this->get_env(...));
        }
        return $this->expression_language;
    }
    private function in_vendors(string $path): bool
    {
        $path = is_file($path) ? \dirname($path) : $path;
        if (isset($this->paths_in_vendor[$path])) {
            return $this->paths_in_vendor[$path];
        }
        $this->vendors ??= (new Composer_Resource())->get_vendors();
        $path = realpath($path) ?: $path;
        if (isset($this->paths_in_vendor[$path])) {
            return $this->paths_in_vendor[$path];
        }
        foreach ($this->vendors as $vendor) {
            if (\in_array($path[\strlen((string) $vendor)] ?? '', ['/', \DIRECTORY_SEPARATOR], true) && str_starts_with($path, (string) $vendor)) {
                $this->paths_in_vendor[$vendor . \DIRECTORY_SEPARATOR . 'composer'] = false;
                $this->add_resource(new File_Resource($vendor . \DIRECTORY_SEPARATOR . 'composer' . \DIRECTORY_SEPARATOR . 'installed.json'));
                $this->paths_in_vendor[$vendor . \DIRECTORY_SEPARATOR . 'composer'] = true;
                return $this->paths_in_vendor[$path] = true;
            }
        }
        return $this->paths_in_vendor[$path] = false;
    }
    private function escape_parameters(array $parameters): array
    {
        $params = [];
        foreach ($parameters as $k => $v) {
            $params[$k] = match (true) {
                \is_array($v) => $this->escape_parameters($v),
                \is_string($v) => str_replace('%', '%%', $v),
                default => $v,
            };
        }
        return $params;
    }
}