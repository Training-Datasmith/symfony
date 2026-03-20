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
namespace Symfony\Bundle\Debug_Bundle\Dependency_Injection\Compiler;

use Symfony\Bundle\Web_Profiler_Bundle\Event_Listener\Web_Debug_Toolbar_Listener;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Registers the file link format for the {@link \Symfony\Component\HttpKernel\DataCollector\DumpDataCollector}.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class Dump_Data_Collector_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('data_collector.dump')) {
            return;
        }
        $definition = $container->get_definition('data_collector.dump');
        if (!$container->has('.virtual_request_stack')) {
            $definition->replace_argument(3, new Reference('request_stack'));
        }
        if (!$container->has_parameter('web_profiler.debug_toolbar.mode') || Web_Debug_Toolbar_Listener::DISABLED === $container->get_parameter('web_profiler.debug_toolbar.mode')) {
            $definition->replace_argument(3, null);
        }
        if (!$container->has_parameter('kernel.runtime_mode.web')) {
            $definition->replace_argument(5, null);
        }
    }
}