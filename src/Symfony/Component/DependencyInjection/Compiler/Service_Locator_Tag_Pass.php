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
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Service_Locator;
/**
 * Applies the "container.service_locator" tag by wrapping references into ServiceClosureArgument instances.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Service_Locator_Tag_Pass extends Abstract_Recursive_Pass
{
    use Priority_Tagged_Service_Trait;
    protected bool $skip_scalars = true;
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Service_Locator_Argument) {
            if ($value->get_tagged_iterator_argument()) {
                $value->set_values($this->find_and_sort_tagged_services($value->get_tagged_iterator_argument(), $this->container));
            }
            return self::register($this->container, $value->get_values());
        }
        if ($value instanceof Definition) {
            $value->set_bindings(parent::process_value($value->get_bindings()));
        }
        if (!$value instanceof Definition || !$value->has_tag('container.service_locator')) {
            return parent::process_value($value, $is_root);
        }
        if (!$value->get_class()) {
            $value->set_class(Service_Locator::class);
        }
        $values = $value->get_arguments()[0] ?? null;
        $services = [];
        if ($values instanceof Tagged_Iterator_Argument) {
            foreach ($this->find_and_sort_tagged_services($values, $this->container) as $k => $v) {
                $services[$k] = new Service_Closure_Argument($v);
            }
        } elseif (!\is_array($values)) {
            throw new InvalidArgumentException(\sprintf('Invalid definition for service "%s": an array of references is expected as first argument when the "container.service_locator" tag is set.', $this->current_id));
        } else {
            $i = 0;
            foreach ($values as $k => $v) {
                if ($v instanceof Service_Closure_Argument) {
                    $services[$k] = $v;
                    continue;
                }
                if ($i === $k) {
                    if ($v instanceof Reference) {
                        $k = (string) $v;
                    }
                    ++$i;
                } elseif (\is_int($k)) {
                    $i = null;
                }
                $services[$k] = new Service_Closure_Argument($v);
            }
            if (\count($services) === $i) {
                ksort($services);
            }
        }
        $value->set_argument(0, $services);
        $id = '.service_locator.' . Container_Builder::hash($value);
        if ($is_root) {
            if ($id !== $this->current_id) {
                $this->container->set_alias($id, new Alias($this->current_id, false));
            }
            return $value;
        }
        $this->container->set_definition($id, $value->set_public(false));
        return new Reference($id);
    }
    public static function register(Container_Builder $container, array $map, ?string $caller_id = null): Reference
    {
        foreach ($map as $k => $v) {
            $map[$k] = new Service_Closure_Argument($v);
        }
        $locator = (new Definition(Service_Locator::class))->add_argument($map)->add_tag('container.service_locator');
        if (null !== $caller_id && $container->has_definition($caller_id)) {
            $locator->set_bindings($container->get_definition($caller_id)->get_bindings());
        }
        if (!$container->has_definition($id = '.service_locator.' . Container_Builder::hash($locator))) {
            $container->set_definition($id, $locator);
        }
        if (null !== $caller_id) {
            $locator_id = $id;
            // Locators are shared when they hold the exact same list of factories;
            // to have them specialized per consumer service, we use a cloning factory
            // to derivate customized instances from the prototype one.
            $container->register($id .= '.' . $caller_id, Service_Locator::class)->set_factory([new Reference($locator_id), 'withContext'])->add_tag('container.service_locator_context', ['id' => $caller_id])->add_argument($caller_id)->add_argument(new Reference('service_container'));
        }
        return new Reference($id);
    }
}