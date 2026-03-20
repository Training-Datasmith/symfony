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
 * A clock that always returns the same date, suitable for testing time-sensitive logic.
 *
 * Consider using ClockSensitiveTrait in your test cases instead of using this class directly.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Mock_Clock implements Clock_Interface
{
    private Date_Point $now;
    /**
     * @throws \DateMalformedStringException When $now is invalid
     * @throws \DateInvalidTimeZoneException When $timezone is invalid
     */
    public function __construct(\DateTimeImmutable|string $now = 'now', \DateTimeZone|string|null $timezone = null)
    {
        if (\is_string($timezone)) {
            $timezone = new \DateTimeZone($timezone);
        }
        if (\is_string($now)) {
            $now = new Date_Point($now, $timezone ?? new \DateTimeZone('UTC'));
        } elseif (!$now instanceof Date_Point) {
            $now = Date_Point::create_from_interface($now);
        }
        $this->now = null !== $timezone ? $now->set_timezone($timezone) : $now;
    }
    public function now(): Date_Point
    {
        return clone $this->now;
    }
    public function sleep(float|int $seconds): void
    {
        if (0 >= $seconds) {
            return;
        }
        $now = (float) $this->now->format('Uu') + $seconds * 1000000.0;
        $now = substr_replace(\sprintf('@%07.0F', $now), '.', -6, 0);
        $timezone = $this->now->get_timezone();
        $this->now = Date_Point::create_from_interface(new \DateTimeImmutable($now, $timezone))->set_timezone($timezone);
    }
    /**
     * @throws \DateMalformedStringException When $modifier is invalid
     */
    public function modify(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
    /**
     * @throws \DateInvalidTimeZoneException When the timezone name is invalid
     */
    public function with_time_zone(\DateTimeZone|string $timezone): static
    {
        if (\is_string($timezone)) {
            $timezone = new \DateTimeZone($timezone);
        }
        $clone = clone $this;
        $clone->now = $clone->now->set_timezone($timezone);
        return $clone;
    }
}