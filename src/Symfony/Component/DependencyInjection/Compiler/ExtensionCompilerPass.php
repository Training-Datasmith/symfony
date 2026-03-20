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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * A pass to automatically process extensions if they implement
 * CompilerPassInterface.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class Extension_Compiler_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        foreach ($container->get_extensions() as $extension) {
            if (!$extension instanceof Compiler_Pass_Interface) {
                continue;
            }
            $extension->process($container);
        }
    }
}