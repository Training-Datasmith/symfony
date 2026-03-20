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
 * Removes abstract Definitions.
 */
class Remove_Abstract_Definitions_Pass implements Compiler_Pass_Interface
{
    /**
     * Removes abstract definitions from the ContainerBuilder.
     */
    public function process(Container_Builder $container): void
    {
        foreach ($container->get_definitions() as $id => $definition) {
            if ($definition->is_abstract()) {
                $container->resolve_env_placeholders($definition);
                $container->remove_definition($id);
                $container->log($this, \sprintf('Removed service "%s"; reason: abstract.', $id));
            }
        }
    }
}