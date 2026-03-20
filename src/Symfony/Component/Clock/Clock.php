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
 * A global clock.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Clock implements Clock_Interface
{
    private static Clock_Interface $global_clock;
    public function __construct(private readonly ?Psr_Clock_Interface $clock = null, private ?\DateTimeZone $timezone = null)
    {
    }
    /**
     * Returns the current global clock.
     *
     * Note that you should prefer injecting a ClockInterface or using
     * ClockAwareTrait when possible instead of using this method.
     */
    public static function get(): Clock_Interface
    {
        return self::$global_clock ??= new Native_Clock();
    }
    public static function set(Psr_Clock_Interface $clock): void
    {
        self::$global_clock = $clock instanceof Clock_Interface ? $clock : new self($clock);
    }
    public function now(): Date_Point
    {
        $now = ($this->clock ?? self::get())->now();
        if (!$now instanceof Date_Point) {
            $now = Date_Point::create_from_interface($now);
        }
        return isset($this->timezone) ? $now->set_timezone($this->timezone) : $now;
    }
    public function sleep(float|int $seconds): void
    {
        $clock = $this->clock ?? self::get();
        if ($clock instanceof Clock_Interface) {
            $clock->sleep($seconds);
        } else {
            (new Native_Clock())->sleep($seconds);
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