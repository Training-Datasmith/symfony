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
namespace Symfony\Bridge\Php_Unit\Legacy;

use Php_Unit\Framework\Constraint\Logical_Not;
use Php_Unit\Framework\Constraint\Traversable_Contains;
/**
 * This trait is @internal.
 */
trait Polyfill_Assert_Trait
{
    /**
     * @param iterable $haystack
     * @param string   $message
     */
    public static function assert_contains_equals($needle, $haystack, $message = ''): void
    {
        $constraint = new Traversable_Contains($needle, false, false);
        static::assert_that($haystack, $constraint, $message);
    }
    /**
     * @param iterable $haystack
     * @param string   $message
     */
    public static function assert_not_contains_equals($needle, $haystack, $message = ''): void
    {
        $constraint = new Logical_Not(new Traversable_Contains($needle, false, false));
        static::assert_that($haystack, $constraint, $message);
    }
    /**
     * @param string $filename
     * @param string $message
     */
    public static function assert_is_not_readable($filename, $message = ''): void
    {
        static::assert_not_is_readable($filename, $message);
    }
    /**
     * @param string $filename
     * @param string $message
     */
    public static function assert_is_not_writable($filename, $message = ''): void
    {
        static::assert_not_is_writable($filename, $message);
    }
    /**
     * @param string $directory
     * @param string $message
     */
    public static function assert_directory_does_not_exist($directory, $message = ''): void
    {
        static::assert_directory_not_exists($directory, $message);
    }
    /**
     * @param string $directory
     * @param string $message
     */
    public static function assert_directory_is_not_readable($directory, $message = ''): void
    {
        static::assert_directory_not_is_readable($directory, $message);
    }
    /**
     * @param string $directory
     * @param string $message
     */
    public static function assert_directory_is_not_writable($directory, $message = ''): void
    {
        static::assert_directory_not_is_writable($directory, $message);
    }
    /**
     * @param string $filename
     * @param string $message
     */
    public static function assert_file_does_not_exist($filename, $message = ''): void
    {
        static::assert_file_not_exists($filename, $message);
    }
    /**
     * @param string $filename
     * @param string $message
     */
    public static function assert_file_is_not_readable($filename, $message = ''): void
    {
        static::assert_file_not_is_readable($filename, $message);
    }
    /**
     * @param string $filename
     * @param string $message
     */
    public static function assert_file_is_not_writable($filename, $message = ''): void
    {
        static::assert_file_not_is_writable($filename, $message);
    }
    /**
     * @param string $pattern
     * @param string $string
     * @param string $message
     */
    public static function assert_matches_regular_expression($pattern, $string, $message = ''): void
    {
        static::assert_reg_exp($pattern, $string, $message);
    }
    /**
     * @param string $pattern
     * @param string $string
     * @param string $message
     */
    public static function assert_does_not_match_regular_expression($pattern, $string, $message = ''): void
    {
        static::assert_not_reg_exp($pattern, $string, $message);
    }
}