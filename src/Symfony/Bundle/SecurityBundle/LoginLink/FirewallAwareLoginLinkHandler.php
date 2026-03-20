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
namespace Symfony\Bundle\Security_Bundle\Login_Link;

use Psr\Container\Container_Interface;
use Symfony\Bundle\Security_Bundle\Security\Firewall_Aware_Trait;
use Symfony\Bundle\Security_Bundle\Security\Firewall_Map;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Security\Core\User\User_Interface;
use Symfony\Component\Security\Http\Login_Link\Login_Link_Details;
use Symfony\Component\Security\Http\Login_Link\Login_Link_Handler_Interface;
/**
 * Decorates the login link handler for the current firewall.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class Firewall_Aware_Login_Link_Handler implements Login_Link_Handler_Interface
{
    use Firewall_Aware_Trait;
    private const FIREWALL_OPTION = 'login_link';
    public function __construct(Firewall_Map $firewall_map, Container_Interface $login_link_handler_locator, Request_Stack $request_stack)
    {
        $this->firewall_map = $firewall_map;
        $this->locator = $login_link_handler_locator;
        $this->request_stack = $request_stack;
    }
    public function create_login_link(User_Interface $user, ?Request $request = null, ?int $lifetime = null): Login_Link_Details
    {
        return $this->get_for_firewall()->create_login_link($user, $request, $lifetime);
    }
    public function consume_login_link(Request $request): User_Interface
    {
        return $this->get_for_firewall()->consume_login_link($request);
    }
}