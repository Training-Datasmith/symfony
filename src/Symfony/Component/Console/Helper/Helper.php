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
namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Formatter\Output_Formatter_Interface;
use Symfony\Component\String\Unicode_String;
/**
 * Helper is the base class for all helper classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Helper implements Helper_Interface
{
    protected ?Helper_Set $helper_set = null;
    public function set_helper_set(?Helper_Set $helper_set): void
    {
        $this->helper_set = $helper_set;
    }
    public function get_helper_set(): ?Helper_Set
    {
        return $this->helper_set;
    }
    /**
     * Returns the width of a string, using mb_strwidth if it is available.
     * The width is how many characters positions the string will use.
     */
    public static function width(?string $string): int
    {
        $string ??= '';
        if (preg_match('//u', $string)) {
            $string = preg_replace('/[\p{Cc}\x7F]++/u', '', $string, -1, $count);
            return (new Unicode_String($string))->width(false) + $count;
        }
        if (false === $encoding = mb_detect_encoding($string, null, true)) {
            return \strlen($string);
        }
        return mb_strwidth($string, $encoding);
    }
    /**
     * Returns the length of a string, using mb_strlen if it is available.
     * The length is related to how many bytes the string will use.
     */
    public static function length(?string $string): int
    {
        $string ??= '';
        if (preg_match('//u', $string)) {
            return (new Unicode_String($string))->length();
        }
        if (false === $encoding = mb_detect_encoding($string, null, true)) {
            return \strlen($string);
        }
        return mb_strlen($string, $encoding);
    }
    /**
     * Returns the subset of a string, using mb_substr if it is available.
     */
    public static function substr(?string $string, int $from, ?int $length = null): string
    {
        $string ??= '';
        if (preg_match('//u', $string)) {
            return (new Unicode_String($string))->slice($from, $length);
        }
        if (false === $encoding = mb_detect_encoding($string, null, true)) {
            return substr($string, $from, $length);
        }
        return mb_substr($string, $from, $length, $encoding);
    }
    public static function format_time(int|float $secs, int $precision = 1): string
    {
        $ms = (int) ($secs * 1000);
        if (0 === $ms) {
            return '< 1 ms';
        }
        static $time_formats = [[1, 'ms'], [1000, 's'], [60000, 'min'], [3600000, 'h'], [86400000, 'd']];
        $times = [];
        foreach ($time_formats as $index => $format) {
            $milli_seconds = isset($time_formats[$index + 1]) ? $ms % $time_formats[$index + 1][0] : $ms;
            if (isset($times[$index - $precision])) {
                unset($times[$index - $precision]);
            }
            if (0 === $milli_seconds) {
                continue;
            }
            $unit_count = $milli_seconds / $format[0];
            $times[$index] = $unit_count . ' ' . $format[1];
            if ($ms === $milli_seconds) {
                break;
            }
            $ms -= $milli_seconds;
        }
        return implode(', ', array_reverse($times));
    }
    public static function format_memory(int $memory): string
    {
        if ($memory >= 1024 * 1024 * 1024) {
            return \sprintf('%.1f GiB', $memory / 1024 / 1024 / 1024);
        }
        if ($memory >= 1024 * 1024) {
            return \sprintf('%.1f MiB', $memory / 1024 / 1024);
        }
        if ($memory >= 1024) {
            return \sprintf('%d KiB', $memory / 1024);
        }
        return \sprintf('%d B', $memory);
    }
    public static function remove_decoration(Output_Formatter_Interface $formatter, ?string $string): string
    {
        $is_decorated = $formatter->is_decorated();
        $formatter->set_decorated(false);
        // remove <...> formatting
        $string = $formatter->format($string ?? '');
        // remove already formatted characters
        $string = preg_replace("/\x1b\\[[^m]*m/", '', $string ?? '');
        // remove terminal hyperlinks
        $string = preg_replace('/\033]8;[^;]*;[^\033]*\033\\\\/', '', $string ?? '');
        $formatter->set_decorated($is_decorated);
        return $string;
    }
}