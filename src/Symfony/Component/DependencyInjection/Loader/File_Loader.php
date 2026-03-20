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
namespace Symfony\Component\Dependency_Injection\Loader;

use Symfony\Component\Config\Exception\File_Locator_File_Not_Found_Exception;
use Symfony\Component\Config\Exception\Loader_Load_Exception;
use Symfony\Component\Config\File_Locator_Interface;
use Symfony\Component\Config\Loader\File_Loader as BaseFileLoader;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Resource\Glob_Resource;
use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Attribute\As_Alias;
use Symfony\Component\Dependency_Injection\Attribute\Exclude;
use Symfony\Component\Dependency_Injection\Attribute\When;
use Symfony\Component\Dependency_Injection\Attribute\When_Not;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Compiler\Register_Autoconfigure_Attributes_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
/**
 * FileLoader is the abstract class used by all built-in loaders that are file based.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class File_Loader extends Base_File_Loader
{
    public const ANONYMOUS_ID_REGEXP = '/^\.\d+_[^~]*+~[._a-zA-Z\d]{7}$/';
    protected bool $is_loading_instanceof = false;
    protected array $instanceof = [];
    protected array $interfaces = [];
    protected array $singly_implemented = [];
    /** @var array<string, Alias> */
    protected array $aliases = [];
    /** @var array<string, string> */
    protected array $aliased_targets = [];
    protected bool $auto_register_aliases_for_singly_implemented_interfaces = true;
    protected array $extension_configs = [];
    protected int $importing = 0;
    /**
     * @param bool $prepend Whether to prepend extension config instead of appending them
     */
    public function __construct(protected Container_Builder $container, File_Locator_Interface $locator, ?string $env = null, protected bool $prepend = false)
    {
        parent::__construct($locator, $env);
    }
    /**
     * @param bool|string $ignoreErrors Whether errors should be ignored; pass "not_found" to ignore only when the loaded resource is not found
     */
    public function import(mixed $resource, ?string $type = null, bool|string $ignore_errors = false, ?string $source_resource = null, $exclude = null): mixed
    {
        $args = \func_get_args();
        if ($ignore_not_found = 'not_found' === $ignore_errors) {
            $args[2] = false;
        } elseif (!\is_bool($ignore_errors)) {
            throw new \TypeError(\sprintf('Invalid argument $ignoreErrors provided to "%s::import()": boolean or "not_found" expected, "%s" given.', static::class, get_debug_type($ignore_errors)));
        }
        ++$this->importing;
        try {
            return parent::import(...$args);
        } catch (Loader_Load_Exception $e) {
            if (!$ignore_not_found || !($prev = $e->get_previous()) instanceof File_Locator_File_Not_Found_Exception) {
                throw $e;
            }
            foreach ($prev->get_trace() as $frame) {
                if ('import' === ($frame['function'] ?? null) && is_a($frame['class'] ?? '', Loader::class, true)) {
                    break;
                }
            }
            if (__FILE__ !== $frame['file']) {
                throw $e;
            }
        } finally {
            --$this->importing;
            $this->load_extension_configs();
        }
        return null;
    }
    /**
     * Registers a set of classes as services using PSR-4 for discovery.
     *
     * @param Definition           $prototype A definition to use as template
     * @param string               $namespace The namespace prefix of classes in the scanned directory
     * @param string               $resource  The directory to look for classes, glob-patterns allowed
     * @param string|string[]|null $exclude   A globbed path of files to exclude or an array of globbed paths of files to exclude
     * @param string|null          $source    The path to the file that defines the auto-discovery rule
     */
    public function register_classes(Definition $prototype, string $namespace, string $resource, string|array|null $exclude = null, ?string $source = null): void
    {
        if (!str_ends_with($namespace, '\\')) {
            throw new InvalidArgumentException(\sprintf('Namespace prefix must end with a "\": "%s".', $namespace));
        }
        if (!preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+\\\\)++$/', $namespace)) {
            throw new InvalidArgumentException(\sprintf('Namespace is not a valid PSR-4 prefix: "%s".', $namespace));
        }
        // This can happen with YAML files
        if (\is_array($exclude) && \in_array(null, $exclude, true)) {
            throw new InvalidArgumentException('The exclude list must not contain a "null" value.');
        }
        // This can happen with XML files
        if (\is_array($exclude) && \in_array('', $exclude, true)) {
            throw new InvalidArgumentException('The exclude list must not contain an empty value.');
        }
        $autoconfigure_attributes = new Register_Autoconfigure_Attributes_Pass();
        $autoconfigure_attributes = $autoconfigure_attributes->accept($prototype) ? $autoconfigure_attributes : null;
        $classes = $this->find_classes($namespace, $resource, (array) $exclude, $source);
        $get_prototype = static fn(): \Symfony\Component\Dependency_Injection\Definition => clone $prototype;
        $serialized = serialize($prototype);
        // avoid deep cloning if no definitions are nested
        if (strpos($serialized, 'O:48:"Symfony\Component\DependencyInjection\Definition"', 55) || strpos($serialized, 'O:53:"Symfony\Component\DependencyInjection\ChildDefinition"', 55)) {
            // prepare for deep cloning
            foreach (['Arguments', 'Properties', 'MethodCalls', 'Configurator', 'Factory', 'Bindings'] as $key) {
                $serialized = serialize($prototype->{'get' . $key}());
                if (strpos($serialized, 'O:48:"Symfony\Component\DependencyInjection\Definition"') || strpos($serialized, 'O:53:"Symfony\Component\DependencyInjection\ChildDefinition"')) {
                    $get_prototype = static fn(): \Symfony\Component\Dependency_Injection\Definition => $get_prototype()->{'set' . $key}(unserialize($serialized));
                }
            }
        }
        unset($serialized);
        foreach ($classes as $class => $error_message) {
            if (null === $error_message && $autoconfigure_attributes) {
                $r = $this->container->get_reflection_class($class);
                if ($r->get_attributes(Exclude::class)[0] ?? null) {
                    $this->add_container_excluded_tag($class, $source);
                    continue;
                }
                if ($this->env) {
                    $excluded = true;
                    $when_attributes = $r->get_attributes(When::class, \Reflection_Attribute::IS_INSTANCEOF);
                    $not_when_attributes = $r->get_attributes(When_Not::class, \Reflection_Attribute::IS_INSTANCEOF);
                    if ($when_attributes && $not_when_attributes) {
                        throw new LogicException(\sprintf('The "%s" class cannot have both #[When] and #[WhenNot] attributes.', $class));
                    }
                    if (!$when_attributes && !$not_when_attributes) {
                        $excluded = false;
                    }
                    foreach ($when_attributes as $attribute) {
                        if ($this->env === $attribute->new_instance()->env) {
                            $excluded = false;
                            break;
                        }
                    }
                    foreach ($not_when_attributes as $attribute) {
                        if ($excluded = $this->env === $attribute->new_instance()->env) {
                            break;
                        }
                    }
                    if ($excluded) {
                        $this->add_container_excluded_tag($class, $source);
                        continue;
                    }
                }
            }
            $r = null === $error_message ? $this->container->get_reflection_class($class) : null;
            $abstract = $r?->is_abstract() || $r?->is_interface() ? '.abstract.' : '';
            $this->set_definition($abstract . $class, $definition = $get_prototype());
            $definition->set_class($class);
            if (null !== $error_message) {
                $definition->add_error($error_message);
                continue;
            }
            if ($abstract) {
                if ($r->is_interface()) {
                    $this->interfaces[] = $class;
                }
                $autoconfigure_attributes?->process_class($this->container, $r);
                $definition->set_abstract(true)->add_tag('container.excluded', ['source' => 'because the class is abstract']);
                continue;
            }
            $interfaces = [];
            foreach (class_implements($class, false) as $interface) {
                $this->singly_implemented[$interface] = ($this->singly_implemented[$interface] ?? $class) !== $class ? false : $class;
                $interfaces[] = $interface;
            }
            if (!$autoconfigure_attributes) {
                continue;
            }
            $r = $this->container->get_reflection_class($class);
            $default_alias = 1 === \count($interfaces) ? $interfaces[0] : null;
            foreach ($r->get_attributes(As_Alias::class, \Reflection_Attribute::IS_INSTANCEOF) as $attr) {
                /** @var AsAlias $attribute */
                $attribute = $attr->new_instance();
                $alias = $attribute->id ?? $default_alias;
                $public = $attribute->public;
                if (null === $alias) {
                    throw new LogicException(\sprintf('Alias cannot be automatically determined for class "%s". If you have used the #[AsAlias] attribute with a class implementing multiple interfaces, add the interface you want to alias to the first parameter of #[AsAlias].', $class));
                }
                if ($attribute->when && !\in_array($this->env, $attribute->when, true)) {
                    continue;
                }
                if (!$attribute->target) {
                    if (isset($this->aliases[$alias])) {
                        throw new LogicException(\sprintf('The "%s" alias has already been defined with the #[AsAlias] attribute in "%s".', $alias, $this->aliases[$alias]));
                    }
                    $this->aliases[$alias] = new Alias($class, $public);
                    continue;
                }
                if ($public) {
                    throw new LogicException(\sprintf('#[AsAlias] attributes with a target cannot be public in "%s".', $class));
                }
                $this->container->register_alias_for_argument($class, $alias, $attribute->target);
                $alias = array_key_last($this->container->get_aliases());
                if (isset($this->aliased_targets[$alias])) {
                    throw new LogicException(\sprintf('The "%s" alias has already been defined with the #[AsAlias] attribute in "%s".', $alias, $class));
                }
                $this->aliased_targets[$alias] = $class;
            }
        }
        foreach ($this->aliases as $alias => $alias_definition) {
            $this->container->set_alias($alias, $alias_definition);
        }
        if ($this->auto_register_aliases_for_singly_implemented_interfaces) {
            $this->register_aliases_for_singly_implemented_interfaces();
        }
    }
    public function register_aliases_for_singly_implemented_interfaces(): void
    {
        foreach ($this->interfaces as $interface) {
            if (!empty($this->singly_implemented[$interface]) && !isset($this->aliases[$interface]) && !$this->container->has($interface)) {
                $this->container->set_alias($interface, $this->singly_implemented[$interface]);
            }
        }
        $this->interfaces = $this->singly_implemented = $this->aliases = $this->aliased_targets = [];
    }
    final protected function load_extension_config(string $namespace, array $config, string $file = '?'): void
    {
        if (!$this->prepend) {
            $this->container->load_from_extension($namespace, $config);
            return;
        }
        if ($this->importing) {
            if (!isset($this->extension_configs[$namespace])) {
                $this->extension_configs[$namespace] = [];
            }
            array_unshift($this->extension_configs[$namespace], $config);
            return;
        }
        $this->container->prepend_extension_config($namespace, $config);
    }
    final protected function load_extension_configs(): void
    {
        if ($this->importing || !$this->extension_configs) {
            return;
        }
        foreach ($this->extension_configs as $namespace => $configs) {
            foreach ($configs as $config) {
                $this->container->prepend_extension_config($namespace, $config);
            }
        }
        $this->extension_configs = [];
    }
    /**
     * Registers a definition in the container with its instanceof-conditionals.
     */
    protected function set_definition(string $id, Definition $definition): void
    {
        $this->container->remove_bindings($id);
        foreach ($definition->get_tag('container.error') as $error) {
            if (isset($error['message'])) {
                $definition->add_error($error['message']);
            }
        }
        if ($this->is_loading_instanceof) {
            if (!$definition instanceof Child_Definition) {
                throw new InvalidArgumentException(\sprintf('Invalid type definition "%s": ChildDefinition expected, "%s" given.', $id, get_debug_type($definition)));
            }
            $this->instanceof[$id] = $definition;
        } else {
            $this->container->set_definition($id, $definition->set_instanceof_conditionals($this->instanceof));
        }
    }
    private function find_classes(string $namespace, string $pattern, array $exclude_patterns, ?string $source): array
    {
        $parameter_bag = $this->container->get_parameter_bag();
        $exclude_paths = [];
        $exclude_prefix = null;
        $exclude_patterns = $parameter_bag->unescape_value($parameter_bag->resolve_value($exclude_patterns));
        foreach ($exclude_patterns as $exclude_pattern) {
            foreach ($this->glob($exclude_pattern, true, $resource, true, true) as $path => $info) {
                $exclude_prefix ??= $resource->get_prefix();
                // normalize Windows slashes and remove trailing slashes
                $exclude_paths[rtrim(str_replace('\\', '/', $path), '/')] = true;
            }
        }
        $pattern = $parameter_bag->unescape_value($parameter_bag->resolve_value($pattern));
        $classes = [];
        $prefix_len = null;
        foreach ($this->glob($pattern, true, $resource, false, false, $exclude_paths) as $path => $info) {
            if (null === $prefix_len) {
                $prefix_len = \strlen($resource->get_prefix());
                if ($exclude_prefix && !str_starts_with($exclude_prefix, $resource->get_prefix())) {
                    throw new InvalidArgumentException(\sprintf('Invalid "exclude" pattern when importing classes for "%s": make sure your "exclude" pattern (%s) is a subset of the "resource" pattern (%s).', $namespace, $exclude_pattern, $pattern));
                }
            }
            if (isset($exclude_paths[str_replace('\\', '/', $path)])) {
                continue;
            }
            if (!str_ends_with((string) $path, '.php')) {
                continue;
            }
            $class = $namespace . ltrim(str_replace('/', '\\', substr((string) $path, $prefix_len, -4)), '\\');
            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+$/', $class)) {
                continue;
            }
            try {
                $r = $this->container->get_reflection_class($class);
            } catch (\Reflection_Exception $e) {
                $classes[$class] = $e->get_message();
                continue;
            }
            // check to make sure the expected class exists
            if (!$r) {
                throw new InvalidArgumentException(\sprintf('Expected to find class "%s" in file "%s" while importing services from resource "%s", but it was not found! Check the namespace prefix used with the resource.', $class, $path, $pattern));
            }
            if (!$r->is_trait()) {
                $classes[$class] = null;
            }
        }
        // track only for new & removed files
        if ($resource instanceof Glob_Resource) {
            $this->container->add_resource($resource);
        } else {
            foreach ($resource as $path) {
                $this->container->file_exists($path, false);
            }
        }
        if (null !== $prefix_len) {
            foreach ($exclude_paths as $path => $_) {
                $class = $namespace . ltrim(str_replace('/', '\\', substr($path, $prefix_len, str_ends_with($path, '.php') ? -4 : null)), '\\');
                $this->add_container_excluded_tag($class, $source);
            }
        }
        return $classes;
    }
    private function add_container_excluded_tag(string $class, ?string $source): void
    {
        if ($this->container->has($class)) {
            return;
        }
        static $attributes = [];
        if (null !== $source && !isset($attributes[$source])) {
            $attributes[$source] = ['source' => \sprintf('in "%s/%s"', basename(\dirname($source)), basename($source))];
        }
        $this->container->register($class, $class)->set_abstract(true)->add_tag('container.excluded', null !== $source ? $attributes[$source] : []);
    }
}