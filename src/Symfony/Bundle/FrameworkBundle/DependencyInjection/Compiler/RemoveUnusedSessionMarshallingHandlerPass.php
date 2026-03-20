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
namespace Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class Remove_Unused_Session_Marshalling_Handler_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('session.marshalling_handler')) {
            return;
        }
        $is_marshaller_decorated = false;
        foreach ($container->get_definitions() as $definition) {
            $decorated = $definition->get_decorated_service();
            if (null !== $decorated && 'session.marshaller' === $decorated[0]) {
                $is_marshaller_decorated = true;
                break;
            }
        }
        if (!$is_marshaller_decorated) {
            $container->remove_definition('session.marshalling_handler');
        }
    }
}