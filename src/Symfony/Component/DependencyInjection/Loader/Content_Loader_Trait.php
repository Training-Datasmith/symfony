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

use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Argument\Abstract_Argument;
use Symfony\Component\Dependency_Injection\Argument\Bound_Argument;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Yaml\Tag\Tagged_Value;
/**
 * @internal
 */
trait Content_Loader_Trait
{
    private const SERVICE_KEYWORDS = ['alias' => 'alias', 'parent' => 'parent', 'class' => 'class', 'shared' => 'shared', 'synthetic' => 'synthetic', 'lazy' => 'lazy', 'public' => 'public', 'abstract' => 'abstract', 'deprecated' => 'deprecated', 'factory' => 'factory', 'file' => 'file', 'arguments' => 'arguments', 'properties' => 'properties', 'configurator' => 'configurator', 'calls' => 'calls', 'tags' => 'tags', 'resource_tags' => 'resource_tags', 'decorates' => 'decorates', 'decoration_inner_name' => 'decoration_inner_name', 'decoration_priority' => 'decoration_priority', 'decoration_on_invalid' => 'decoration_on_invalid', 'decorates_tag' => 'decorates_tag', 'autowire' => 'autowire', 'autoconfigure' => 'autoconfigure', 'bind' => 'bind', 'constructor' => 'constructor'];
    private const PROTOTYPE_KEYWORDS = ['resource' => 'resource', 'namespace' => 'namespace', 'exclude' => 'exclude', 'parent' => 'parent', 'shared' => 'shared', 'lazy' => 'lazy', 'public' => 'public', 'abstract' => 'abstract', 'deprecated' => 'deprecated', 'factory' => 'factory', 'arguments' => 'arguments', 'properties' => 'properties', 'configurator' => 'configurator', 'calls' => 'calls', 'tags' => 'tags', 'resource_tags' => 'resource_tags', 'autowire' => 'autowire', 'autoconfigure' => 'autoconfigure', 'bind' => 'bind', 'constructor' => 'constructor'];
    private const INSTANCEOF_KEYWORDS = ['shared' => 'shared', 'lazy' => 'lazy', 'public' => 'public', 'properties' => 'properties', 'configurator' => 'configurator', 'calls' => 'calls', 'tags' => 'tags', 'resource_tags' => 'resource_tags', 'autowire' => 'autowire', 'bind' => 'bind', 'constructor' => 'constructor'];
    private const DEFAULTS_KEYWORDS = ['public' => 'public', 'tags' => 'tags', 'resource_tags' => 'resource_tags', 'autowire' => 'autowire', 'autoconfigure' => 'autoconfigure', 'bind' => 'bind'];
    private int $anonymous_services_count;
    private string $anonymous_services_suffix;
    private function load_content(array $content, string $path): void
    {
        // imports
        $this->parse_imports($content, $path);
        // parameters
        if (isset($content['parameters'])) {
            if (!\is_array($content['parameters'])) {
                throw new InvalidArgumentException(\sprintf('The "parameters" key should contain an array in "%s".', $path));
            }
            foreach ($content['parameters'] as $key => $value) {
                $this->container->set_parameter($key, $this->resolve_services($value, $path, true));
            }
        }
        // extensions
        $this->load_from_extensions($content);
        // services
        $this->anonymous_services_count = 0;
        $this->anonymous_services_suffix = '~' . Container_Builder::hash($path);
        $this->set_current_dir(\dirname($path));
        try {
            $this->parse_definitions($content, $path);
        } finally {
            $this->instanceof = [];
            $this->register_aliases_for_singly_implemented_interfaces();
        }
    }
    private function parse_imports(array $content, string $file): void
    {
        if (!isset($content['imports'])) {
            return;
        }
        if (!\is_array($content['imports'])) {
            throw new InvalidArgumentException(\sprintf('The "imports" key should contain an array in "%s".', $file));
        }
        $default_directory = \dirname($file);
        foreach ($content['imports'] as $import) {
            if (!\is_array($import)) {
                $import = ['resource' => $import];
            }
            if (!isset($import['resource'])) {
                throw new InvalidArgumentException(\sprintf('An import should provide a resource in "%s".', $file));
            }
            $this->set_current_dir($default_directory);
            $this->import($import['resource'], $import['type'] ?? null, $import['ignore_errors'] ?? false, $file);
        }
    }
    private function parse_definitions(array $content, string $file, bool $track_bindings = true): void
    {
        if (!isset($content['services'])) {
            return;
        }
        if (!\is_array($content['services'])) {
            throw new InvalidArgumentException(\sprintf('The "services" key should contain an array in "%s".', $file));
        }
        if (\array_key_exists('_instanceof', $content['services'])) {
            $instanceof = $content['services']['_instanceof'];
            unset($content['services']['_instanceof']);
            if (!\is_array($instanceof)) {
                throw new InvalidArgumentException(\sprintf('Service "_instanceof" key must be an array, "%s" given in "%s".', get_debug_type($instanceof), $file));
            }
            $this->instanceof = [];
            $this->is_loading_instanceof = true;
            foreach ($instanceof as $id => $service) {
                if (!$service || !\is_array($service)) {
                    throw new InvalidArgumentException(\sprintf('Type definition "%s" must be a non-empty array within "_instanceof" in "%s".', $id, $file));
                }
                if (\is_string($service) && str_starts_with($service, '@')) {
                    throw new InvalidArgumentException(\sprintf('Type definition "%s" cannot be an alias within "_instanceof" in "%s".', $id, $file));
                }
                $this->parse_definition($id, $service, $file, [], false, $track_bindings);
            }
        }
        $this->is_loading_instanceof = false;
        $defaults = $this->parse_defaults($content, $file);
        foreach ($content['services'] as $id => $service) {
            $this->parse_definition($id, $service, $file, $defaults, false, $track_bindings);
        }
    }
    /**
     * @throws InvalidArgumentException
     */
    private function parse_defaults(array &$content, string $file): array
    {
        if (!\array_key_exists('_defaults', $content['services'])) {
            return [];
        }
        $defaults = $content['services']['_defaults'];
        unset($content['services']['_defaults']);
        if (!\is_array($defaults)) {
            throw new InvalidArgumentException(\sprintf('Service "_defaults" key must be an array, "%s" given in "%s".', get_debug_type($defaults), $file));
        }
        foreach ($defaults as $key => $default) {
            if (!isset(self::DEFAULTS_KEYWORDS[$key])) {
                throw new InvalidArgumentException(\sprintf('The configuration key "%s" cannot be used to define a default value in "%s". Allowed keys are "%s".', $key, $file, implode('", "', self::DEFAULTS_KEYWORDS)));
            }
        }
        foreach (['tags', 'resource_tags'] as $type) {
            if (!isset($defaults[$type])) {
                continue;
            }
            if (!\is_array($tags = $defaults[$type])) {
                throw new InvalidArgumentException(\sprintf('Parameter "%s" in "_defaults" must be an array in "%s".', $type, $file));
            }
            foreach ($tags as $tag) {
                if (!\is_array($tag)) {
                    $tag = ['name' => $tag];
                }
                if (1 === \count($tag) && \is_array(current($tag))) {
                    $name = key($tag);
                    $tag = current($tag);
                } else {
                    if (!isset($tag['name'])) {
                        throw new InvalidArgumentException(\sprintf('A "%s" entry in "_defaults" is missing a "name" key in "%s".', $type, $file));
                    }
                    $name = $tag['name'];
                    unset($tag['name']);
                }
                if (!\is_string($name) || '' === $name) {
                    throw new InvalidArgumentException(\sprintf('The tag name in "_defaults" must be a non-empty string in "%s".', $file));
                }
                $this->validate_attributes(\sprintf('Tag "%s", attribute "%s" in "_defaults" must be of a scalar-type in "%s".', $name, '%s', $file), $tag);
            }
        }
        if (isset($defaults['bind'])) {
            if (!\is_array($defaults['bind'])) {
                throw new InvalidArgumentException(\sprintf('Parameter "bind" in "_defaults" must be an array in "%s".', $file));
            }
            foreach ($this->resolve_services($defaults['bind'], $file) as $argument => $value) {
                $defaults['bind'][$argument] = new Bound_Argument($value, true, Bound_Argument::DEFAULTS_BINDING, $file);
            }
        }
        return $defaults;
    }
    private function is_using_short_syntax(array $service): bool
    {
        foreach ($service as $key => $value) {
            if (\is_string($key) && ('' === $key || '$' !== $key[0] && !str_contains($key, '\\'))) {
                return false;
            }
        }
        return true;
    }
    /**
     * @throws InvalidArgumentException When tags are invalid
     */
    private function parse_definition(string $id, array|string|null $service, string $file, array $defaults, bool $return = false, bool $track_bindings = true): Definition|Alias|null
    {
        if (preg_match('/^_[a-zA-Z0-9_]*$/', $id)) {
            throw new InvalidArgumentException(\sprintf('Service names that start with an underscore are reserved. Rename the "%s" service or define it in XML instead.', $id));
        }
        if (\is_string($service) && str_starts_with($service, '@')) {
            $alias = new Alias(substr($service, 1));
            if (isset($defaults['public'])) {
                $alias->set_public($defaults['public']);
            }
            return $return ? $alias : $this->container->set_alias($id, $alias);
        }
        if (\is_array($service) && $this->is_using_short_syntax($service)) {
            $service = ['arguments' => $service];
        }
        if (!\is_array($service ??= [])) {
            throw new InvalidArgumentException(\sprintf('A service definition must be an array or a string starting with "@" but "%s" found for service "%s" in "%s".', get_debug_type($service), $id, $file));
        }
        if (isset($service['stack'])) {
            if (!\is_array($service['stack'])) {
                throw new InvalidArgumentException(\sprintf('A stack must be an array of definitions, "%s" given for service "%s" in "%s".', get_debug_type($service), $id, $file));
            }
            $stack = [];
            foreach ($service['stack'] as $k => $frame) {
                if (\is_array($frame) && 1 === \count($frame) && !isset(self::SERVICE_KEYWORDS[key($frame)])) {
                    $frame = ['class' => key($frame), 'arguments' => current($frame)];
                }
                if (\is_array($frame) && isset($frame['stack'])) {
                    throw new InvalidArgumentException(\sprintf('Service stack "%s" cannot contain another stack in "%s".', $id, $file));
                }
                $definition = $this->parse_definition($id . '" at index "' . $k, $frame, $file, $defaults, true);
                if ($definition instanceof Definition) {
                    $definition->set_instanceof_conditionals($this->instanceof);
                }
                $stack[$k] = $definition;
            }
            if ($diff = array_diff(array_keys($service), ['stack', 'public', 'deprecated'])) {
                throw new InvalidArgumentException(\sprintf('Invalid attribute "%s"; supported ones are "public" and "deprecated" for service "%s" in "%s".', implode('", "', $diff), $id, $file));
            }
            $service = ['parent' => '', 'arguments' => $stack, 'tags' => ['container.stack'], 'public' => $service['public'] ?? null, 'deprecated' => $service['deprecated'] ?? null];
        }
        $definition = isset($service[0]) && $service[0] instanceof Definition ? array_shift($service) : null;
        $return = null === $definition ? $return : true;
        if (isset($service['from_callable'])) {
            foreach (['alias', 'parent', 'synthetic', 'factory', 'file', 'arguments', 'properties', 'configurator', 'calls'] as $key) {
                if (isset($service['factory'])) {
                    throw new InvalidArgumentException(\sprintf('The configuration key "%s" is unsupported for the service "%s" when using "from_callable" in "%s".', $key, $id, $file));
                }
                if (isset($service[$key])) {
                    trigger_deprecation('symfony/dependency-injection', '8.1', 'Configuring the "%s" key for the service "%s" when using "from_callable" is deprecated and will throw an "InvalidArgumentException" in 9.0.', $key, $id);
                }
            }
            if ('Closure' !== $service['class'] ??= 'Closure') {
                $service['lazy'] = true;
            }
            $service['factory'] = ['Closure', 'fromCallable'];
            $service['arguments'] = [$service['from_callable']];
            unset($service['from_callable']);
        }
        $this->check_definition($id, $service, $file);
        if (isset($service['alias'])) {
            $alias = new Alias($service['alias']);
            if (isset($service['public'])) {
                $alias->set_public($service['public']);
            } elseif (isset($defaults['public'])) {
                $alias->set_public($defaults['public']);
            }
            foreach ($service as $key => $value) {
                if (!\in_array($key, ['alias', 'public', 'deprecated'], true)) {
                    throw new InvalidArgumentException(\sprintf('The configuration key "%s" is unsupported for the service "%s" which is defined as an alias in "%s". Allowed configuration keys for service aliases are "alias", "public" and "deprecated".', $key, $id, $file));
                }
                if ('deprecated' === $key) {
                    $deprecation = \is_array($value) ? $value : ['message' => $value];
                    if (!isset($deprecation['package'])) {
                        throw new InvalidArgumentException(\sprintf('Missing attribute "package" of the "deprecated" option in "%s".', $file));
                    }
                    if (!isset($deprecation['version'])) {
                        throw new InvalidArgumentException(\sprintf('Missing attribute "version" of the "deprecated" option in "%s".', $file));
                    }
                    $alias->set_deprecated($deprecation['package'], $deprecation['version'], $deprecation['message'] ?? '');
                }
            }
            return $return ? $alias : $this->container->set_alias($id, $alias);
        }
        $changes = [];
        if (null !== $definition) {
            $changes = $definition->get_changes();
        } elseif ($this->is_loading_instanceof) {
            $definition = new Child_Definition('');
        } elseif (isset($service['parent'])) {
            if ('' !== $service['parent'] && '@' === $service['parent'][0]) {
                throw new InvalidArgumentException(\sprintf('The value of the "parent" option for the "%s" service must be the id of the service without the "@" prefix (replace "%s" with "%s").', $id, $service['parent'], substr((string) $service['parent'], 1)));
            }
            $definition = new Child_Definition($service['parent']);
        } else {
            $definition = new Definition();
        }
        if (isset($defaults['public'])) {
            $definition->set_public($defaults['public']);
        }
        if (isset($defaults['autowire'])) {
            $definition->set_autowired($defaults['autowire']);
        }
        if (isset($defaults['autoconfigure'])) {
            $definition->set_autoconfigured($defaults['autoconfigure']);
        }
        $definition->set_changes($changes);
        if (isset($service['class'])) {
            $definition->set_class($service['class']);
        }
        if (isset($service['shared'])) {
            $definition->set_shared($service['shared']);
        }
        if (isset($service['synthetic'])) {
            $definition->set_synthetic($service['synthetic']);
        }
        if (isset($service['lazy'])) {
            $definition->set_lazy((bool) $service['lazy']);
            if (\is_string($service['lazy'])) {
                $definition->add_tag('proxy', ['interface' => $service['lazy']]);
            }
        }
        if (isset($service['public'])) {
            $definition->set_public($service['public']);
        }
        if (isset($service['abstract'])) {
            $definition->set_abstract($service['abstract']);
        }
        if (isset($service['deprecated'])) {
            $deprecation = \is_array($service['deprecated']) ? $service['deprecated'] : ['message' => $service['deprecated']];
            if (!isset($deprecation['package'])) {
                throw new InvalidArgumentException(\sprintf('Missing attribute "package" of the "deprecated" option in "%s".', $file));
            }
            if (!isset($deprecation['version'])) {
                throw new InvalidArgumentException(\sprintf('Missing attribute "version" of the "deprecated" option in "%s".', $file));
            }
            $definition->set_deprecated($deprecation['package'], $deprecation['version'], $deprecation['message'] ?? '');
        }
        if (isset($service['factory'])) {
            $definition->set_factory($this->parse_callable($service['factory'], 'factory', $id, $file));
        }
        if (isset($service['constructor'])) {
            if (null !== $definition->get_factory()) {
                throw new LogicException(\sprintf('The "%s" service cannot declare a factory as well as a constructor.', $id));
            }
            $definition->set_factory([null, $service['constructor']]);
        }
        if (isset($service['file'])) {
            $definition->set_file($service['file']);
        }
        if (isset($service['arguments'])) {
            $definition->set_arguments($this->resolve_services($service['arguments'], $file));
        }
        if (isset($service['properties'])) {
            $definition->set_properties($this->resolve_services($service['properties'], $file));
        }
        if (isset($service['configurator'])) {
            $definition->set_configurator($this->parse_callable($service['configurator'], 'configurator', $id, $file));
        }
        if (isset($service['calls'])) {
            if (!\is_array($service['calls'])) {
                throw new InvalidArgumentException(\sprintf('Parameter "calls" must be an array for service "%s" in "%s".', $id, $file));
            }
            foreach ($service['calls'] as $k => $call) {
                if (!\is_array($call) && (!\is_string($k) || !$call instanceof Tagged_Value)) {
                    throw new InvalidArgumentException(\sprintf('Invalid method call for service "%s": expected map or array, "%s" given in "%s".', $id, $call instanceof Tagged_Value ? '!' . $call->get_tag() : get_debug_type($call), $file));
                }
                if (\is_string($k)) {
                    throw new InvalidArgumentException(\sprintf('Invalid method call for service "%s", did you forget a leading dash before "%s: ..." in "%s"?', $id, $k, $file));
                }
                if (isset($call['method']) && \is_string($call['method'])) {
                    $method = $call['method'];
                    $args = $call['arguments'] ?? [];
                    $returns_clone = $call['returns_clone'] ?? false;
                } else if (1 === \count($call) && \is_string(key($call))) {
                    $method = key($call);
                    $args = $call[$method];
                    if ($args instanceof Tagged_Value) {
                        if ('returns_clone' !== $args->get_tag()) {
                            throw new InvalidArgumentException(\sprintf('Unsupported tag "!%s", did you mean "!returns_clone" for service "%s" in "%s"?', $args->get_tag(), $id, $file));
                        }
                        $returns_clone = true;
                        $args = $args->get_value();
                    } else {
                        $returns_clone = false;
                    }
                } elseif (empty($call[0])) {
                    throw new InvalidArgumentException(\sprintf('Invalid call for service "%s": the method must be defined as the first index of an array or as the only key of a map in "%s".', $id, $file));
                } else {
                    $method = $call[0];
                    $args = $call[1] ?? [];
                    $returns_clone = $call[2] ?? false;
                }
                if (!\is_array($args)) {
                    throw new InvalidArgumentException(\sprintf('The second parameter for function call "%s" must be an array of its arguments for service "%s" in "%s".', $method, $id, $file));
                }
                $args = $this->resolve_services($args, $file);
                $definition->add_method_call($method, $args, $returns_clone);
            }
        }
        foreach (['tags', 'resource_tags'] as $type) {
            $tags = $service[$type] ?? [];
            if (!\is_array($tags)) {
                throw new InvalidArgumentException(\sprintf('Parameter "%s" must be an array for service "%s" in "%s".', $type, $id, $file));
            }
            if (isset($defaults[$type])) {
                $tags = array_merge($tags, $defaults[$type]);
            }
            foreach ($tags as $tag) {
                if (!\is_array($tag)) {
                    $tag = ['name' => $tag];
                }
                if (1 === \count($tag) && \is_array(current($tag))) {
                    $name = key($tag);
                    $tag = current($tag);
                } else {
                    if (!isset($tag['name'])) {
                        throw new InvalidArgumentException(\sprintf('A "%s" entry is missing a "name" key for service "%s" in "%s".', $type, $id, $file));
                    }
                    $name = $tag['name'];
                    unset($tag['name']);
                }
                if (!\is_string($name) || '' === $name) {
                    throw new InvalidArgumentException(\sprintf('The tag name for service "%s" in "%s" must be a non-empty string.', $id, $file));
                }
                $this->validate_attributes(\sprintf('A "%s" attribute must be of a scalar-type for service "%s", tag "%s", attribute "%s" in "%s".', $id, $name, '%s', $type, $file), $tag);
                match ($type) {
                    'tags' => $definition->add_tag($name, $tag),
                    'resource_tags' => $definition->add_resource_tag($name, $tag),
                };
            }
        }
        if (null !== $decorates = $service['decorates'] ?? null) {
            if ('' !== $decorates && '@' === $decorates[0]) {
                throw new InvalidArgumentException(\sprintf('The value of the "decorates" option for the "%s" service must be the id of the service without the "@" prefix (replace "%s" with "%s").', $id, $service['decorates'], substr($decorates, 1)));
            }
            $decoration_on_invalid = \array_key_exists('decoration_on_invalid', $service) ? $service['decoration_on_invalid'] : 'exception';
            $invalid_behavior = match ($decoration_on_invalid) {
                'exception', Container_Interface::EXCEPTION_ON_INVALID_REFERENCE => Container_Interface::EXCEPTION_ON_INVALID_REFERENCE,
                'ignore', Container_Interface::IGNORE_ON_INVALID_REFERENCE => Container_Interface::IGNORE_ON_INVALID_REFERENCE,
                null, Container_Interface::NULL_ON_INVALID_REFERENCE => Container_Interface::NULL_ON_INVALID_REFERENCE,
                'null' => throw new InvalidArgumentException(\sprintf('Invalid value "%s" for attribute "decoration_on_invalid" on service "%s". Did you mean null (without quotes) in "%s"?', $decoration_on_invalid, $id, $file)),
                default => throw new InvalidArgumentException(\sprintf('Invalid value "%s" for attribute "decoration_on_invalid" on service "%s". Did you mean "exception", "ignore" or "null" in "%s"?', $decoration_on_invalid, $id, $file)),
            };
            $rename_id = $service['decoration_inner_name'] ?? null;
            $priority = $service['decoration_priority'] ?? 0;
            $definition->set_decorated_service($decorates, $rename_id, $priority, $invalid_behavior);
        }
        if (null !== $decorates_tag = $service['decorates_tag'] ?? null) {
            if (isset($service['decorates'])) {
                throw new InvalidArgumentException(\sprintf('A service cannot have both "decorates" and "decorates_tag" attributes on service "%s" in "%s".', $id, $file));
            }
            $priority = $service['decoration_priority'] ?? 0;
            $decoration_on_invalid = \array_key_exists('decoration_on_invalid', $service) ? $service['decoration_on_invalid'] : 'exception';
            $invalid_behavior = match ($decoration_on_invalid) {
                'exception', Container_Interface::EXCEPTION_ON_INVALID_REFERENCE => Container_Interface::EXCEPTION_ON_INVALID_REFERENCE,
                'ignore', Container_Interface::IGNORE_ON_INVALID_REFERENCE => Container_Interface::IGNORE_ON_INVALID_REFERENCE,
                null, Container_Interface::NULL_ON_INVALID_REFERENCE => Container_Interface::NULL_ON_INVALID_REFERENCE,
                'null' => throw new InvalidArgumentException(\sprintf('Invalid value "%s" for attribute "decoration_on_invalid" on service "%s". Did you mean null (without quotes) in "%s"?', $decoration_on_invalid, $id, $file)),
                default => throw new InvalidArgumentException(\sprintf('Invalid value "%s" for attribute "decoration_on_invalid" on service "%s". Did you mean "exception", "ignore" or "null" in "%s"?', $decoration_on_invalid, $id, $file)),
            };
            $tag_attributes = ['decorates_tag' => $decorates_tag, 'priority' => $priority];
            if (Container_Interface::EXCEPTION_ON_INVALID_REFERENCE !== $invalid_behavior) {
                $tag_attributes['on_invalid'] = $invalid_behavior;
            }
            $definition->add_resource_tag('container.tag_decorator', $tag_attributes);
        }
        if (isset($service['autowire'])) {
            $definition->set_autowired($service['autowire']);
        }
        if (isset($defaults['bind']) || isset($service['bind'])) {
            // deep clone, to avoid multiple process of the same instance in the passes
            $bindings = $definition->get_bindings();
            $bindings += isset($defaults['bind']) ? unserialize(serialize($defaults['bind'])) : [];
            if (isset($service['bind'])) {
                if (!\is_array($service['bind'])) {
                    throw new InvalidArgumentException(\sprintf('Parameter "bind" must be an array for service "%s" in "%s".', $id, $file));
                }
                $bindings = array_merge($bindings, $this->resolve_services($service['bind'], $file));
                $binding_type = $this->is_loading_instanceof ? Bound_Argument::INSTANCEOF_BINDING : Bound_Argument::SERVICE_BINDING;
                foreach ($bindings as $argument => $value) {
                    if (!$value instanceof Bound_Argument) {
                        $bindings[$argument] = new Bound_Argument($value, $track_bindings, $binding_type, $file);
                    }
                }
            }
            $definition->set_bindings($bindings);
        }
        if (isset($service['autoconfigure'])) {
            $definition->set_autoconfigured($service['autoconfigure']);
        }
        if (\array_key_exists('namespace', $service) && !\array_key_exists('resource', $service)) {
            throw new InvalidArgumentException(\sprintf('A "resource" attribute must be set when the "namespace" attribute is set for service "%s" in "%s".', $id, $file));
        }
        if ($return) {
            if (\array_key_exists('resource', $service)) {
                throw new InvalidArgumentException(\sprintf('Invalid "resource" attribute found for service "%s" in "%s".', $id, $file));
            }
            return $definition;
        }
        if (\array_key_exists('resource', $service)) {
            if (!\is_string($service['resource'])) {
                throw new InvalidArgumentException(\sprintf('A "resource" attribute must be of type string for service "%s" in "%s".', $id, $file));
            }
            $exclude = $service['exclude'] ?? null;
            $namespace = $service['namespace'] ?? $id;
            $this->register_classes($definition, $namespace, $service['resource'], $exclude, $file);
        } else {
            $this->set_definition($id, $definition);
        }
        return null;
    }
    /**
     * @throws InvalidArgumentException When errors occur
     */
    private function parse_callable(mixed $callable, string $parameter, string $id, string $file): string|array
    {
        if (\is_string($callable)) {
            if (str_starts_with($callable, '@=')) {
                if ('factory' !== $parameter) {
                    throw new InvalidArgumentException(\sprintf('Using expressions in "%s" for the "%s" service is not supported in "%s".', $parameter, $id, $file));
                }
                if (!class_exists(Expression::class)) {
                    throw new \LogicException('The "@=" expression syntax cannot be used without the ExpressionLanguage component. Try running "composer require symfony/expression-language".');
                }
                return $callable;
            }
            if ('' !== $callable && '@' === $callable[0]) {
                if (!str_contains($callable, ':')) {
                    return [$this->resolve_services($callable, $file), '__invoke'];
                }
                throw new InvalidArgumentException(\sprintf('The value of the "%s" option for the "%s" service must be the id of the service without the "@" prefix (replace "%s" with "%s" in "%s").', $parameter, $id, $callable, substr($callable, 1), $file));
            }
            return $callable;
        }
        if (\is_array($callable)) {
            if (isset($callable[0]) && isset($callable[1])) {
                return [$this->resolve_services($callable[0], $file), $callable[1]];
            }
            if ('factory' === $parameter && isset($callable[1]) && null === $callable[0]) {
                return $callable;
            }
            throw new InvalidArgumentException(\sprintf('Parameter "%s" must contain an array with two elements for service "%s" in "%s".', $parameter, $id, $file));
        }
        throw new InvalidArgumentException(\sprintf('Parameter "%s" must be a string or an array for service "%s" in "%s".', $parameter, $id, $file));
    }
    private function resolve_services(mixed $value, string $file, bool $is_parameter = false): mixed
    {
        if ($value instanceof Tagged_Value) {
            $argument = $value->get_value();
            if ('closure' === $value->get_tag()) {
                $argument = $this->resolve_services($argument, $file, $is_parameter);
                return (new Definition('Closure'))->set_factory(['Closure', 'fromCallable'])->add_argument($argument);
            }
            if ('iterator' === $value->get_tag()) {
                if (!\is_array($argument)) {
                    throw new InvalidArgumentException(\sprintf('"!iterator" tag only accepts sequences in "%s".', $file));
                }
                $argument = $this->resolve_services($argument, $file, $is_parameter);
                return new Iterator_Argument($argument);
            }
            if ('service_closure' === $value->get_tag()) {
                $argument = $this->resolve_services($argument, $file, $is_parameter);
                return new Service_Closure_Argument($argument);
            }
            if ('service_locator' === $value->get_tag()) {
                if (!\is_array($argument)) {
                    throw new InvalidArgumentException(\sprintf('"!service_locator" tag only accepts maps in "%s".', $file));
                }
                $argument = $this->resolve_services($argument, $file, $is_parameter);
                return new Service_Locator_Argument($argument);
            }
            if (\in_array($value->get_tag(), ['tagged_iterator', 'tagged_locator'], true)) {
                $for_locator = 'tagged_locator' === $value->get_tag();
                if (\is_array($argument) && isset($argument['tag']) && $argument['tag']) {
                    if ($diff = array_diff(array_keys($argument), $supported_keys = ['tag', 'index_by', 'default_index_method', 'default_priority_method', 'exclude', 'exclude_self'])) {
                        throw new InvalidArgumentException(\sprintf('"!%s" tag contains unsupported key "%s"; supported ones are "%s".', $value->get_tag(), implode('", "', $diff), implode('", "', $supported_keys)));
                    }
                    $tag = $argument['tag'];
                    $index_by = $argument['index_by'] ?? null;
                    $exclude = (array) ($argument['exclude'] ?? null);
                    $exclude_self = $argument['exclude_self'] ?? true;
                    if (\array_key_exists('default_index_method', $argument) || \array_key_exists('default_priority_method', $argument)) {
                        $argument = new Tagged_Iterator_Argument($tag, $index_by, $argument['default_index_method'] ?? null, $for_locator, $argument['default_priority_method'] ?? null, $exclude, $exclude_self);
                    } else {
                        $argument = new Tagged_Iterator_Argument($tag, $index_by, $for_locator, $exclude, $exclude_self);
                    }
                } elseif (\is_string($argument) && $argument) {
                    $argument = new Tagged_Iterator_Argument($argument, null, null, $for_locator);
                } else {
                    throw new InvalidArgumentException(\sprintf('"!%s" tags only accept a non empty string or an array with a key "tag" in "%s".', $value->get_tag(), $file));
                }
                if ($for_locator) {
                    return new Service_Locator_Argument($argument);
                }
                return $argument;
            }
            if ('service' === $value->get_tag()) {
                if ($is_parameter) {
                    throw new InvalidArgumentException(\sprintf('Using an anonymous service in a parameter is not allowed in "%s".', $file));
                }
                $is_loading_instanceof = $this->is_loading_instanceof;
                $this->is_loading_instanceof = false;
                $instanceof = $this->instanceof;
                $this->instanceof = [];
                $id = \sprintf('.%d_%s', ++$this->anonymous_services_count, preg_replace('/^.*\\\\/', '', $argument['class'] ?? '') . $this->anonymous_services_suffix);
                $this->parse_definition($id, $argument, $file, []);
                if (!$this->container->has_definition($id)) {
                    throw new InvalidArgumentException(\sprintf('Creating an alias using the tag "!service" is not allowed in "%s".', $file));
                }
                $this->container->get_definition($id);
                $this->is_loading_instanceof = $is_loading_instanceof;
                $this->instanceof = $instanceof;
                return new Reference($id);
            }
            if ('abstract' === $value->get_tag()) {
                return new Abstract_Argument($value->get_value());
            }
            throw new InvalidArgumentException(\sprintf('Unsupported tag "!%s".', $value->get_tag()));
        }
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->resolve_services($v, $file, $is_parameter);
            }
        } elseif (\is_string($value) && str_starts_with($value, '@=')) {
            if ($is_parameter) {
                throw new InvalidArgumentException(\sprintf('Using expressions in parameters is not allowed in "%s".', $file));
            }
            if (!class_exists(Expression::class)) {
                throw new \LogicException('The "@=" expression syntax cannot be used without the ExpressionLanguage component. Try running "composer require symfony/expression-language".');
            }
            return new Expression(substr($value, 2));
        } elseif (\is_string($value) && str_starts_with($value, '@')) {
            if (str_starts_with($value, '@>')) {
                $argument = $this->resolve_services(substr_replace($value, '', 1, 1), $file, $is_parameter);
                return new Service_Closure_Argument($argument);
            }
            if (str_starts_with($value, '@@')) {
                $value = substr($value, 1);
                $invalid_behavior = null;
            } elseif (str_starts_with($value, '@!')) {
                $value = substr($value, 2);
                $invalid_behavior = Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE;
            } elseif (str_starts_with($value, '@?')) {
                $value = substr($value, 2);
                $invalid_behavior = Container_Interface::IGNORE_ON_INVALID_REFERENCE;
            } else {
                $value = substr($value, 1);
                $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE;
            }
            if (null !== $invalid_behavior) {
                $value = new Reference($value, $invalid_behavior);
            }
        }
        return $value;
    }
    private function load_from_extensions(array $content): void
    {
        foreach ($content as $namespace => $values) {
            if (\in_array($namespace, ['imports', 'parameters', 'services'], true)) {
                continue;
            }
            if (str_starts_with((string) $namespace, 'when@')) {
                $known_envs = $this->container->has_parameter('.container.known_envs') ? array_flip($this->container->get_parameter('.container.known_envs')) : [];
                $this->container->set_parameter('.container.known_envs', array_keys($known_envs + [substr((string) $namespace, 5) => true]));
                continue;
            }
            if (!\is_array($values)) {
                $values = [];
            }
            $this->load_extension_config($namespace, $values);
        }
        $this->load_extension_configs();
    }
    private function check_definition(string $id, array $definition, string $file): void
    {
        if ($this->is_loading_instanceof) {
            $keywords = self::INSTANCEOF_KEYWORDS;
        } elseif (isset($definition['resource']) || isset($definition['namespace'])) {
            $keywords = self::PROTOTYPE_KEYWORDS;
        } else {
            $keywords = self::SERVICE_KEYWORDS;
        }
        foreach ($definition as $key => $value) {
            if (!isset($keywords[$key])) {
                throw new InvalidArgumentException(\sprintf('The configuration key "%s" is unsupported for definition "%s" in "%s". Allowed configuration keys are "%s".', $key, $id, $file, implode('", "', $keywords)));
            }
        }
    }
    private function validate_attributes(string $message, array $attributes, array $path = []): void
    {
        foreach ($attributes as $name => $value) {
            if (\is_array($value)) {
                $this->validate_attributes($message, $value, [...$path, $name]);
            } elseif (!\is_scalar($value ?? '')) {
                $name = implode('.', [...$path, $name]);
                throw new InvalidArgumentException(\sprintf($message, $name));
            }
        }
    }
}