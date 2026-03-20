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

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Removes empty service-locators registered for ServiceValueResolver.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Remove_Empty_Controller_Argument_Locators_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        $controller_locator = $container->find_definition('argument_resolver.controller_locator');
        $controllers = $controller_locator->get_argument(0);
        foreach ($controllers as $controller => $argument_ref) {
            $argument_locator = $container->get_definition((string) $argument_ref->get_values()[0]);
            if ($argument_locator->get_factory()) {
                $argument_locator = $container->get_definition($argument_locator->get_factory()[0]);
            }
            if (!$argument_locator->get_argument(0)) {
                // remove empty argument locators
                $reason = \sprintf('Removing service-argument resolver for controller "%s": no corresponding services exist for the referenced types.', $controller);
            } else {
                // any methods listed for call-at-instantiation cannot be actions
                $reason = false;
                [$id, $action] = explode('::', (string) $controller);
                if ($container->has_alias($id)) {
                    continue;
                }
                $controller_def = $container->get_definition($id);
                foreach ($controller_def->get_method_calls() as [$method]) {
                    if (0 === strcasecmp($action, (string) $method)) {
                        $reason = \sprintf('Removing method "%s" of service "%s" from controller candidates: the method is called at instantiation, thus cannot be an action.', $action, $id);
                        break;
                    }
                }
                if (!$reason) {
                    // see Symfony\Component\HttpKernel\Controller\ContainerControllerResolver
                    $controllers[$id . ':' . $action] = $argument_ref;
                    if ('__invoke' === $action) {
                        $controllers[$id] = $argument_ref;
                    }
                    continue;
                }
            }
            unset($controllers[$controller]);
            $container->log($this, $reason);
        }
        $controller_locator->replace_argument(0, $controllers);
    }
}