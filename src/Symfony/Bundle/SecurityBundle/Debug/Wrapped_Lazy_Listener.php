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

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Security\Core\Exception\Lazy_Response_Exception;
use Symfony\Component\Security\Http\Authenticator\Debug\Traceable_Authenticator_Manager_Listener;
use Symfony\Component\Security\Http\Firewall\Abstract_Listener;
use Symfony\Component\Security\Http\Firewall\Firewall_Listener_Interface;
use Symfony\Component\Var_Dumper\Caster\Class_Stub;
/**
 * Wraps a lazy security listener.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
final class Wrapped_Lazy_Listener extends Abstract_Listener
{
    private ?Response $response = null;
    private ?float $time = null;
    private Class_Stub $stub;
    public function __construct(private readonly Firewall_Listener_Interface $listener)
    {
    }
    public function supports(Request $request): ?bool
    {
        return $this->listener->supports($request);
    }
    public function authenticate(Request_Event $event): void
    {
        $start_time = microtime(true);
        try {
            $this->listener->authenticate($event);
        } catch (Lazy_Response_Exception $e) {
            $this->response = $e->get_response();
            throw $e;
        } finally {
            $this->time = microtime(true) - $start_time;
        }
        $this->response = $event->get_response();
    }
    public function get_info(): array
    {
        return ['response' => $this->response, 'time' => $this->time, 'stub' => $this->stub ??= new Class_Stub($this->listener instanceof Traceable_Authenticator_Manager_Listener ? $this->listener->get_authenticator_manager_listener()::class : $this->listener::class)];
    }
    /**
     * Proxies all method calls to the original listener.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->listener->{$method}(...$arguments);
    }
}