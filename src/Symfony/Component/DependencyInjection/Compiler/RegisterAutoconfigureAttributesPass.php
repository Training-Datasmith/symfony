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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Attribute\Autoconfigure;
use Symfony\Component\Dependency_Injection\Attribute\Lazy;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\Autoconfigure_Failed_Exception;
use Symfony\Component\Dependency_Injection\Loader\Yaml_File_Loader;
/**
 * Reads #[Autoconfigure] attributes on definitions that are autoconfigured
 * and don't have the "container.ignore_attributes" tag.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Register_Autoconfigure_Attributes_Pass implements Compiler_Pass_Interface
{
    private static \Closure $register_for_autoconfiguration;
    public function process(Container_Builder $container): void
    {
        foreach ($container->get_definitions() as $definition) {
            if ($this->accept($definition) && $class = $container->get_reflection_class($definition->get_class(), false)) {
                $this->process_class($container, $class);
            }
        }
    }
    public function accept(Definition $definition): bool
    {
        return $definition->is_autoconfigured() && !$definition->has_tag('container.ignore_attributes');
    }
    public function process_class(Container_Builder $container, \ReflectionClass $class): void
    {
        $autoconfigure = $class->get_attributes(Autoconfigure::class, \Reflection_Attribute::IS_INSTANCEOF);
        $lazy = $class->get_attributes(Lazy::class, \Reflection_Attribute::IS_INSTANCEOF);
        if ($autoconfigure && $lazy) {
            throw new Autoconfigure_Failed_Exception($class->name, 'Using both attributes #[Lazy] and #[Autoconfigure] on an argument is not allowed; use the "lazy" parameter of #[Autoconfigure] instead.');
        }
        $attributes = array_merge($autoconfigure, $lazy);
        foreach ($attributes as $attribute) {
            self::register_for_autoconfiguration($container, $class, $attribute);
        }
    }
    private static function register_for_autoconfiguration(Container_Builder $container, \ReflectionClass $class, \Reflection_Attribute $attribute): void
    {
        if (isset(self::$register_for_autoconfiguration)) {
            (self::$register_for_autoconfiguration)($container, $class, $attribute);
            return;
        }
        $parse_definitions = new \ReflectionMethod(Yaml_File_Loader::class, 'parseDefinitions');
        $yaml_loader = $parse_definitions->get_declaring_class()->new_instance_without_constructor();
        self::$register_for_autoconfiguration = static function (Container_Builder $container, \ReflectionClass $class, \Reflection_Attribute $attribute) use ($parse_definitions, $yaml_loader): void {
            $attribute = (array) $attribute->new_instance();
            foreach (['tags', 'resourceTags'] as $type) {
                foreach ($attribute[$type] ?? [] as $i => $tag) {
                    if (\is_array($tag) && [0] === array_keys($tag)) {
                        $attribute[$type][$i] = [$class->name => $tag[0]];
                    }
                }
            }
            if (isset($attribute['resourceTags'])) {
                $attribute['resource_tags'] = $attribute['resourceTags'];
            }
            unset($attribute['resourceTags']);
            $parse_definitions->invoke($yaml_loader, ['services' => ['_instanceof' => [$class->name => [$container->register_for_autoconfiguration($class->name)] + $attribute]]], $class->get_file_name(), false);
        };
        (self::$register_for_autoconfiguration)($container, $class, $attribute);
    }
}