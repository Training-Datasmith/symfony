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

use Symfony\Component\Config\Definition\Configurable_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Loader\Configurator\Container_Configurator;
/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
interface Configurable_Extension_Interface extends Configurable_Interface
{
    /**
     * Allows an extension to prepend the extension configurations.
     */
    public function prepend_extension(Container_Configurator $container, Container_Builder $builder): void;
    /**
     * Loads a specific configuration.
     */
    public function load_extension(array $config, Container_Configurator $container, Container_Builder $builder): void;
}