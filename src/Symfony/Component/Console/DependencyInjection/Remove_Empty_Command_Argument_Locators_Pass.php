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
namespace Symfony\Component\Console\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Removes empty service-locators registered for ServiceValueResolver for commands.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class Remove_Empty_Command_Argument_Locators_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('console.argument_resolver.service')) {
            return;
        }
        $service_resolver_def = $container->get_definition('console.argument_resolver.service');
        $command_locator_ref = $service_resolver_def->get_argument(0);
        if (!$command_locator_ref) {
            return;
        }
        $command_locator = $container->get_definition((string) $command_locator_ref);
        if ($command_locator->get_factory()) {
            $command_locator = $container->get_definition($command_locator->get_factory()[0]);
        }
        $commands = $command_locator->get_argument(0);
        foreach ($commands as $command_name => $argument_ref) {
            $argument_locator = $container->get_definition((string) $argument_ref->get_values()[0]);
            if ($argument_locator->get_factory()) {
                $argument_locator = $container->get_definition($argument_locator->get_factory()[0]);
            }
            if (!$argument_locator->get_argument(0)) {
                $reason = \sprintf('Removing service-argument resolver for command "%s": no corresponding services exist for the referenced types.', $command_name);
                unset($commands[$command_name]);
                $container->log($this, $reason);
            }
        }
        $command_locator->replace_argument(0, $commands);
    }
}