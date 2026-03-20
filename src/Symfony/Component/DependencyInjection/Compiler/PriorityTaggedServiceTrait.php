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

use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
use Symfony\Component\Dependency_Injection\Attribute\As_Tagged_Item;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Typed_Reference;
/**
 * Trait that allows a generic method to find and sort service by priority option in the tag.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
trait Priority_Tagged_Service_Trait
{
    /**
     * Finds all services with the given tag name and order them by their priority.
     *
     * The order of additions must be respected for services having the same priority,
     * and knowing that the \SplPriorityQueue class does not respect the FIFO method,
     * we should not use that class.
     *
     * @see https://bugs.php.net/53710
     * @see https://bugs.php.net/60926
     *
     * @return Reference[]
     */
    private function find_and_sort_tagged_services(string|Tagged_Iterator_Argument $tag_name, Container_Builder $container, array $exclude = []): array
    {
        $index_attribute = $default_index_method = $needs_indexes = $default_priority_method = null;
        if ($tag_name instanceof Tagged_Iterator_Argument) {
            $index_attribute = $tag_name->get_index_attribute();
            $default_index_method = $tag_name->get_default_index_method(false);
            $needs_indexes = $tag_name->needs_indexes();
            $default_priority_method = $tag_name->get_default_priority_method(false) ?? 'getDefaultPriority';
            $exclude = array_merge($exclude, $tag_name->get_exclude());
            $tag_name = $tag_name->get_tag();
        }
        $parameter_bag = $container->get_parameter_bag();
        $services = [];
        foreach ($container->find_tagged_service_ids($tag_name, true) as $service_id => $attributes) {
            if (\in_array($service_id, $exclude, true)) {
                continue;
            }
            $default_priority = $default_attribute_priority = null;
            $default_index = $default_attribute_index = null;
            $definition = $container->get_definition($service_id);
            $class = $definition->get_class();
            $class = $container->get_parameter_bag()->resolve_value($class) ?: null;
            $reflector = null !== $class ? $container->get_reflection_class($class) : null;
            $php_attributes = $definition->is_autoconfigured() && !$definition->has_tag('container.ignore_attributes') ? $reflector?->get_attributes(As_Tagged_Item::class) : [];
            foreach ($php_attributes ??= [] as $i => $attribute) {
                $attribute = $attribute->new_instance();
                $php_attributes[$i] = ['priority' => $attribute->priority, $index_attribute ?? '' => $attribute->index];
                if (null === $default_attribute_priority) {
                    $default_attribute_priority = $attribute->priority ?? 0;
                    $default_attribute_index = $attribute->index;
                }
            }
            if (1 >= \count($php_attributes)) {
                $php_attributes = [];
            }
            // For decorated services, walk the decoration chain to find #[AsTaggedItem] on the original service
            $inner_class = null;
            $inner_def = $definition;
            while ($inner_id = $inner_def->get_tag('container.decorator')[0]['inner'] ?? null) {
                if (!$container->has($inner_id)) {
                    break;
                }
                $inner_def = $container->find_definition($inner_id);
                $inner_class = $container->get_parameter_bag()->resolve_value($inner_def->get_class()) ?: null;
            }
            $inner_reflector = null !== $inner_class ? $container->get_reflection_class($inner_class) : null;
            $attributes = array_values($attributes);
            for ($i = 0; $i < \count($attributes); ++$i) {
                if (!($attribute = $attributes[$i]) && $php_attributes) {
                    array_splice($attributes, $i--, 1, $php_attributes);
                    continue;
                }
                $index = $priority = null;
                if (isset($attribute['priority'])) {
                    $priority = $attribute['priority'];
                } elseif (null === $default_priority && $default_priority_method && $reflector) {
                    $default_priority = Priority_Tagged_Service_Util::get_default($service_id, $reflector, $default_priority_method, $tag_name, 'priority') ?? $default_attribute_priority;
                    if (null === $default_priority && null !== $inner_reflector) {
                        $default_priority = Priority_Tagged_Service_Util::get_default($service_id, $inner_reflector, $default_priority_method, $tag_name, 'priority');
                    }
                }
                $priority ??= $default_priority ??= 0;
                if (null === $index_attribute && !$default_index_method && !$needs_indexes) {
                    $services[] = [$priority, $i, null, $service_id, null];
                    continue 2;
                }
                if (null !== $index_attribute && isset($attribute[$index_attribute])) {
                    $index = $parameter_bag->resolve_value($attribute[$index_attribute]);
                }
                if (null === $index && null === $default_index && $default_priority_method && $reflector) {
                    $default_index = Priority_Tagged_Service_Util::get_default($service_id, $reflector, $default_index_method ?? 'getDefaultName', $tag_name, $index_attribute) ?? $default_attribute_index;
                    if (null === $default_index && null !== $inner_reflector) {
                        $default_index = Priority_Tagged_Service_Util::get_default($service_id, $inner_reflector, $default_index_method ?? 'getDefaultName', $tag_name, $index_attribute);
                        if (null === $default_index) {
                            foreach ($inner_reflector->get_attributes(As_Tagged_Item::class) as $inner_attr) {
                                $default_index = $inner_attr->new_instance()->index;
                                break;
                            }
                        }
                    }
                }
                $index ??= $default_index ??= $definition->get_tag('container.decorator')[0]['id'] ?? $service_id;
                $services[] = [$priority, $i, $index, $service_id, $class];
            }
        }
        uasort($services, static fn($a, $b): int => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);
        $refs = [];
        foreach ($services as [, , $index, $service_id, $class]) {
            $reference = match (true) {
                !$class => new Reference($service_id),
                $index === $service_id => new Typed_Reference($service_id, $class),
                default => new Typed_Reference($service_id, $class, Container_Builder::EXCEPTION_ON_INVALID_REFERENCE, $index),
            };
            if (null === $index) {
                $refs[] = $reference;
            } else {
                $refs[$index] = $reference;
            }
        }
        return $refs;
    }
}
/**
 * @internal
 */
class Priority_Tagged_Service_Util
{
    public static function get_default(string $service_id, \ReflectionClass $r, string $default_method, string $tag_name, ?string $index_attribute): string|int|null
    {
        if ($r->is_interface() || !$r->has_method($default_method)) {
            return null;
        }
        $class = $r->name;
        if (null !== $index_attribute) {
            $service = $class !== $service_id ? \sprintf('service "%s"', $service_id) : 'on the corresponding service';
            $message = [\sprintf('Either method "%s::%s()" should ', $class, $default_method), \sprintf(' or tag "%s" on %s is missing attribute "%s".', $tag_name, $service, $index_attribute)];
        } else {
            $message = [\sprintf('Method "%s::%s()" should ', $class, $default_method), '.'];
        }
        if (!($rm = $r->get_method($default_method))->is_static()) {
            throw new InvalidArgumentException(implode('be static', $message));
        }
        if (!$rm->is_public()) {
            throw new InvalidArgumentException(implode('be public', $message));
        }
        trigger_deprecation('symfony/dependency-injection', '8.1', 'Calling "%s::%s()" to get the "%s" index is deprecated, use the #[AsTaggedItem] attribute instead.', $class, $default_method, $index_attribute);
        $default = $rm->invoke(null);
        if ('priority' === $index_attribute) {
            if (!\is_int($default)) {
                throw new InvalidArgumentException(implode(\sprintf('return int (got "%s")', get_debug_type($default)), $message));
            }
            return $default;
        }
        if (\is_int($default)) {
            $default = (string) $default;
        }
        if (!\is_string($default)) {
            throw new InvalidArgumentException(implode(\sprintf('return string|int (got "%s")', get_debug_type($default)), $message));
        }
        return $default;
    }
}