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
namespace Symfony\Bundle\Security_Bundle\Security;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Security\Core\Authentication\Token\Storage\Token_Storage;
use Symfony\Component\Security\Http\Event\Lazy_Response_Event;
use Symfony\Component\Security\Http\Firewall\Exception_Listener;
use Symfony\Component\Security\Http\Firewall\Firewall_Listener_Interface;
use Symfony\Component\Security\Http\Firewall\Logout_Listener;
/**
 * Lazily calls authentication listeners when actually required by the access listener.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Lazy_Firewall_Context extends Firewall_Context implements Firewall_Listener_Interface
{
    public function __construct(iterable $listeners, ?Exception_Listener $exception_listener, ?Logout_Listener $logout_listener, ?Firewall_Config $config, private readonly Token_Storage $token_storage)
    {
        parent::__construct($listeners, $exception_listener, $logout_listener, $config);
    }
    public function get_listeners(): iterable
    {
        return [$this];
    }
    public function supports(Request $request): ?bool
    {
        return true;
    }
    public function authenticate(Request_Event $event): void
    {
        $listeners = [];
        $request = $event->get_request();
        $lazy = true;
        foreach (parent::get_listeners() as $listener) {
            if (false !== $supports = $listener->supports($request)) {
                $listeners[] = $listener;
                $lazy = $lazy && null === $supports;
            }
        }
        if (!$lazy) {
            foreach ($listeners as $listener) {
                $listener->authenticate($event);
                if ($event->has_response()) {
                    return;
                }
            }
            return;
        }
        $this->token_storage->set_initializer(static function () use ($event, $listeners): void {
            $event = new Lazy_Response_Event($event);
            foreach ($listeners as $listener) {
                $listener->authenticate($event);
            }
        });
    }
    public static function get_priority(): int
    {
        return 0;
    }
}