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
namespace Symfony\Component\Clock;

use Psr\Clock\Clock_Interface as PsrClockInterface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface Clock_Interface extends Psr_Clock_Interface
{
    public function sleep(float|int $seconds): void;
    public function with_time_zone(\DateTimeZone|string $timezone): static;
}