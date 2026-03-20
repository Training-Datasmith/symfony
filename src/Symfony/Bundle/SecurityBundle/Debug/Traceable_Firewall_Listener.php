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
namespace Symfony\Bundle\Security_Bundle\Debug;

use Symfony\Bundle\Security_Bundle\Event_Listener\Firewall_Listener;
use Symfony\Bundle\Security_Bundle\Security\Firewall_Context;
use Symfony\Bundle\Security_Bundle\Security\Lazy_Firewall_Context;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Security\Http\Authenticator\Debug\Traceable_Authenticator_Manager_Listener;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Firewall collecting called security listeners and authenticators.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class Traceable_Firewall_Listener extends Firewall_Listener implements Reset_Interface
{
    private array $wrapped_listeners = [];
    private ?Traceable_Authenticator_Manager_Listener $authenticator_manager_listener = null;
    public function get_wrapped_listeners(): array
    {
        return array_map(static fn(Wrapped_Lazy_Listener $listener): array => $listener->get_info(), $this->wrapped_listeners);
    }
    public function get_authenticators_info(): array
    {
        return $this->authenticator_manager_listener?->get_authenticators_info() ?? [];
    }
    public function reset(): void
    {
        $this->wrapped_listeners = [];
        $this->authenticator_manager_listener = null;
    }
    protected function call_listeners(Request_Event $event, iterable $listeners): void
    {
        $request_listeners = [];
        foreach ($listeners as $listener) {
            if ($listener instanceof Lazy_Firewall_Context) {
                $context_wrapped_listeners = [];
                $context_authenticator_manager_listener = null;
                \Closure::bind(function () use (&$context_wrapped_listeners, &$context_authenticator_manager_listener): void {
                    foreach ($this->listeners as $listener) {
                        if ($listener instanceof Traceable_Authenticator_Manager_Listener) {
                            $context_authenticator_manager_listener ??= $listener;
                        }
                        $context_wrapped_listeners[] = new Wrapped_Lazy_Listener($listener);
                    }
                    $this->listeners = $context_wrapped_listeners;
                }, $listener, Firewall_Context::class)();
                $this->authenticator_manager_listener ??= $context_authenticator_manager_listener;
                $this->wrapped_listeners = array_merge($this->wrapped_listeners, $context_wrapped_listeners);
                $request_listeners[] = $listener;
            } else {
                if ($listener instanceof Traceable_Authenticator_Manager_Listener) {
                    $this->authenticator_manager_listener ??= $listener;
                }
                $wrapped_listener = new Wrapped_Lazy_Listener($listener);
                $this->wrapped_listeners[] = $wrapped_listener;
                $request_listeners[] = $wrapped_listener;
            }
        }
        foreach ($request_listeners as $listener) {
            if (false === $listener->supports($event->get_request())) {
                continue;
            }
            $listener->authenticate($event);
            if ($event->has_response()) {
                break;
            }
        }
    }
}