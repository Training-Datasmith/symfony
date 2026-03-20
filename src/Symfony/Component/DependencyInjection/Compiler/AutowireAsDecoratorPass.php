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

use Symfony\Component\Dependency_Injection\Attribute\As_Decorator;
use Symfony\Component\Dependency_Injection\Attribute\As_Tag_Decorator;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
/**
 * Reads #[AsDecorator] and #[AsTagDecorator] attributes on definitions that are autowired
 * and don't have the "container.ignore_attributes" tag.
 */
final class Autowire_As_Decorator_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        foreach ($container->get_definitions() as $id => $definition) {
            if ($this->accept($definition) && $reflection_class = $container->get_reflection_class($definition->get_class(), false)) {
                $this->process_class($id, $container, $definition, $reflection_class);
            }
        }
    }
    private function accept(Definition $definition): bool
    {
        return !$definition->has_tag('container.ignore_attributes') && $definition->is_autowired();
    }
    private function process_class(string $id, Container_Builder $container, Definition $definition, \ReflectionClass $reflection_class): void
    {
        $decorator_attributes = $reflection_class->get_attributes(As_Decorator::class, \Reflection_Attribute::IS_INSTANCEOF);
        $tag_decorator_attributes = $reflection_class->get_attributes(As_Tag_Decorator::class, \Reflection_Attribute::IS_INSTANCEOF);
        if (!$decorator_attributes && !$tag_decorator_attributes) {
            return;
        }
        if (1 === \count($decorator_attributes) && !$tag_decorator_attributes) {
            $attribute = $decorator_attributes[0]->new_instance();
            $definition->set_decorated_service($attribute->decorates, null, $attribute->priority, $attribute->on_invalid);
            return;
        }
        foreach ($decorator_attributes as $attribute) {
            $attribute = $attribute->new_instance();
            $cloned_definition = clone $definition;
            $cloned_definition->set_decorated_service($attribute->decorates, null, $attribute->priority, $attribute->on_invalid);
            $container->set_definition(\sprintf('.decorator.%s.%s', $attribute->decorates, $id), $cloned_definition);
        }
        foreach ($tag_decorator_attributes as $attribute) {
            $attribute = $attribute->new_instance();
            $cloned_definition = clone $definition;
            $tag_attributes = ['decorates_tag' => $attribute->tag, 'priority' => $attribute->priority];
            if (Container_Interface::EXCEPTION_ON_INVALID_REFERENCE !== $attribute->on_invalid) {
                $tag_attributes['on_invalid'] = $attribute->on_invalid;
            }
            $cloned_definition->add_resource_tag('container.tag_decorator', $tag_attributes);
            $container->set_definition(\sprintf('.tag_decorator.%s.%s', $attribute->tag, $id), $cloned_definition);
        }
        $container->remove_definition($id);
    }
}