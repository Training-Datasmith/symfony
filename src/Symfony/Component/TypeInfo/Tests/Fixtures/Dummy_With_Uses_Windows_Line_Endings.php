<?php

declare(strict_types=1);

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

use DateTimeImmutable as DateTime;
use DateTimeInterface;
use Symfony\Component\TypeInfo\Type;

final class DummyWithUsesWindowsLineEndings
{
    private DateTimeInterface $createdAt;

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getType(): Type
    {
        throw new \LogicException('Should not be called.');
    }
}
