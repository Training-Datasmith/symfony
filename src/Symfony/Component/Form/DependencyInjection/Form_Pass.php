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
namespace Symfony\Component\Form\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Argument\Argument_Interface;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Priority_Tagged_Service_Trait;
use Symfony\Component\Dependency_Injection\Compiler\Service_Locator_Tag_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Adds all services with the tags "form.type", "form.type_extension" and
 * "form.type_guesser" as arguments of the "form.extension" service.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form_Pass implements Compiler_Pass_Interface
{
    use Priority_Tagged_Service_Trait;
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('form.extension')) {
            return;
        }
        $definition = $container->get_definition('form.extension');
        $definition->replace_argument(0, $this->process_form_types($container));
        $definition->replace_argument(1, $this->process_form_type_extensions($container));
        $definition->replace_argument(2, $this->process_form_type_guessers($container));
    }
    private function process_form_types(Container_Builder $container): Reference
    {
        // Get service locator argument
        $services_map = [];
        $namespaces = ['Symfony\Component\Form\Extension\Core\Type' => true];
        $csrf_token_ids = [];
        // Builds an array with fully-qualified type class names as keys and service IDs as values
        foreach ($container->find_tagged_service_ids('form.type', true) as $service_id => $tag) {
            // Add form type service to the service locator
            $service_definition = $container->get_definition($service_id);
            $services_map[$form_type = $service_definition->get_class()] = new Reference($service_id);
            $namespaces[substr((string) $form_type, 0, strrpos((string) $form_type, '\\') ?: \strlen((string) $form_type))] = true;
            if (isset($tag[0]['csrf_token_id'])) {
                $csrf_token_ids[$form_type] = $tag[0]['csrf_token_id'];
            }
        }
        if ($container->has_definition('console.command.form_debug')) {
            $command_definition = $container->get_definition('console.command.form_debug');
            $command_definition->set_argument(1, array_keys($namespaces));
            $command_definition->set_argument(2, array_keys($services_map));
        }
        if ($csrf_token_ids && $container->has_definition('form.type_extension.csrf')) {
            $csrf_extension = $container->get_definition('form.type_extension.csrf');
            if (8 <= \count($csrf_extension->get_arguments())) {
                $csrf_extension->replace_argument(7, $csrf_token_ids);
            }
        }
        return Service_Locator_Tag_Pass::register($container, $services_map);
    }
    private function process_form_type_extensions(Container_Builder $container): array
    {
        $type_extensions = [];
        $type_extensions_classes = [];
        foreach ($this->find_and_sort_tagged_services('form.type_extension', $container) as $reference) {
            $service_id = (string) $reference;
            $service_definition = $container->get_definition($service_id);
            $tag = $service_definition->get_tag('form.type_extension');
            $type_extension_class = $container->get_parameter_bag()->resolve_value($service_definition->get_class());
            if (isset($tag[0]['extended_type'])) {
                $type_extensions[$tag[0]['extended_type']][] = new Reference($service_id);
                $type_extensions_classes[] = $type_extension_class;
            } else {
                $extends_types = false;
                $type_extensions_classes[] = $type_extension_class;
                $container->get_reflection_class($type_extension_class);
                foreach ($type_extension_class::get_extended_types() as $extended_type) {
                    $type_extensions[$extended_type][] = new Reference($service_id);
                    $extends_types = true;
                }
                if (!$extends_types) {
                    throw new InvalidArgumentException(\sprintf('The getExtendedTypes() method for service "%s" does not return any extended types.', $service_id));
                }
            }
        }
        foreach ($type_extensions as $extended_type => $extensions) {
            $type_extensions[$extended_type] = new Iterator_Argument($extensions);
        }
        if ($container->has_definition('console.command.form_debug')) {
            $command_definition = $container->get_definition('console.command.form_debug');
            $command_definition->set_argument(3, $type_extensions_classes);
        }
        return $type_extensions;
    }
    private function process_form_type_guessers(Container_Builder $container): Argument_Interface
    {
        $guessers = [];
        $guessers_classes = [];
        foreach ($container->find_tagged_service_ids('form.type_guesser', true) as $service_id => $tags) {
            $guessers[] = new Reference($service_id);
            $service_definition = $container->get_definition($service_id);
            $guessers_classes[] = $service_definition->get_class();
        }
        if ($container->has_definition('console.command.form_debug')) {
            $command_definition = $container->get_definition('console.command.form_debug');
            $command_definition->set_argument(4, $guessers_classes);
        }
        return new Iterator_Argument($guessers);
    }
}