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

use Symfony\Component\Security\Core\User\User_Interface;
use Symfony\Component\Security\Http\Remember_Me\Remember_Me_Details;
use Symfony\Component\Security\Http\Remember_Me\Remember_Me_Handler_Interface;
/**
 * Used as a "workaround" for tagging aliases in the RememberMeFactory.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
final readonly class Decorated_Remember_Me_Handler implements Remember_Me_Handler_Interface
{
    public function __construct(private Remember_Me_Handler_Interface $handler)
    {
    }
    public function create_remember_me_cookie(User_Interface $user): void
    {
        $this->handler->create_remember_me_cookie($user);
    }
    public function consume_remember_me_cookie(Remember_Me_Details $remember_me_details): User_Interface
    {
        return $this->handler->consume_remember_me_cookie($remember_me_details);
    }
    public function clear_remember_me_cookie(): void
    {
        $this->handler->clear_remember_me_cookie();
    }
}