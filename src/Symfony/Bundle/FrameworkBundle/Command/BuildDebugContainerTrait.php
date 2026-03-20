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
namespace Symfony\Bundle\Framework_Bundle\Command;

use Symfony\Component\Config\Config_Cache;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Http_Kernel\Kernel_Interface;
/**
 * @internal
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait Build_Debug_Container_Trait
{
    protected Container_Builder $container;
    /**
     * Loads the ContainerBuilder from the cache.
     *
     * @throws \LogicException
     */
    protected function get_container_builder(Kernel_Interface $kernel): Container_Builder
    {
        if (isset($this->container)) {
            return $this->container;
        }
        $file = $kernel->is_debug() ? $kernel->get_container()->get_parameter('debug.container.dump') : false;
        if (!$file || !(new Config_Cache($file, true))->is_fresh()) {
            $build_container = \Closure::bind(function () {
                $this->initialize_bundles();
                return $this->build_container();
            }, $kernel, $kernel::class);
            $container = $build_container();
            $container->get_compiler_pass_config()->set_removing_passes([]);
            $container->get_compiler_pass_config()->set_after_removing_passes([]);
            $container->compile();
        } else {
            $build_container = \Closure::bind(function () {
                $container_builder = $this->get_container_builder();
                $this->prepare_container($container_builder);
                return $container_builder;
            }, $kernel, $kernel::class);
            $container = $build_container();
            $dumped_container = unserialize(file_get_contents(substr_replace($file, '.ser', -4)));
            $container->set_definitions($dumped_container->get_definitions());
            $container->set_aliases($dumped_container->get_aliases());
            $parameter_bag = $container->get_parameter_bag();
            $parameter_bag->clear();
            $parameter_bag->add($dumped_container->get_parameter_bag()->all());
        }
        return $this->container = $container;
    }
}