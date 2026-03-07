<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * SwitchUserEvent.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class SwitchUserEvent extends Event
{
    public function __construct(
        private readonly Request $request,
        private readonly UserInterface $targetUser,
        private ?TokenInterface $token = null,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getTargetUser(): UserInterface
    {
        return $this->targetUser;
    }

    public function getToken(): ?TokenInterface
    {
        return $this->token;
    }

    public function setToken(TokenInterface $token): void
    {
        $this->token = $token;
    }
}
