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
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class Class_Exists_Mock
{
    private static array $classes = [];
    private static array $enums = [];
    /**
     * Configures the classes to be checked upon existence.
     *
     * @param array $classes Mocked class names as keys (case-sensitive, without leading root namespace slash) and booleans as values
     */
    public static function with_mocked_classes(array $classes): void
    {
        self::$classes = $classes;
    }
    /**
     * Configures the enums to be checked upon existence.
     *
     * @param array $enums Mocked enums names as keys (case-sensitive, without leading root namespace slash) and booleans as values
     */
    public static function with_mocked_enums(array $enums): void
    {
        self::$enums = $enums;
        self::$classes += $enums;
    }
    public static function class_exists($name, $autoload = true): bool
    {
        $name = ltrim((string) $name, '\\');
        return isset(self::$classes[$name]) ? (bool) self::$classes[$name] : \class_exists($name, $autoload);
    }
    public static function interface_exists($name, $autoload = true): bool
    {
        $name = ltrim((string) $name, '\\');
        return isset(self::$classes[$name]) ? (bool) self::$classes[$name] : \interface_exists($name, $autoload);
    }
    public static function trait_exists($name, $autoload = true): bool
    {
        $name = ltrim((string) $name, '\\');
        return isset(self::$classes[$name]) ? (bool) self::$classes[$name] : \trait_exists($name, $autoload);
    }
    public static function enum_exists($name, $autoload = true): bool
    {
        $name = ltrim((string) $name, '\\');
        return isset(self::$enums[$name]) ? (bool) self::$enums[$name] : \enum_exists($name, $autoload);
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
            foreach (['class', 'interface', 'trait', 'enum'] as $type) {
                if (\function_exists($ns . '\\' . $type . '_exists')) {
                    continue;
                }
                eval(<<<EOPHP
                namespace {$ns};
                
                function {$type}_exists(\$name, \$autoload = true)
                {
                    return \\{$self}::{$type}_exists(\$name, \$autoload);
                }
                
                EOPHP);
            }
        }
    }
}