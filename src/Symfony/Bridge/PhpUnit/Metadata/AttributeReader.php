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
namespace Symfony\Bridge\Php_Unit\Metadata;

/**
 * @internal
 *
 * @template T of object
 */
final class Attribute_Reader
{
    /**
     * @var array<string, array<class-string<T>, list<T>>>
     */
    private array $cache = [];
    /**
     * @param class-string    $className
     * @param class-string<T> $name
     *
     * @return list<T>
     */
    public function for_class(string $class_name, string $name): array
    {
        $attributes = $this->cache[$class_name] ??= $this->read_attributes(new \ReflectionClass($class_name));
        return $attributes[$name] ?? [];
    }
    /**
     * @param class-string    $className
     * @param class-string<T> $name
     *
     * @return list<T>
     */
    public function for_method(string $class_name, string $method_name, string $name): array
    {
        $attributes = $this->cache[$class_name . '::' . $method_name] ??= $this->read_attributes(new \ReflectionMethod($class_name, $method_name));
        return $attributes[$name] ?? [];
    }
    /**
     * @param class-string    $className
     * @param class-string<T> $name
     *
     * @return list<T>
     */
    public function for_class_and_method(string $class_name, string $method_name, string $name): array
    {
        return [...$this->for_class($class_name, $name), ...$this->for_method($class_name, $method_name, $name)];
    }
    private function read_attributes(\ReflectionClass|\ReflectionMethod $reflection): array
    {
        $attribute_instances = [];
        foreach ($reflection->get_attributes() as $attribute) {
            if (!str_starts_with($name = $attribute->get_name(), 'Symfony\Bridge\PhpUnit\Attribute\\')) {
                continue;
            }
            $attribute_instances[$name][] = $attribute->new_instance();
        }
        return $attribute_instances;
    }
}