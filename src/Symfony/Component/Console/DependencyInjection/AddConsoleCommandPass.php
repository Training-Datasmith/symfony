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

use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\Lazy_Command;
use Symfony\Component\Console\Command_Loader\Container_Command_Loader;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Service_Locator_Tag_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Typed_Reference;
/**
 * Registers console commands.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Add_Console_Command_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        $command_services = [];
        $lazy_command_map = [];
        $lazy_command_refs = [];
        $service_ids = [];
        foreach ($container->find_tagged_service_ids('console.command', true) as $id => $tags) {
            foreach ($tags as $tag) {
                $command_services[$id][$tag['method'] ?? '__invoke'][] = $tag;
            }
        }
        foreach ($command_services as $id => $commands) {
            $definition = $container->get_definition($id);
            $class = $container->get_parameter_bag()->resolve_value($definition->get_class());
            if (!$r = $container->get_reflection_class($class)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            foreach ($commands as $tags) {
                $this->register_command($container, $r, $id, $class, $tags, $definition, $service_ids, $lazy_command_map, $lazy_command_refs);
            }
        }
        $container->register('console.command_loader', Container_Command_Loader::class)->set_public(true)->add_tag('container.no_preload')->set_arguments([Service_Locator_Tag_Pass::register($container, $lazy_command_refs), $lazy_command_map]);
        $container->set_parameter('console.command.ids', $service_ids);
    }
    private function register_command(Container_Builder $container, \ReflectionClass $reflection, string $id, string $class, array $tags, Definition $definition, array &$service_ids, array &$lazy_command_map, array &$lazy_command_refs): void
    {
        if (!$reflection->is_subclass_of(Command::class)) {
            $method = $tags[0]['method'] ?? '__invoke';
            if (!$reflection->has_method($method)) {
                throw new InvalidArgumentException(\sprintf('The service "%s" tagged "%s" must either be a subclass of "%s" or have an "%s()" method.', $id, 'console.command', Command::class, $method));
            }
            $reflection = $reflection->get_method($method);
            if (!$reflection->is_public() || $reflection->is_static()) {
                throw new InvalidArgumentException(\sprintf('The method "%s::%s()" must be public and non-static to be used as a console command.', $class, $method));
            }
            if ('__invoke' === $method) {
                $callable_ref = new Reference($id);
                $id .= '.command';
            } else {
                $callable_ref = [new Reference($id), $method];
                $id .= '.' . $method . '.command';
            }
            $class = Command::class;
            $closure_definition = (new Definition(\Closure::class))->set_factory(\Closure::from_callable(...))->set_arguments([$callable_ref]);
            $definition = $container->register($id, $class)->add_method_call('setCode', [$closure_definition]);
        } elseif (isset($tags[0]['method'])) {
            throw new InvalidArgumentException(\sprintf('The service "%s" tagged "console.command" cannot define a method command when it is a subclass of "%s".', $id, Command::class));
        }
        $definition->add_tag('container.no_preload');
        $attribute = $this->get_command_attribute($reflection);
        $default_name = $attribute?->name;
        $aliases = str_replace('%', '%%', $tags[0]['command'] ?? $default_name ?? '');
        $aliases = explode('|', $aliases);
        $command_name = array_shift($aliases);
        if ($is_hidden = '' === $command_name) {
            $command_name = array_shift($aliases);
        }
        if (null === $command_name) {
            if ($definition->is_private() || $definition->has_tag('container.private')) {
                $command_id = 'console.command.public_alias.' . $id;
                $container->set_alias($command_id, $id)->set_public(true);
                $id = $command_id;
            }
            $service_ids[] = $id;
            return;
        }
        $description = $tags[0]['description'] ?? null;
        $help = $tags[0]['help'] ?? null;
        $usages = $tags[0]['usages'] ?? null;
        unset($tags[0]);
        $lazy_command_map[$command_name] = $id;
        $lazy_command_refs[$id] = new Typed_Reference($id, $class);
        foreach ($aliases as $alias) {
            $lazy_command_map[$alias] = $id;
        }
        foreach ($tags as $tag) {
            if (isset($tag['command'])) {
                $aliases[] = $tag['command'];
                $lazy_command_map[$tag['command']] = $id;
            }
            $description ??= $tag['description'] ?? null;
            $help ??= $tag['help'] ?? null;
            $usages ??= $tag['usages'] ?? null;
        }
        $definition->add_method_call('setName', [$command_name]);
        if ($aliases) {
            $definition->add_method_call('setAliases', [$aliases]);
        }
        if ($is_hidden) {
            $definition->add_method_call('setHidden', [true]);
        }
        if ($help ??= $attribute?->help) {
            $definition->add_method_call('setHelp', [str_replace('%', '%%', $help)]);
        }
        if ($usages ??= $attribute?->usages) {
            foreach ($usages as $usage) {
                $definition->add_method_call('addUsage', [$usage]);
            }
        }
        if ($description ??= $attribute?->description) {
            $escaped_description = str_replace('%', '%%', $description);
            $definition->add_method_call('setDescription', [$escaped_description]);
            $container->register('.' . $id . '.lazy', Lazy_Command::class)->set_arguments([$command_name, $aliases, $escaped_description, $is_hidden, new Service_Closure_Argument($lazy_command_refs[$id])]);
            $lazy_command_refs[$id] = new Reference('.' . $id . '.lazy');
        }
    }
    private function get_command_attribute(\ReflectionClass|\ReflectionMethod $reflection): ?As_Command
    {
        /** @var AsCommand|null $attribute */
        if ($attribute = ($reflection->get_attributes(As_Command::class)[0] ?? null)?->new_instance()) {
            return $attribute;
        }
        if ($reflection instanceof \ReflectionMethod && '__invoke' === $reflection->get_name()) {
            return ($reflection->get_declaring_class()->get_attributes(As_Command::class)[0] ?? null)?->new_instance();
        }
        return null;
    }
}