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

namespace Symfony\Component\Notifier\Event;

use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 */
final class FailedMessageEvent extends Event
{
    public function __construct(
        private readonly MessageInterface $message,
        private readonly \Throwable $error,
    ) {
    }

    public function getMessage(): MessageInterface
    {
        return $this->message;
    }

    public function getError(): \Throwable
    {
        return $this->error;
    }
}
