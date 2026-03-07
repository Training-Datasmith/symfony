<?php

declare(strict_types=1);

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithDateTimes
{
    public \DateTimeInterface $interface;
    public \DateTimeImmutable $immutable;
    public \DateTimeImmutable|int $union;
}
