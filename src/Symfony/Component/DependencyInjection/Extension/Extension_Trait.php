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
namespace Symfony\Component\Dependency_Injection\Extension;

use Symfony\Component\Config\File_Locator;
use Symfony\Component\Config\Loader\Delegating_Loader;
use Symfony\Component\Config\Loader\Loader_Resolver;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Loader\Closure_Loader;
use Symfony\Component\Dependency_Injection\Loader\Configurator\Container_Configurator;
use Symfony\Component\Dependency_Injection\Loader\Directory_Loader;
use Symfony\Component\Dependency_Injection\Loader\Glob_File_Loader;
use Symfony\Component\Dependency_Injection\Loader\Ini_File_Loader;
use Symfony\Component\Dependency_Injection\Loader\Php_File_Loader;
use Symfony\Component\Dependency_Injection\Loader\Yaml_File_Loader;
/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
trait Extension_Trait
{
    private function execute_configurator_callback(Container_Builder $container, \Closure $callback, Configurable_Extension_Interface $subject, bool $prepend = false): void
    {
        $env = $container->get_parameter('kernel.environment');
        $loader = $this->create_container_loader($container, $env, $prepend);
        $file = (new \Reflection_Object($subject))->get_file_name();
        $bundle_loader = $loader->get_resolver()->resolve($file);
        if (!$bundle_loader instanceof Php_File_Loader) {
            throw new \LogicException('Unable to create the ContainerConfigurator.');
        }
        $bundle_loader->set_current_dir(\dirname($file));
        $instanceof =& \Closure::bind(fn&(): array => $this->instanceof, $bundle_loader, $bundle_loader)();
        try {
            $callback(new Container_Configurator($container, $bundle_loader, $instanceof, $file, $file, $env));
        } finally {
            $instanceof = [];
            $bundle_loader->register_aliases_for_singly_implemented_interfaces();
        }
    }
    private function create_container_loader(Container_Builder $container, string $env, bool $prepend): Delegating_Loader
    {
        $locator = new File_Locator();
        $resolver = new Loader_Resolver([new Yaml_File_Loader($container, $locator, $env, $prepend), new Ini_File_Loader($container, $locator, $env), new Php_File_Loader($container, $locator, $env, $prepend), new Glob_File_Loader($container, $locator, $env), new Directory_Loader($container, $locator, $env), new Closure_Loader($container, $env)]);
        return new Delegating_Loader($resolver);
    }
}