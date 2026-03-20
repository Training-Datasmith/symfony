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
namespace Symfony\Bundle\Framework_Bundle\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
class Virtual_Request_Stack_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if ($container->has('.virtual_request_stack')) {
            return;
        }
        if ($container->has_definition('debug.event_dispatcher')) {
            $container->get_definition('debug.event_dispatcher')->replace_argument(3, new Reference('request_stack', Container_Builder::NULL_ON_INVALID_REFERENCE));
        }
        if ($container->has_definition('debug.log_processor')) {
            $container->get_definition('debug.log_processor')->replace_argument(0, new Reference('request_stack'));
        }
    }
}