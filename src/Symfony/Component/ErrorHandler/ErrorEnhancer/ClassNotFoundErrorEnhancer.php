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
namespace Symfony\Component\Error_Handler\Error_Enhancer;

use Composer\Autoload\Class_Loader;
use Symfony\Component\Error_Handler\Debug_Class_Loader;
use Symfony\Component\Error_Handler\Error\Class_Not_Found_Error;
use Symfony\Component\Error_Handler\Error\Fatal_Error;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Class_Not_Found_Error_Enhancer implements Error_Enhancer_Interface
{
    public function enhance(\Throwable $error): ?\Throwable
    {
        // Some specific versions of PHP produce a fatal error when extending a not found class.
        $message = !$error instanceof Fatal_Error ? $error->get_message() : $error->get_error()['message'];
        if (!preg_match('/^(Class|Interface|Trait) [\'"]([^\'"]+)[\'"] not found$/', (string) $message, $matches)) {
            return null;
        }
        $type_name = strtolower($matches[1]);
        $fully_qualified_class_name = $matches[2];
        if (false !== $namespace_separator_index = strrpos($fully_qualified_class_name, '\\')) {
            $class_name = substr($fully_qualified_class_name, $namespace_separator_index + 1);
            $namespace_prefix = substr($fully_qualified_class_name, 0, $namespace_separator_index);
            $message = \sprintf('Attempted to load %s "%s" from namespace "%s".', $type_name, $class_name, $namespace_prefix);
            $tail = ' for another namespace?';
        } else {
            $class_name = $fully_qualified_class_name;
            $message = \sprintf('Attempted to load %s "%s" from the global namespace.', $type_name, $class_name);
            $tail = '?';
        }
        if ($candidates = $this->get_class_candidates($class_name)) {
            $tail = array_pop($candidates) . '"?';
            if ($candidates) {
                $tail = ' for e.g. "' . implode('", "', $candidates) . '" or "' . $tail;
            } else {
                $tail = ' for "' . $tail;
            }
        }
        $message .= "\nDid you forget a \"use\" statement" . $tail;
        return new Class_Not_Found_Error($message, $error);
    }
    /**
     * Tries to guess the full namespace for a given class name.
     *
     * By default, it looks for PSR-0 and PSR-4 classes registered via a Symfony or a Composer
     * autoloader (that should cover all common cases).
     *
     * @param string $class A class name (without its namespace)
     *
     * Returns an array of possible fully qualified class names
     */
    private function get_class_candidates(string $class): array
    {
        if (!\is_array($functions = spl_autoload_functions())) {
            return [];
        }
        // find Symfony and Composer autoloaders
        $classes = [];
        foreach ($functions as $function) {
            if (!\is_array($function)) {
                continue;
            }
            // get class loaders wrapped by DebugClassLoader
            if ($function[0] instanceof Debug_Class_Loader) {
                $function = $function[0]->get_class_loader();
                if (!\is_array($function)) {
                    continue;
                }
            }
            if ($function[0] instanceof Class_Loader) {
                foreach ($function[0]->get_prefixes() as $prefix => $paths) {
                    foreach ($paths as $path) {
                        $classes[] = $this->find_class_in_path($path, $class, $prefix);
                    }
                }
                foreach ($function[0]->get_prefixes_psr4() as $prefix => $paths) {
                    foreach ($paths as $path) {
                        $classes[] = $this->find_class_in_path($path, $class, $prefix);
                    }
                }
            }
        }
        return array_unique(array_merge([], ...$classes));
    }
    private function find_class_in_path(string $path, string $class, string $prefix): array
    {
        $path = (realpath($path . '/' . strtr($prefix, '\_', '//')) ?: realpath($path . '/' . \dirname(strtr($prefix, '\_', '//')))) ?: realpath($path);
        if (!$path || !is_dir($path)) {
            return [];
        }
        $classes = [];
        $filename = $class . '.php';
        foreach (new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($path, \Recursive_Directory_Iterator::SKIP_DOTS), \Recursive_Iterator_Iterator::LEAVES_ONLY) as $file) {
            if ($filename == $file->get_file_name() && $class = $this->convert_file_to_class($path, $file->get_path_name(), $prefix)) {
                $classes[] = $class;
            }
        }
        return $classes;
    }
    private function convert_file_to_class(string $path, string $file, string $prefix): ?string
    {
        $candidates = [
            // namespaced class
            $namespaced_class = str_replace([$path . \DIRECTORY_SEPARATOR, '.php', '/'], ['', '', '\\'], $file),
            // namespaced class (with target dir)
            $prefix . $namespaced_class,
            // namespaced class (with target dir and separator)
            $prefix . '\\' . $namespaced_class,
            // PEAR class
            str_replace('\\', '_', $namespaced_class),
            // PEAR class (with target dir)
            str_replace('\\', '_', $prefix . $namespaced_class),
            // PEAR class (with target dir and separator)
            str_replace('\\', '_', $prefix . '\\' . $namespaced_class),
        ];
        if ($prefix) {
            $candidates = array_filter($candidates, static fn(string|array $candidate): bool => str_starts_with($candidate, $prefix));
        }
        // We cannot use the autoloader here as most of them use require; but if the class
        // is not found, the new autoloader call will require the file again leading to a
        // "cannot redeclare class" error.
        foreach ($candidates as $candidate) {
            if ($this->class_exists($candidate)) {
                return $candidate;
            }
        }
        // Symfony may ship some polyfills, like "Normalizer". But if the Intl
        // extension is already installed, the next require_once will fail with
        // a compile error because the class is already defined. And this one
        // does not throw a Throwable. So it's better to skip it here.
        if (str_contains($file, 'Resources/stubs')) {
            return null;
        }
        try {
            require_once $file;
        } catch (\Throwable) {
            return null;
        }
        foreach ($candidates as $candidate) {
            if ($this->class_exists($candidate)) {
                return $candidate;
            }
        }
        return null;
    }
    private function class_exists(string $class): bool
    {
        return class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false);
    }
}