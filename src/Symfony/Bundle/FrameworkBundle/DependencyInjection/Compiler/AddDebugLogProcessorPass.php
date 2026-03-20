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
use Symfony\Component\Dependency_Injection\Reference;
class Add_Debug_Log_Processor_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('profiler')) {
            return;
        }
        if (!$container->has_definition('monolog.logger_prototype')) {
            return;
        }
        if (!$container->has_definition('debug.log_processor')) {
            return;
        }
        $container->get_definition('monolog.logger_prototype')->set_configurator([new Reference('debug.debug_logger_configurator'), 'pushDebugLogger']);
    }
}