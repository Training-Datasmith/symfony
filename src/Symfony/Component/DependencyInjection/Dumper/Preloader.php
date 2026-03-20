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
namespace Symfony\Component\Dependency_Injection\Dumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Preloader
{
    public static function append(string $file, array $list): void
    {
        if (!file_exists($file)) {
            throw new \LogicException(\sprintf('File "%s" does not exist.', $file));
        }
        $cache_dir = \dirname($file);
        $classes = [];
        foreach ($list as $item) {
            if (str_starts_with((string) $item, $cache_dir)) {
                file_put_contents($file, \sprintf("require_once __DIR__.%s;\n", var_export(strtr(substr((string) $item, \strlen($cache_dir)), \DIRECTORY_SEPARATOR, '/'), true)), \FILE_APPEND);
                continue;
            }
            $classes[] = \sprintf("\$classes[] = %s;\n", var_export($item, true));
        }
        file_put_contents($file, \sprintf("\n\$classes = [];\n%s\$preloaded = Preloader::preload(\$classes, \$preloaded);\n", implode('', $classes)), \FILE_APPEND);
    }
    public static function preload(array $classes, array $preloaded = []): array
    {
        set_error_handler(static function ($t, $m, $f, $l): void {
            if (error_reporting() & $t) {
                if (__FILE__ !== $f) {
                    throw new \ErrorException($m, 0, $t, $f, $l);
                }
                throw new \Reflection_Exception($m);
            }
        });
        $prev = [];
        try {
            while ($prev !== $classes) {
                $prev = $classes;
                foreach ($classes as $c) {
                    if (!isset($preloaded[$c])) {
                        self::do_preload($c, $preloaded);
                    }
                }
                $classes = array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits());
            }
        } finally {
            restore_error_handler();
        }
        return $preloaded;
    }
    private static function do_preload(string $class, array &$preloaded): void
    {
        if (isset($preloaded[$class]) || \in_array($class, ['self', 'static', 'parent'], true)) {
            return;
        }
        $preloaded[$class] = true;
        try {
            if (!class_exists($class) && !interface_exists($class, false) && !trait_exists($class, false)) {
                return;
            }
            $r = new \ReflectionClass($class);
            if ($r->is_internal()) {
                return;
            }
            $r->get_constants();
            $r->get_default_properties();
            foreach ($r->get_properties(\ReflectionProperty::IS_PUBLIC) as $p) {
                self::preload_type($p->get_type(), $preloaded);
            }
            foreach ($r->get_methods(\ReflectionMethod::IS_PUBLIC) as $m) {
                foreach ($m->get_parameters() as $p) {
                    if ($p->is_default_value_available() && $p->is_default_value_constant()) {
                        $c = $p->get_default_value_constant_name();
                        if ($i = strpos((string) $c, '::')) {
                            self::do_preload(substr((string) $c, 0, $i), $preloaded);
                        }
                    }
                    self::preload_type($p->get_type(), $preloaded);
                }
                self::preload_type($m->get_return_type(), $preloaded);
            }
        } catch (\Throwable) {
            // ignore missing classes
        }
    }
    private static function preload_type(?\Reflection_Type $t, array &$preloaded): void
    {
        if (!$t) {
            return;
        }
        foreach ($t instanceof \ReflectionUnionType || $t instanceof \ReflectionIntersectionType ? $t->get_types() : [$t] as $t) {
            if (!$t->is_builtin()) {
                self::do_preload($t instanceof \ReflectionNamedType ? $t->get_name() : $t, $preloaded);
            }
        }
    }
}