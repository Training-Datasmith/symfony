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
namespace Symfony\Bridge\Php_Unit;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Dominic Tubach <dominic.tubach@to.com>
 */
class Clock_Mock
{
    private static string|float|null $now = null;
    public static function with_clock_mock($enable = null): ?bool
    {
        if (null === $enable) {
            return null !== self::$now;
        }
        self::$now = is_numeric($enable) ? (float) $enable : ($enable ? microtime(true) : null);
        return null;
    }
    public static function time(): int
    {
        if (null === self::$now) {
            return \time();
        }
        return (int) self::$now;
    }
    public static function sleep($s): int
    {
        if (null === self::$now) {
            return \sleep($s);
        }
        self::$now += (int) $s;
        return 0;
    }
    public static function usleep($us): void
    {
        if (null === self::$now) {
            \usleep($us);
        } else {
            self::$now += $us / 1000000;
        }
    }
    /**
     * @return string|float
     */
    public static function microtime($as_float = false)
    {
        if (null === self::$now) {
            return \microtime($as_float);
        }
        if ($as_float) {
            return self::$now;
        }
        return \sprintf('%0.6f00 %d', self::$now - (int) self::$now, (int) self::$now);
    }
    public static function date($format, $timestamp = null): string
    {
        if (null === $timestamp) {
            $timestamp = self::time();
        }
        return \date($format, $timestamp);
    }
    public static function gmdate($format, $timestamp = null): string
    {
        if (null === $timestamp) {
            $timestamp = self::time();
        }
        return \gmdate($format, $timestamp);
    }
    public static function hrtime($as_number = false): int|float|array
    {
        $ns = (self::$now - (int) self::$now) * 1000000000;
        if ($as_number) {
            $number = \sprintf('%d%d', (int) self::$now, $ns);
            return \PHP_INT_SIZE === 8 ? (int) $number : (float) $number;
        }
        return [(int) self::$now, (int) $ns];
    }
    /**
     * @return false|int
     */
    public static function strtotime(string $datetime, ?int $timestamp = null): int|false
    {
        if (null === $timestamp) {
            $timestamp = self::time();
        }
        return \strtotime($datetime, $timestamp);
    }
    public static function register($class): void
    {
        $self = static::class;
        $mocked_ns = [substr($class, 0, strrpos($class, '\\'))];
        if (0 < strpos($class, '\Tests\\')) {
            $ns = str_replace('\Tests\\', '\\', $class);
            $mocked_ns[] = substr($ns, 0, strrpos($ns, '\\'));
        } elseif (str_starts_with($class, 'Tests\\')) {
            $mocked_ns[] = substr($class, 6, strrpos($class, '\\') - 6);
        }
        foreach ($mocked_ns as $ns) {
            if (\function_exists($ns . '\time')) {
                continue;
            }
            eval(<<<EOPHP
            namespace {$ns};
            
            function time()
            {
                return \\{$self}::time();
            }
            
            function microtime(\$asFloat = false)
            {
                return \\{$self}::microtime(\$asFloat);
            }
            
            function sleep(\$s)
            {
                return \\{$self}::sleep(\$s);
            }
            
            function usleep(\$us)
            {
                \\{$self}::usleep(\$us);
            }
            
            function date(\$format, \$timestamp = null)
            {
                return \\{$self}::date(\$format, \$timestamp);
            }
            
            function gmdate(\$format, \$timestamp = null)
            {
                return \\{$self}::gmdate(\$format, \$timestamp);
            }
            
            function hrtime(\$asNumber = false)
            {
                return \\{$self}::hrtime(\$asNumber);
            }
            
            function strtotime(\$datetime, \$timestamp = null)
            {
                return \\{$self}::strtotime(\$datetime, \$timestamp);
            }
            EOPHP);
        }
    }
}