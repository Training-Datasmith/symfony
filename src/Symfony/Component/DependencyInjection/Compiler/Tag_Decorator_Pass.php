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

use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class Tag_Decorator_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        foreach ($container->find_tagged_resource_ids('container.tag_decorator', false) as $id => $tags) {
            $definition = $container->get_definition($id);
            foreach ($tags as $tag) {
                if (!$decorates_tag = $tag['decorates_tag'] ?? null) {
                    continue;
                }
                $priority = $tag['priority'] ?? 0;
                $invalid_behavior = $tag['on_invalid'] ?? Container_Interface::EXCEPTION_ON_INVALID_REFERENCE;
                $tagged_services = $container->find_tagged_service_ids($decorates_tag);
                if (!$tagged_services) {
                    if (Container_Interface::IGNORE_ON_INVALID_REFERENCE === $invalid_behavior) {
                        continue;
                    }
                    if (Container_Interface::EXCEPTION_ON_INVALID_REFERENCE === $invalid_behavior) {
                        throw new Service_Not_Found_Exception($decorates_tag, $id);
                    }
                }
                foreach ($tagged_services as $tagged_service_id => $_) {
                    $cloned_definition = clone $definition;
                    $cloned_definition->clear_tag('container.tag_decorator');
                    $cloned_definition->clear_tag('container.excluded');
                    $cloned_definition->set_decorated_service($tagged_service_id, null, $priority, $invalid_behavior);
                    $decorator_id = \sprintf('.decorator.%s.%s', $tagged_service_id, $id);
                    $container->set_definition($decorator_id, $cloned_definition);
                }
            }
            $container->remove_definition($id);
        }
    }
}