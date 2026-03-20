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

use Symfony\Component\Config\Loader\Loader_Resolver;
use Symfony\Component\Dependency_Injection\Attribute\When;
use Symfony\Component\Dependency_Injection\Attribute\When_Not;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Loader\Configurator\App;
use Symfony\Component\Dependency_Injection\Loader\Configurator\App_Reference;
use Symfony\Component\Dependency_Injection\Loader\Configurator\Container_Configurator;
/**
 * PhpFileLoader loads service definitions from a PHP file.
 *
 * The PHP file is required and the $container variable can be
 * used within the file to change the container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Php_File_Loader extends File_Loader
{
    use Content_Loader_Trait;
    protected bool $auto_register_aliases_for_singly_implemented_interfaces = false;
    public function load(mixed $resource, ?string $type = null): mixed
    {
        // the container and loader variables are exposed to the included file below
        $container = $this->container;
        $loader = $this;
        $path = $this->locator->locate($resource);
        $this->set_current_dir(\dirname($path));
        $this->container->file_exists($path);
        // Force load ContainerConfigurator to make env(), param() etc available.
        class_exists(Container_Configurator::class);
        // Expose AppReference::config() as App::config()
        if (!class_exists(App::class)) {
            class_alias(App_Reference::class, App::class);
        }
        // the closure forbids access to the private scope in the included file
        $load = \Closure::bind(static fn($path, $env) => include $path, null, null);
        $instanceof = $this->instanceof;
        $this->instanceof = [];
        try {
            if (1 === $result = $load($path, $this->env)) {
                $result = null;
            }
            if (\is_object($result) && \is_callable($result)) {
                $this->call_configurator($result, new Container_Configurator($this->container, $this, $this->instanceof, $path, $resource, $this->env), $path);
            } elseif (\is_array($result)) {
                $yaml_loader = new Yaml_File_Loader($this->container, $this->locator, $this->env, $this->prepend);
                $yaml_loader->set_resolver($this->resolver ?? new Loader_Resolver([$this]));
                $result = Container_Configurator::process_value($result);
                ++$this->importing;
                try {
                    $content = array_intersect_key($result, ['imports' => true, 'parameters' => true, 'services' => true]);
                    $this->load_content($content, $path);
                    foreach ($result as $namespace => $config) {
                        if (\in_array($namespace, ['imports', 'parameters', 'services'], true)) {
                            continue;
                        }
                        if (str_starts_with((string) $namespace, 'when@')) {
                            $known_envs = $this->container->has_parameter('.container.known_envs') ? array_flip($this->container->get_parameter('.container.known_envs')) : [];
                            $this->container->set_parameter('.container.known_envs', array_keys($known_envs + [substr((string) $namespace, 5) => true]));
                            continue;
                        }
                        $this->load_extension_config($namespace, $config);
                    }
                    // per-env configuration
                    if ($this->env && isset($result[$when = 'when@' . $this->env])) {
                        if (!\is_array($result[$when])) {
                            throw new InvalidArgumentException(\sprintf('The "%s" key should contain an array in "%s".', $when, $path));
                        }
                        $content = array_intersect_key($result[$when], ['imports' => true, 'parameters' => true, 'services' => true]);
                        $this->load_content($content, $path);
                        foreach ($result[$when] as $namespace => $config) {
                            if (!\in_array($namespace, ['imports', 'parameters', 'services'], true) && !str_starts_with((string) $namespace, 'when@')) {
                                $this->load_extension_config($namespace, $config);
                            }
                        }
                    }
                } finally {
                    --$this->importing;
                }
            }
            $this->load_extension_configs();
        } finally {
            $this->instanceof = $instanceof;
            $this->register_aliases_for_singly_implemented_interfaces();
        }
        return null;
    }
    public function supports(mixed $resource, ?string $type = null): bool
    {
        if (!\is_string($resource)) {
            return false;
        }
        if (null === $type && 'php' === pathinfo($resource, \PATHINFO_EXTENSION)) {
            return true;
        }
        return 'php' === $type;
    }
    /**
     * Resolve the parameters to the $callback and execute it.
     */
    private function call_configurator(callable $callback, Container_Configurator $container_configurator, string $path): void
    {
        $callback = $callback(...);
        $arguments = [];
        $r = new \ReflectionFunction($callback);
        $excluded = true;
        $when_attributes = $r->get_attributes(When::class, \Reflection_Attribute::IS_INSTANCEOF);
        $not_when_attributes = $r->get_attributes(When_Not::class, \Reflection_Attribute::IS_INSTANCEOF);
        if ($when_attributes && $not_when_attributes) {
            throw new LogicException('Using both #[When] and #[WhenNot] attributes on the same target is not allowed.');
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
            return;
        }
        foreach ($r->get_parameters() as $parameter) {
            $reflection_type = $parameter->get_type();
            if (!$reflection_type instanceof \ReflectionNamedType) {
                throw new \InvalidArgumentException(\sprintf('Could not resolve argument "$%s" for "%s". You must typehint it (for example with "%s" or "%s").', $parameter->get_name(), $path, Container_Configurator::class, Container_Builder::class));
            }
            $type = $reflection_type->get_name();
            switch ($type) {
                case Container_Configurator::class:
                    $arguments[] = $container_configurator;
                    break;
                case Container_Builder::class:
                    $arguments[] = $this->container;
                    break;
                case File_Loader::class:
                case self::class:
                    $arguments[] = $this;
                    break;
                case 'string':
                    if (null !== $this->env && 'env' === $parameter->get_name()) {
                        $arguments[] = $this->env;
                        break;
                    }
                // no break
                default:
                    throw new \InvalidArgumentException(\sprintf('Could not resolve argument "%s" for "%s".', $type . ' $' . $parameter->get_name(), $path));
            }
        }
        ++$this->importing;
        try {
            $callback(...$arguments);
        } finally {
            --$this->importing;
        }
    }
}