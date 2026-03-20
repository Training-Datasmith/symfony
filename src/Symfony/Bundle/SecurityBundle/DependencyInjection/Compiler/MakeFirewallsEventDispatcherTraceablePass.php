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
namespace Symfony\Bundle\Security_Bundle\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Event_Dispatcher\Debug\Traceable_Event_Dispatcher;
/**
 * @author Mathieu Lechat <mathieu.lechat@les-tilleuls.coop>
 */
class Make_Firewalls_Event_Dispatcher_Traceable_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has('event_dispatcher') || !$container->has_parameter('security.firewalls')) {
            return;
        }
        if (!$container->get_parameter('kernel.debug') || !$container->has('debug.stopwatch')) {
            return;
        }
        $dispatchers_id = [];
        foreach ($container->get_parameter('security.firewalls') as $firewall_name) {
            $dispatcher_id = 'security.event_dispatcher.' . $firewall_name;
            if (!$container->has($dispatcher_id)) {
                continue;
            }
            $dispatchers_id[$dispatcher_id] = 'debug.' . $dispatcher_id;
            $container->register($dispatchers_id[$dispatcher_id], Traceable_Event_Dispatcher::class)->set_decorated_service($dispatcher_id)->set_arguments([new Reference($dispatchers_id[$dispatcher_id] . '.inner'), new Reference('debug.stopwatch'), new Reference('logger', Container_Interface::NULL_ON_INVALID_REFERENCE), new Reference('request_stack', Container_Interface::NULL_ON_INVALID_REFERENCE)])->add_tag('monolog.logger', ['channel' => 'event'])->add_tag('kernel.reset', ['method' => 'reset']);
        }
        foreach (['kernel.event_subscriber', 'kernel.event_listener'] as $tag_name) {
            foreach ($container->find_tagged_service_ids($tag_name) as $tagged_service_id => $tags) {
                $tagged_service_definition = $container->find_definition($tagged_service_id);
                $tagged_service_definition->clear_tag($tag_name);
                foreach ($tags as $tag) {
                    if ($dispatcher_id = $tag['dispatcher'] ?? null) {
                        $tag['dispatcher'] = $dispatchers_id[$dispatcher_id] ?? $dispatcher_id;
                    }
                    $tagged_service_definition->add_tag($tag_name, $tag);
                }
            }
        }
    }
}