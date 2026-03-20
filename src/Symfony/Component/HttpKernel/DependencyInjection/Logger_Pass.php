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
namespace Symfony\Component\Http_Kernel\Dependency_Injection;

use Psr\Log\Logger_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Kernel\Log\Logger;
/**
 * Registers the default logger if necessary.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Logger_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has(Logger_Interface::class)) {
            $container->set_alias(Logger_Interface::class, 'logger');
        }
        if ($container->has('logger')) {
            return;
        }
        if ($debug = $container->get_parameter('kernel.debug')) {
            $debug = $container->has_parameter('kernel.runtime_mode.web') ? $container->get_parameter('kernel.runtime_mode.web') : !\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
        }
        $container->register('logger', Logger::class)->set_arguments([null, null, null, new Reference(Request_Stack::class), $debug]);
    }
}