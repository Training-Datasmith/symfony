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

namespace Symfony\Component\Messenger\Event;

use Symfony\Component\Messenger\Envelope;

/**
 * Dispatched when a message was received from a transport and handling failed.
 *
 * The event name is the class name.
 */
final class WorkerMessageFailedEvent extends AbstractWorkerMessageEvent
{
    private bool $willRetry = false;

    public function __construct(Envelope $envelope, string $receiverName, private readonly \Throwable $throwable)
    {
        parent::__construct($envelope, $receiverName);
    }

    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }

    public function willRetry(): bool
    {
        return $this->willRetry;
    }

    public function setForRetry(): void
    {
        $this->willRetry = true;
    }
}
