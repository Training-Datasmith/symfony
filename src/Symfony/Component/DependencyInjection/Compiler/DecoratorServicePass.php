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

use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Overwrites a service but keeps the overridden one.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Diego Saint Esteben <diego@saintesteben.me>
 */
class Decorator_Service_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    public function process(Container_Builder $container): void
    {
        $definitions = new \SplPriorityQueue();
        $order = \PHP_INT_MAX;
        foreach ($container->get_definitions() as $id => $definition) {
            if (!$decorated = $definition->get_decorated_service()) {
                continue;
            }
            $definitions->insert([$id, $definition], [$decorated[2], --$order]);
        }
        $decorating_definitions = [];
        $decorated_ids = [];
        $tags_to_keep = $container->has_parameter('container.behavior_describing_tags') ? $container->get_parameter('container.behavior_describing_tags') : ['proxy', 'container.do_not_inline', 'container.service_locator', 'container.service_subscriber', 'container.service_subscriber.locator'];
        foreach ($definitions as [$id, $definition]) {
            $decorated_service = $definition->get_decorated_service();
            [$inner, $renamed_id] = $decorated_service;
            $invalid_behavior = $decorated_service[3] ?? Container_Interface::EXCEPTION_ON_INVALID_REFERENCE;
            $definition->set_decorated_service(null);
            if (!$renamed_id) {
                $renamed_id = $id . '.inner';
            }
            $decorated_ids[$inner] ??= $renamed_id;
            $this->current_id = $renamed_id;
            $this->process_value($definition);
            $definition->inner_service_id = $renamed_id;
            $definition->decoration_on_invalid = $invalid_behavior;
            $definition->decoration_priority = $decorated_service[2];
            // we create a new alias/service for the service we are replacing
            // to be able to reference it in the new one
            if ($container->has_alias($inner)) {
                $alias = $container->get_alias($inner);
                $public = $alias->is_public();
                $container->set_alias($renamed_id, new Alias((string) $alias, false));
                $decorated_definition = $container->find_definition($alias);
            } elseif ($container->has_definition($inner)) {
                $decorated_definition = $container->get_definition($inner);
                $public = $decorated_definition->is_public();
                $decorated_definition->set_public(false);
                $container->set_definition($renamed_id, $decorated_definition);
                $decorating_definitions[$inner] = $decorated_definition;
            } elseif (Container_Interface::IGNORE_ON_INVALID_REFERENCE === $invalid_behavior) {
                $container->remove_definition($id);
                continue;
            } elseif (Container_Interface::NULL_ON_INVALID_REFERENCE === $invalid_behavior) {
                $public = $definition->is_public();
                $decorated_definition = null;
            } else {
                throw new Service_Not_Found_Exception($inner, $id);
            }
            if ($decorated_definition?->is_synthetic()) {
                throw new InvalidArgumentException(\sprintf('A synthetic service cannot be decorated: service "%s" cannot decorate "%s".', $id, $inner));
            }
            if (isset($decorating_definitions[$inner])) {
                $decorating_definition = $decorating_definitions[$inner];
                $decorating_tags = $decorating_definition->get_tags();
                $reset_tags = [];
                // Behavior-describing tags must not be transferred out to decorators
                foreach ($tags_to_keep as $container_tag) {
                    if (isset($decorating_tags[$container_tag])) {
                        $reset_tags[$container_tag] = $decorating_tags[$container_tag];
                        unset($decorating_tags[$container_tag]);
                    }
                }
                $definition->set_tags(array_merge($decorating_tags, $definition->get_tags()));
                $decorating_definition->set_tags($reset_tags);
                $decorating_definitions[$inner] = $definition;
            }
            $container->set_alias($inner, $id)->set_public($public);
        }
        foreach ($decorating_definitions as $inner => $definition) {
            $definition->add_tag('container.decorator', ['id' => $inner, 'inner' => $decorated_ids[$inner]]);
        }
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Reference && '.inner' === (string) $value) {
            return new Reference($this->current_id, $value->get_invalid_behavior());
        }
        return parent::process_value($value, $is_root);
    }
}