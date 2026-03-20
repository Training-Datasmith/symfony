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
namespace Symfony\Bundle\Security_Bundle\Remember_Me;

use Psr\Container\Container_Interface;
use Symfony\Bundle\Security_Bundle\Security\Firewall_Aware_Trait;
use Symfony\Bundle\Security_Bundle\Security\Firewall_Map;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Security\Core\User\User_Interface;
use Symfony\Component\Security\Http\Remember_Me\Remember_Me_Details;
use Symfony\Component\Security\Http\Remember_Me\Remember_Me_Handler_Interface;
/**
 * Decorates {@see RememberMeHandlerInterface} for the current firewall.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class Firewall_Aware_Remember_Me_Handler implements Remember_Me_Handler_Interface
{
    use Firewall_Aware_Trait;
    private const FIREWALL_OPTION = 'remember_me';
    public function __construct(Firewall_Map $firewall_map, Container_Interface $remember_me_handler_locator, Request_Stack $request_stack)
    {
        $this->firewall_map = $firewall_map;
        $this->locator = $remember_me_handler_locator;
        $this->request_stack = $request_stack;
    }
    public function create_remember_me_cookie(User_Interface $user): void
    {
        $this->get_for_firewall()->create_remember_me_cookie($user);
    }
    public function consume_remember_me_cookie(Remember_Me_Details $remember_me_details): User_Interface
    {
        return $this->get_for_firewall()->consume_remember_me_cookie($remember_me_details);
    }
    public function clear_remember_me_cookie(): void
    {
        $this->get_for_firewall()->clear_remember_me_cookie();
    }
}