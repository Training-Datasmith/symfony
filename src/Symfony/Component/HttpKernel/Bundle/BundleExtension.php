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

use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Configuration_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Extension\Configurable_Extension_Interface;
use Symfony\Component\Dependency_Injection\Extension\Extension;
use Symfony\Component\Dependency_Injection\Extension\Extension_Trait;
use Symfony\Component\Dependency_Injection\Extension\Prepend_Extension_Interface;
use Symfony\Component\Dependency_Injection\Loader\Configurator\Container_Configurator;
/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
class Bundle_Extension extends Extension implements Prepend_Extension_Interface
{
    use Extension_Trait;
    public function __construct(private Configurable_Extension_Interface $subject, private string $alias)
    {
    }
    public function get_configuration(array $config, Container_Builder $container): ?Configuration_Interface
    {
        return new Configuration($this->subject, $container, $this->get_alias());
    }
    public function get_alias(): string
    {
        return $this->alias;
    }
    public function prepend(Container_Builder $container): void
    {
        $callback = function (Container_Configurator $configurator) use ($container): void {
            $this->subject->prepend_extension($configurator, $container);
        };
        $this->execute_configurator_callback($container, $callback, $this->subject, true);
    }
    public function load(array $configs, Container_Builder $container): void
    {
        $config = $this->process_configuration($this->get_configuration([], $container), $configs);
        $callback = function (Container_Configurator $configurator) use ($config, $container): void {
            $this->subject->load_extension($config, $configurator, $container);
        };
        $this->execute_configurator_callback($container, $callback, $this->subject);
    }
}