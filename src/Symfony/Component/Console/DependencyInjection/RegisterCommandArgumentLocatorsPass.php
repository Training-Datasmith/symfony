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
namespace Symfony\Component\Console\Dependency_Injection;

use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
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
use Symfony\Component\Var_Exporter\Proxy_Helper;
/**
 * Creates the service-locators required by ServiceValueResolver for commands.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class Register_Command_Argument_Locators_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('console.argument_resolver.service')) {
            return;
        }
        $parameter_bag = $container->get_parameter_bag();
        $service_locators = [];
        foreach ($container->find_tagged_service_ids('console.command.service_arguments', true) as $id => $tags) {
            $def = $container->get_definition($id);
            $class = $def->get_class();
            $autowire = $def->is_autowired();
            $bindings = $def->get_bindings();
            // Resolve service class, taking parent definitions into account
            while ($def instanceof Child_Definition) {
                $def = $container->find_definition($def->get_parent());
                $class = $class ?: $def->get_class();
                $bindings += $def->get_bindings();
            }
            $class = $parameter_bag->resolve_value($class);
            if (!$r = $container->get_reflection_class($class)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for command "%s" cannot be found.', $class, $id));
            }
            // Get all console.command tags to find command names and their methods
            $command_tags = $container->get_definition($id)->get_tag('console.command');
            $manual_arguments = [];
            // Validate and collect explicit per-arguments service references
            foreach ($tags as $attributes) {
                if (!isset($attributes['argument']) && !isset($attributes['id'])) {
                    $autowire = true;
                    continue;
                }
                foreach (['argument', 'id'] as $k) {
                    if (!isset($attributes[$k][0])) {
                        throw new InvalidArgumentException(\sprintf('Missing "%s" attribute on tag "console.command.service_arguments" %s for service "%s".', $k, json_encode($attributes, \JSON_UNESCAPED_UNICODE), $id));
                    }
                }
                $manual_arguments[$attributes['argument']] = $attributes['id'];
            }
            foreach ($command_tags as $command_tag) {
                $command_name = $command_tag['command'] ?? null;
                if (!$command_name) {
                    continue;
                }
                $method_name = $command_tag['method'] ?? '__invoke';
                if (!$r->has_method($method_name)) {
                    continue;
                }
                $method = $r->get_method($method_name);
                $arguments = [];
                $errored_ids = 0;
                foreach ($method->get_parameters() as $p) {
                    $type = preg_replace('/(^|[(|&])\\\\/', '\1', $target = ltrim(Proxy_Helper::export_type($p) ?? '', '?'));
                    $invalid_behavior = Container_Interface::IGNORE_ON_INVALID_REFERENCE;
                    $autowire_attributes = null;
                    $parsed_name = $p->name;
                    $k = null;
                    if (isset($manual_arguments[$p->name])) {
                        $target = $manual_arguments[$p->name];
                        if ('?' !== $target[0]) {
                            $invalid_behavior = Container_Interface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE;
                        } elseif ('' === $target = substr($target, 1)) {
                            throw new InvalidArgumentException(\sprintf('A "console.command.service_arguments" tag must have non-empty "id" attributes for service "%s".', $id));
                        } elseif ($p->allows_null() && !$p->is_optional()) {
                            $invalid_behavior = Container_Interface::NULL_ON_INVALID_REFERENCE;
                        }
                    } elseif (isset($bindings[$binding_name = $type . ' $' . $name = Target::parse_name($p, $k, $parsed_name)]) || isset($bindings[$binding_name = $type . ' $' . $parsed_name]) || isset($bindings[$binding_name = '$' . $name]) || isset($bindings[$binding_name = $type])) {
                        $binding = $bindings[$binding_name];
                        [$binding_value, $binding_id, , $binding_type, $binding_file] = $binding->get_values();
                        $binding->set_values([$binding_value, $binding_id, true, $binding_type, $binding_file]);
                        $arguments[$p->name] = $binding_value;
                        continue;
                    } elseif (!$autowire || !($autowire_attributes = $p->get_attributes(Autowire::class, \Reflection_Attribute::IS_INSTANCEOF)) && (!$type || '\\' !== $target[0])) {
                        continue;
                    } elseif (!$autowire_attributes && is_subclass_of($type, \Unit_Enum::class)) {
                        // Do not attempt to register enum typed arguments if not already present in bindings
                        continue;
                    } elseif (!$p->allows_null()) {
                        $invalid_behavior = Container_Interface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE;
                    }
                    // Skip console-specific types that are resolved by other resolvers
                    if (Input_Interface::class === $type) {
                        continue;
                    }
                    if (Output_Interface::class === $type) {
                        continue;
                    }
                    if ($autowire_attributes) {
                        $attribute = $autowire_attributes[0]->new_instance();
                        $value = $parameter_bag->resolve_value($attribute->value);
                        if ($attribute instanceof Autowire_Callable) {
                            $arguments[$p->name] = $attribute->build_definition($value, $type, $p);
                        } elseif ($value instanceof Reference) {
                            $arguments[$p->name] = $type ? new Typed_Reference($value, $type, $invalid_behavior, $p->name) : new Reference($value, $invalid_behavior);
                        } else {
                            $arguments[$p->name] = new Reference('.value.' . $container->hash($value));
                            $container->register((string) $arguments[$p->name], 'mixed')->set_factory('current')->add_argument([$value]);
                        }
                        continue;
                    }
                    if ($type && !$p->is_optional() && !$p->allows_null() && !class_exists($type) && !interface_exists($type, false)) {
                        $message = \sprintf('Cannot determine command argument for "%s::%s()": the $%s argument is type-hinted with the non-existent class or interface: "%s".', $class, $method->name, $p->name, $type);
                        // See if the type-hint lives in the same namespace as the command
                        if (0 === strncmp($type, $class, strrpos($class, '\\'))) {
                            $message .= ' Did you forget to add a use statement?';
                        }
                        $container->register($errored_id = '.errored.' . $container->hash($message), $type)->add_error($message);
                        $arguments[$p->name] = new Reference($errored_id, Container_Interface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
                        ++$errored_ids;
                    } else {
                        $target = preg_replace('/(^|[(|&])\\\\/', '\1', (string) $target);
                        $arguments[$p->name] = $type ? new Typed_Reference($target, $type, $invalid_behavior, Target::parse_name($p)) : new Reference($target, $invalid_behavior);
                    }
                }
                if ($arguments) {
                    $service_locators[$command_name] = Service_Locator_Tag_Pass::register($container, $arguments, \count($arguments) !== $errored_ids ? $command_name : null);
                }
            }
        }
        $container->get_definition('console.argument_resolver.service')->replace_argument(0, Service_Locator_Tag_Pass::register($container, $service_locators));
    }
}