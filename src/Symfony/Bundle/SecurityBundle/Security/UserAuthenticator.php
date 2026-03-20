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

use Psr\Container\Container_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Security\Core\User\User_Interface;
use Symfony\Component\Security\Http\Authentication\User_Authenticator_Interface;
use Symfony\Component\Security\Http\Authenticator\Authenticator_Interface;
/**
 * A decorator that delegates all method calls to the authenticator
 * manager of the current firewall.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class User_Authenticator implements User_Authenticator_Interface
{
    use Firewall_Aware_Trait;
    public function __construct(Firewall_Map $firewall_map, Container_Interface $user_authenticators, Request_Stack $request_stack)
    {
        $this->firewall_map = $firewall_map;
        $this->locator = $user_authenticators;
        $this->request_stack = $request_stack;
    }
    public function authenticate_user(User_Interface $user, Authenticator_Interface $authenticator, Request $request, array $badges = [], array $attributes = []): ?Response
    {
        return $this->get_for_firewall()->authenticate_user($user, $authenticator, $request, $badges, $attributes);
    }
}