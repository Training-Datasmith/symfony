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
 * Remove private aliases from the container. They were only used to establish
 * dependencies between services, and these dependencies have been resolved in
 * one of the previous passes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Remove_Private_Aliases_Pass implements Compiler_Pass_Interface
{
    /**
     * Removes private aliases from the ContainerBuilder.
     */
    public function process(Container_Builder $container): void
    {
        foreach ($container->get_aliases() as $id => $alias) {
            if ($alias->is_public()) {
                continue;
            }
            $container->remove_alias($id);
            $container->log($this, \sprintf('Removed service "%s"; reason: private alias.', $id));
        }
    }
}