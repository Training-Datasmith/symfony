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
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * Collects attribute listeners and registers them for ControllerAttributesListener.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Controller_Attributes_Listener_Pass implements Compiler_Pass_Interface
{
    private const ATTRIBUTE_EVENTS = [Kernel_Events::CONTROLLER, Kernel_Events::CONTROLLER_ARGUMENTS, Kernel_Events::VIEW, Kernel_Events::RESPONSE, Kernel_Events::EXCEPTION, Kernel_Events::FINISH_REQUEST];
    public function process(Container_Builder $container): void
    {
        if (!$container->has('event_dispatcher') || !$container->has_definition('kernel.controller_attributes_listener')) {
            return;
        }
        $dispatcher_definition = $container->find_definition('event_dispatcher');
        $attributes_with_listeners = [];
        foreach ($dispatcher_definition->get_method_calls() as [$method, $arguments]) {
            if ('addListener' !== $method) {
                continue;
            }
            if (!\is_string($event_name = $arguments[0] ?? null)) {
                continue;
            }
            foreach (self::ATTRIBUTE_EVENTS as $kernel_event) {
                if ('.' === ($event_name[\strlen($kernel_event)] ?? null) && str_starts_with($event_name, $kernel_event)) {
                    $attributes_with_listeners[$kernel_event][substr($event_name, \strlen($kernel_event) + 1)] = true;
                    break;
                }
            }
        }
        $container->get_definition('kernel.controller_attributes_listener')->replace_argument(0, $attributes_with_listeners);
    }
}