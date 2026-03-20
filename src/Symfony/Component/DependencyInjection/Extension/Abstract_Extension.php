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

use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Config\Definition\Configurator\Definition_Configurator;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Loader\Configurator\Container_Configurator;
/**
 * An Extension that provides configuration hooks.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
abstract class Abstract_Extension extends Extension implements Configurable_Extension_Interface, Prepend_Extension_Interface
{
    use Extension_Trait;
    public function configure(Definition_Configurator $definition): void
    {
    }
    public function prepend_extension(Container_Configurator $container, Container_Builder $builder): void
    {
    }
    public function load_extension(array $config, Container_Configurator $container, Container_Builder $builder): void
    {
    }
    public function get_configuration(array $config, Container_Builder $container): ?Configuration_Interface
    {
        return new Configuration($this, $container, $this->get_alias());
    }
    final public function prepend(Container_Builder $container): void
    {
        $callback = function (Container_Configurator $configurator) use ($container): void {
            $this->prepend_extension($configurator, $container);
        };
        $this->execute_configurator_callback($container, $callback, $this, true);
    }
    final public function load(array $configs, Container_Builder $container): void
    {
        $config = $this->process_configuration($this->get_configuration([], $container), $configs);
        $callback = function (Container_Configurator $configurator) use ($config, $container): void {
            $this->load_extension($config, $configurator, $container);
        };
        $this->execute_configurator_callback($container, $callback, $this);
    }
}