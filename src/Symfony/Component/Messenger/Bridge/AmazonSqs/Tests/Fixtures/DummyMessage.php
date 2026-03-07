<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Fixtures;

class DummyMessage
{
    public function __construct(
        private string $message,
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
