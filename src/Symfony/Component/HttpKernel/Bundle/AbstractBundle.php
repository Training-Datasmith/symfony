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

use Symfony\Component\Config\Definition\Configurator\Definition_Configurator;
use Symfony\Component\Dependency_Injection\Container;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Extension\Configurable_Extension_Interface;
use Symfony\Component\Dependency_Injection\Extension\Extension_Interface;
use Symfony\Component\Dependency_Injection\Loader\Configurator\Container_Configurator;
/**
 * A Bundle that provides configuration hooks.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
abstract class Abstract_Bundle extends Bundle implements Configurable_Extension_Interface
{
    protected string $extension_alias = '';
    public function configure(Definition_Configurator $definition): void
    {
    }
    public function prepend_extension(Container_Configurator $container, Container_Builder $builder): void
    {
    }
    public function load_extension(array $config, Container_Configurator $container, Container_Builder $builder): void
    {
    }
    public function get_container_extension(): ?Extension_Interface
    {
        if ('' === $this->extension_alias) {
            $this->extension_alias = Container::underscore(preg_replace('/Bundle$/', '', $this->get_name()));
        }
        return $this->extension ??= new Bundle_Extension($this, $this->extension_alias);
    }
    public function get_path(): string
    {
        if (!isset($this->path)) {
            $reflected = new \Reflection_Object($this);
            // assume the modern directory structure by default
            $this->path = \dirname($reflected->get_file_name(), 2);
        }
        return $this->path;
    }
}