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
namespace Symfony\Component\Http_Kernel\Bundle;

use Symfony\Component\Console\Application;
use Symfony\Component\Dependency_Injection\Container;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Extension\Extension_Interface;
/**
 * An implementation of BundleInterface that adds a few conventions for DependencyInjection extensions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Bundle implements Bundle_Interface
{
    protected string $name;
    protected Extension_Interface|false|null $extension = null;
    protected string $path;
    protected ?Container_Interface $container = null;
    private string $namespace;
    public function boot(): void
    {
    }
    public function shutdown(): void
    {
    }
    /**
     * This method can be overridden to register compilation passes,
     * other extensions, ...
     */
    public function build(Container_Builder $container): void
    {
    }
    /**
     * Returns the bundle's container extension.
     *
     * @throws \LogicException
     */
    public function get_container_extension(): ?Extension_Interface
    {
        if (!isset($this->extension)) {
            $extension = $this->create_container_extension();
            if (null !== $extension) {
                // check naming convention
                $basename = preg_replace('/Bundle$/', '', $this->get_name());
                $expected_alias = Container::underscore($basename);
                if ($expected_alias != $extension->get_alias()) {
                    throw new \LogicException(\sprintf('Users will expect the alias of the default extension of a bundle to be the underscored version of the bundle name ("%s"). You can override "Bundle::getContainerExtension()" if you want to use "%s" or another alias.', $expected_alias, $extension->get_alias()));
                }
                $this->extension = $extension;
            } else {
                $this->extension = false;
            }
        }
        return $this->extension ?: null;
    }
    public function get_namespace(): string
    {
        if (!isset($this->namespace)) {
            $this->parse_class_name();
        }
        return $this->namespace;
    }
    public function get_path(): string
    {
        if (!isset($this->path)) {
            $reflected = new \Reflection_Object($this);
            $this->path = \dirname($reflected->get_file_name());
        }
        return $this->path;
    }
    /**
     * Returns the bundle name (the class short name).
     */
    final public function get_name(): string
    {
        if (!isset($this->name)) {
            $this->parse_class_name();
        }
        return $this->name;
    }
    public function register_commands(Application $application): void
    {
    }
    /**
     * Returns the bundle's container extension class.
     */
    protected function get_container_extension_class(): string
    {
        $basename = preg_replace('/Bundle$/', '', $this->get_name());
        return $this->get_namespace() . '\DependencyInjection\\' . $basename . 'Extension';
    }
    /**
     * Creates the bundle's container extension.
     */
    protected function create_container_extension(): ?Extension_Interface
    {
        return class_exists($class = $this->get_container_extension_class()) ? new $class() : null;
    }
    private function parse_class_name(): void
    {
        $pos = strrpos(static::class, '\\');
        $this->namespace = false === $pos ? '' : substr(static::class, 0, $pos);
        $this->name ??= false === $pos ? static::class : substr(static::class, $pos + 1);
    }
    public function set_container(?Container_Interface $container): void
    {
        $this->container = $container;
    }
}