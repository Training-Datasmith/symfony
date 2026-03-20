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
namespace Symfony\Component\Dependency_Injection\Lazy_Proxy\Instantiator;

use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
/**
 * Noop proxy instantiator - produces the real service instead of a proxy instance.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class Real_Service_Instantiator implements Instantiator_Interface
{
    /**
     * @return object The real service instance
     */
    public function instantiate_proxy(Container_Interface $container, Definition $definition, string $id, callable $real_instantiator): object
    {
        return $real_instantiator();
    }
}