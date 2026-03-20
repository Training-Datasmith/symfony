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

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

use DateTimeImmutable as DateTime;
use DateTimeInterface;

use Symfony\Component\TypeInfo\Type;

final class DummyWithUses
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
