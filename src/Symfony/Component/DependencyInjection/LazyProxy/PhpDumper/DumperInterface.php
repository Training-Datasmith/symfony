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
namespace Symfony\Component\Dependency_Injection\Lazy_Proxy\Php_Dumper;

use Symfony\Component\Dependency_Injection\Definition;
/**
 * Lazy proxy dumper capable of generating the instantiation logic PHP code for proxied services.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
interface Dumper_Interface
{
    /**
     * Inspects whether the given definitions should produce proxy instantiation logic in the dumped container.
     *
     * @param bool|null &$asGhostObject Set to true after the call if the proxy is a ghost object
     */
    public function is_proxy_candidate(Definition $definition, ?bool &$as_ghost_object = null, ?string $id = null): bool;
    /**
     * Generates the code to be used to instantiate a proxy in the dumped factory code.
     */
    public function get_proxy_factory_code(Definition $definition, string $id, string $factory_code): string;
    /**
     * Generates the code for the lazy proxy.
     */
    public function get_proxy_code(Definition $definition, ?string $id = null): string;
}