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
namespace Symfony\Component\Http_Kernel\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Attribute\Autowire;
use Symfony\Component\Dependency_Injection\Attribute\Autowire_Callable;
use Symfony\Component\Dependency_Injection\Attribute\Target;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Service_Locator_Tag_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Typed_Reference;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Session\Session_Interface;
use Symfony\Component\Var_Exporter\Proxy_Helper;
/**
 * Creates the service-locators required by ServiceValueResolver.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Register_Controller_Argument_Locators_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('argument_resolver.service') && !$container->has_definition('argument_resolver.not_tagged_controller')) {
            return;
        }
        $parameter_bag = $container->get_parameter_bag();
        $controllers = [];
        $controller_classes = [];
        $public_aliases = [];
        foreach ($container->get_aliases() as $id => $alias) {
            if ($alias->is_public()) {
                $public_aliases[(string) $alias][] = $id;
            }
        }
        foreach ($container->find_tagged_service_ids('controller.service_arguments', true) as $id => $tags) {
            $def = $container->get_definition($id);
            $def->set_public(true);
            $def->set_lazy(false);
            $class = $def->get_class();
            $autowire = $def->is_autowired();
            $bindings = $def->get_bindings();
            // resolve service class, taking parent definitions into account
            while ($def instanceof Child_Definition) {
                $def = $container->find_definition($def->get_parent());
                $class = $class ?: $def->get_class();
                $bindings += $def->get_bindings();
            }
            $class = $parameter_bag->resolve_value($class);
            if (!$r = $container->get_reflection_class($class)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            $controller_classes[] = $class;
            // get regular public methods
            $methods = [];
            $arguments = [];
            foreach ($r->get_methods(\ReflectionMethod::IS_PUBLIC) as $r) {
                if ('setContainer' === $r->name) {
                    continue;
                }
                if (!$r->is_constructor() && !$r->is_destructor() && !$r->is_abstract()) {
                    $methods[strtolower($r->name)] = [$r, $r->get_parameters()];
                }
            }
            // validate and collect explicit per-actions and per-arguments service references
            foreach ($tags as $attributes) {
                if (!isset($attributes['action']) && !isset($attributes['argument']) && !isset($attributes['id'])) {
                    $autowire = true;
                    continue;
                }
                foreach (['action', 'argument', 'id'] as $k) {
                    if (!isset($attributes[$k][0])) {
                        throw new InvalidArgumentException(\sprintf('Missing "%s" attribute on tag "controller.service_arguments" %s for service "%s".', $k, json_encode($attributes, \JSON_UNESCAPED_UNICODE), $id));
                    }
                }
                if (!isset($methods[$action = strtolower((string) $attributes['action'])])) {
                    throw new InvalidArgumentException(\sprintf('Invalid "action" attribute on tag "controller.service_arguments" for service "%s": no public "%s()" method found on class "%s".', $id, $attributes['action'], $class));
                }
                [$r, $parameters] = $methods[$action];
                $found = false;
                foreach ($parameters as $p) {
                    if ($attributes['argument'] === $p->name) {
                        if (!isset($arguments[$r->name][$p->name])) {
                            $arguments[$r->name][$p->name] = $attributes['id'];
                        }
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    throw new InvalidArgumentException(\sprintf('Invalid "controller.service_arguments" tag for service "%s": method "%s()" has no "%s" argument on class "%s".', $id, $r->name, $attributes['argument'], $class));
                }
            }
            foreach ($methods as [$r, $parameters]) {
                /** @var \ReflectionMethod $r */
                // create a per-method map of argument-names to service/type-references
                $args = [];
                $errored_ids = 0;
                foreach ($parameters as $p) {
                    /** @var \ReflectionParameter $p */
                    $type = preg_replace('/(^|[(|&])\\\\/', '\1', $target = ltrim(Proxy_Helper::export_type($p) ?? '', '?'));
                    $invalid_behavior = Container_Interface::IGNORE_ON_INVALID_REFERENCE;
                    $autowire_attributes = null;
                    $parsed_name = $p->name;
                    $k = null;
                    if (isset($arguments[$r->name][$p->name])) {
                        $target = $arguments[$r->name][$p->name];
                        if ('?' !== $target[0]) {
                            $invalid_behavior = Container_Interface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE;
                        } elseif ('' === $target = substr($target, 1)) {
                            throw new InvalidArgumentException(\sprintf('A "controller.service_arguments" tag must have non-empty "id" attributes for service "%s".', $id));
                        } elseif ($p->allows_null() && !$p->is_optional()) {
                            $invalid_behavior = Container_Interface::NULL_ON_INVALID_REFERENCE;
                        }
                    } elseif (isset($bindings[$binding_name = $type . ' $' . $name = Target::parse_name($p, $k, $parsed_name)]) || isset($bindings[$binding_name = $type . ' $' . $parsed_name]) || isset($bindings[$binding_name = '$' . $name]) || isset($bindings[$binding_name = $type])) {
                        $binding = $bindings[$binding_name];
                        [$binding_value, $binding_id, , $binding_type, $binding_file] = $binding->get_values();
                        $binding->set_values([$binding_value, $binding_id, true, $binding_type, $binding_file]);
                        $args[$p->name] = $binding_value;
                        continue;
                    } elseif (!$autowire || !($autowire_attributes = $p->get_attributes(Autowire::class, \Reflection_Attribute::IS_INSTANCEOF)) && (!$type || '\\' !== $target[0])) {
                        continue;
                    } elseif (!$autowire_attributes && is_subclass_of($type, \Unit_Enum::class)) {
                        // do not attempt to register enum typed arguments if not already present in bindings
                        continue;
                    } elseif (!$p->allows_null()) {
                        $invalid_behavior = Container_Interface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE;
                    }
                    if (Request::class === $type) {
                        continue;
                    }
                    if (Session_Interface::class === $type) {
                        continue;
                    }
                    if (Response::class === $type) {
                        continue;
                    }
                    if ($autowire_attributes) {
                        $attribute = $autowire_attributes[0]->new_instance();
                        $value = $parameter_bag->resolve_value($attribute->value);
                        if ($attribute instanceof Autowire_Callable) {
                            $args[$p->name] = $attribute->build_definition($value, $type, $p);
                        } elseif ($value instanceof Reference) {
                            $args[$p->name] = $type ? new Typed_Reference($value, $type, $invalid_behavior, $p->name) : new Reference($value, $invalid_behavior);
                        } else {
                            $args[$p->name] = new Reference('.value.' . $container->hash($value));
                            $container->register((string) $args[$p->name], 'mixed')->set_factory('current')->add_argument([$value]);
                        }
                        continue;
                    }
                    if ($type && !$p->is_optional() && !$p->allows_null() && !class_exists($type) && !interface_exists($type, false)) {
                        $message = \sprintf('Cannot determine controller argument for "%s::%s()": the $%s argument is type-hinted with the non-existent class or interface: "%s".', $class, $r->name, $p->name, $type);
                        // see if the type-hint lives in the same namespace as the controller
                        if (0 === strncmp($type, $class, strrpos($class, '\\'))) {
                            $message .= ' Did you forget to add a use statement?';
                        }
                        $container->register($errored_id = '.errored.' . $container->hash($message), $type)->add_error($message);
                        $args[$p->name] = new Reference($errored_id, Container_Interface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
                        ++$errored_ids;
                    } else {
                        $target_attribute = null;
                        $name = Target::parse_name($p, $target_attribute);
                        $target = preg_replace('/(^|[(|&])\\\\/', '\1', (string) $target);
                        $args[$p->name] = $type ? new Typed_Reference($target, $type, $invalid_behavior, $name, $target_attribute ? [$target_attribute] : []) : new Reference($target, $invalid_behavior);
                    }
                }
                // register the maps as a per-method service-locators
                if ($args) {
                    $controllers[$id . '::' . $r->name] = Service_Locator_Tag_Pass::register($container, $args, \count($args) !== $errored_ids ? $id . '::' . $r->name . '()' : null);
                    foreach ($public_aliases[$id] ?? [] as $alias) {
                        $controllers[$alias . '::' . $r->name] = clone $controllers[$id . '::' . $r->name];
                    }
                }
            }
        }
        $controller_locator_ref = Service_Locator_Tag_Pass::register($container, $controllers);
        if ($container->has_definition('argument_resolver.service')) {
            $container->get_definition('argument_resolver.service')->replace_argument(0, $controller_locator_ref);
        }
        if ($container->has_definition('argument_resolver.not_tagged_controller')) {
            $container->get_definition('argument_resolver.not_tagged_controller')->replace_argument(0, $controller_locator_ref);
        }
        $container->set_alias('argument_resolver.controller_locator', (string) $controller_locator_ref);
        if ($container->has_definition('controller_resolver')) {
            $container->get_definition('controller_resolver')->add_method_call('allowControllers', [array_unique($controller_classes)]);
        }
    }
}