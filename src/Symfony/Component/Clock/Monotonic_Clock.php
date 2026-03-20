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

/**
 * A monotonic clock suitable for performance profiling.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Monotonic_Clock implements Clock_Interface
{
    private int $s_offset;
    private int $us_offset;
    private \DateTimeZone $timezone;
    /**
     * @throws \DateInvalidTimeZoneException When $timezone is invalid
     */
    public function __construct(\DateTimeZone|string|null $timezone = null)
    {
        if (false === $offset = hrtime()) {
            throw new \RuntimeException('hrtime() returned false: the runtime environment does not provide access to a monotonic timer.');
        }
        $time = explode(' ', microtime(), 2);
        $this->s_offset = $time[1] - $offset[0];
        $this->us_offset = (int) ($time[0] * 1000000) - (int) ($offset[1] / 1000);
        $this->timezone = \is_string($timezone ??= date_default_timezone_get()) ? $this->with_time_zone($timezone)->timezone : $timezone;
    }
    public function now(): Date_Point
    {
        [$s, $us] = hrtime();
        if (1000000 <= $us = (int) ($us / 1000) + $this->us_offset) {
            ++$s;
            $us -= 1000000;
        } elseif (0 > $us) {
            --$s;
            $us += 1000000;
        }
        if (6 !== \strlen($now = (string) $us)) {
            $now = str_pad($now, 6, '0', \STR_PAD_LEFT);
        }
        $now = '@' . ($s + $this->s_offset) . '.' . $now;
        return Date_Point::create_from_interface(new \DateTimeImmutable($now, $this->timezone))->set_timezone($this->timezone);
    }
    public function sleep(float|int $seconds): void
    {
        if (0 < $s = (int) $seconds) {
            sleep($s);
        }
        if (0 < $us = $seconds - $s) {
            usleep((int) ($us * 1000000.0));
        }
    }
    /**
     * @throws \DateInvalidTimeZoneException When $timezone is invalid
     */
    public function with_time_zone(\DateTimeZone|string $timezone): static
    {
        if (\is_string($timezone)) {
            $timezone = new \DateTimeZone($timezone);
        }
        $clone = clone $this;
        $clone->timezone = $timezone;
        return $clone;
    }
}