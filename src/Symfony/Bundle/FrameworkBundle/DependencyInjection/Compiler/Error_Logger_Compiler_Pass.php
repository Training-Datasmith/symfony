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
/**
 * @internal
 */
class Error_Logger_Compiler_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('debug.error_handler_configurator')) {
            return;
        }
        $definition = $container->get_definition('debug.error_handler_configurator');
        if ($container->has_definition('monolog.logger.php')) {
            $definition->replace_argument(0, new Reference('monolog.logger.php'));
        }
        if ($container->has_definition('monolog.logger.deprecation')) {
            $definition->replace_argument(5, new Reference('monolog.logger.deprecation'));
        }
    }
}