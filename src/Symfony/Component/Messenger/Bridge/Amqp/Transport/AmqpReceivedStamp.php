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

namespace Symfony\Component\Messenger\Bridge\Amqp\Transport;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * Stamp applied when a message is received from Amqp.
 */
class AmqpReceivedStamp implements NonSendableStampInterface
{
    public function __construct(
        private readonly \AMQPEnvelope $amqpEnvelope,
        private readonly string $queueName,
    ) {
    }

    public function getAmqpEnvelope(): \AMQPEnvelope
    {
        return $this->amqpEnvelope;
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }
}
