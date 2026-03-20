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

use Symfony\Component\Security\Http\Firewall\Exception_Listener;
use Symfony\Component\Security\Http\Firewall\Firewall_Listener_Interface;
use Symfony\Component\Security\Http\Firewall\Logout_Listener;
/**
 * This is a wrapper around the actual firewall configuration which allows us
 * to lazy load the context for one specific firewall only when we need it.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Firewall_Context
{
    /**
     * @param iterable<mixed, FirewallListenerInterface> $listeners
     */
    public function __construct(private readonly iterable $listeners, private readonly ?Exception_Listener $exception_listener = null, private readonly ?Logout_Listener $logout_listener = null, private readonly ?Firewall_Config $config = null)
    {
    }
    public function get_config(): ?Firewall_Config
    {
        return $this->config;
    }
    /**
     * @return iterable<mixed, FirewallListenerInterface>
     */
    public function get_listeners(): iterable
    {
        return $this->listeners;
    }
    public function get_exception_listener(): ?Exception_Listener
    {
        return $this->exception_listener;
    }
    public function get_logout_listener(): ?Logout_Listener
    {
        return $this->logout_listener;
    }
}