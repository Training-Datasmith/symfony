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
use Symfony\Component\Security\Core\Authentication_Events;
use Symfony\Component\Security\Core\Event\Authentication_Success_Event;
use Symfony\Component\Security\Http\Event\Authentication_Token_Created_Event;
use Symfony\Component\Security\Http\Event\Check_Passport_Event;
use Symfony\Component\Security\Http\Event\Interactive_Login_Event;
use Symfony\Component\Security\Http\Event\Login_Failure_Event;
use Symfony\Component\Security\Http\Event\Login_Success_Event;
use Symfony\Component\Security\Http\Event\Logout_Event;
use Symfony\Component\Security\Http\Event\Token_Deauthenticated_Event;
use Symfony\Component\Security\Http\Security_Events;
/**
 * Makes sure all event listeners on the global dispatcher are also listening
 * to events on the firewall-specific dispatchers.
 *
 * This compiler pass must be run after RegisterListenersPass of the
 * EventDispatcher component.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
class Register_Global_Security_Event_Listeners_Pass implements Compiler_Pass_Interface
{
    private const EVENT_BUBBLING_EVENTS = [
        Check_Passport_Event::class,
        Login_Failure_Event::class,
        Login_Success_Event::class,
        Logout_Event::class,
        Authentication_Token_Created_Event::class,
        Authentication_Success_Event::class,
        Interactive_Login_Event::class,
        Token_Deauthenticated_Event::class,
        // When events are registered by their name
        Authentication_Events::AUTHENTICATION_SUCCESS,
        Security_Events::INTERACTIVE_LOGIN,
    ];
    public function process(Container_Builder $container): void
    {
        if (!$container->has('event_dispatcher') || !$container->has_parameter('security.firewalls')) {
            return;
        }
        $firewall_dispatchers = [];
        foreach ($container->get_parameter('security.firewalls') as $firewall_name) {
            if (!$container->has('security.event_dispatcher.' . $firewall_name)) {
                continue;
            }
            $firewall_dispatchers[] = $container->find_definition('security.event_dispatcher.' . $firewall_name);
        }
        $global_dispatcher = $container->find_definition('event_dispatcher');
        foreach ($global_dispatcher->get_method_calls() as $method_call) {
            if ('addListener' !== $method_call[0]) {
                continue;
            }
            $method_call_arguments = $method_call[1];
            if (!\in_array($method_call_arguments[0], self::EVENT_BUBBLING_EVENTS, true)) {
                continue;
            }
            foreach ($firewall_dispatchers as $firewall_dispatcher) {
                $firewall_dispatcher->add_method_call('addListener', $method_call_arguments);
            }
        }
    }
}