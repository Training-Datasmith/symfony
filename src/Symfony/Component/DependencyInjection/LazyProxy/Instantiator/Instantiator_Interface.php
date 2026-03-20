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
 * Lazy proxy instantiator, capable of instantiating a proxy given a container, the
 * service definitions and a callback that produces the real service instance.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
interface Instantiator_Interface
{
    /**
     * Instantiates a proxy object.
     *
     * @param string                                        $id               Identifier of the requested service
     * @param (callable(): object)|(callable(object): void) $realInstantiator A callback that creates or initializes the real service instance:
     *                                                                        - For direct instantiation or value-holder proxies: Called without arguments and returns the service object.
     *                                                                        - For ghost object proxies (using PHP's lazy objects): Called with the proxy as argument, initializes it in place and returns void.
     */
    public function instantiate_proxy(Container_Interface $container, Definition $definition, string $id, callable $real_instantiator): object;
}