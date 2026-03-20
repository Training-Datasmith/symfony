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
 * An immmutable DateTime with stricter error handling and return types than the native one.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Date_Point extends \DateTimeImmutable
{
    /**
     * @throws \DateMalformedStringException When $datetime is invalid
     */
    public function __construct(string $datetime = 'now', ?\DateTimeZone $timezone = null, ?parent $reference = null)
    {
        $now = $reference ?? Clock::get()->now();
        if ('now' !== $datetime) {
            if (!$now instanceof static) {
                $now = static::create_from_interface($now);
            }
            $built_in_date = new parent($datetime, $timezone ?? $now->get_timezone());
            $timezone = $built_in_date->get_timezone();
            $now = $now->set_timezone($timezone)->modify($datetime);
            if ('00:00:00.000000' === $built_in_date->format('H:i:s.u')) {
                $now = $now->set_time(0, 0);
            }
        } elseif (null !== $timezone) {
            $now = $now->set_timezone($timezone);
        }
        $this->__unserialize((array) $now);
    }
    /**
     * @throws \DateMalformedStringException When $format or $datetime are invalid
     */
    public static function create_from_format(string $format, string $datetime, ?\DateTimeZone $timezone = null): static
    {
        return parent::create_from_format($format, $datetime, $timezone) ?: throw new \Date_Malformed_String_Exception(static::get_last_errors()['errors'][0] ?? 'Invalid date string or format.');
    }
    public static function create_from_interface(\DateTimeInterface $object): static
    {
        return parent::create_from_interface($object);
    }
    public static function create_from_mutable(\DateTime $object): static
    {
        return parent::create_from_mutable($object);
    }
    public static function create_from_timestamp(int|float $timestamp): static
    {
        return parent::create_from_timestamp($timestamp);
    }
    public function add(\DateInterval $interval): static
    {
        return parent::add($interval);
    }
    public function sub(\DateInterval $interval): static
    {
        return parent::sub($interval);
    }
    /**
     * @throws \DateMalformedStringException When $modifier is invalid
     */
    public function modify(string $modifier): static
    {
        return parent::modify($modifier);
    }
    public function set_timestamp(int $value): static
    {
        return parent::set_timestamp($value);
    }
    public function set_date(int $year, int $month, int $day): static
    {
        return parent::set_date($year, $month, $day);
    }
    public function set_iso_date(int $year, int $week, int $day = 1): static
    {
        return parent::set_iso_date($year, $week, $day);
    }
    public function set_time(int $hour, int $minute, int $second = 0, int $microsecond = 0): static
    {
        return parent::set_time($hour, $minute, $second, $microsecond);
    }
    public function set_timezone(\DateTimeZone $timezone): static
    {
        return parent::set_timezone($timezone);
    }
    public function get_timezone(): \DateTimeZone
    {
        return parent::get_timezone() ?: throw new \Date_Invalid_Time_Zone_Exception('The DatePoint object has no timezone.');
    }
    public function set_microsecond(int $microsecond): static
    {
        if ($microsecond < 0 || $microsecond > 999999) {
            throw new \Date_Range_Error('DatePoint::setMicrosecond(): Argument #1 ($microsecond) must be between 0 and 999999, ' . $microsecond . ' given');
        }
        return parent::set_microsecond($microsecond);
    }
}