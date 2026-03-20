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
namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Option;
/**
 * XML descriptor.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 *
 * @internal
 */
class Xml_Descriptor extends Descriptor
{
    public function get_input_definition_document(Input_Definition $definition): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($definition_xml = $dom->create_element('definition'));
        $definition_xml->append_child($arguments_xml = $dom->create_element('arguments'));
        foreach ($definition->get_arguments() as $argument) {
            $this->append_document($arguments_xml, $this->get_input_argument_document($argument));
        }
        $definition_xml->append_child($options_xml = $dom->create_element('options'));
        foreach ($definition->get_options() as $option) {
            $this->append_document($options_xml, $this->get_input_option_document($option));
        }
        return $dom;
    }
    public function get_command_document(Command $command, bool $short = false): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($command_xml = $dom->create_element('command'));
        $command_xml->set_attribute('id', $command->get_name());
        $command_xml->set_attribute('name', $command->get_name());
        $command_xml->set_attribute('hidden', $command->is_hidden() ? 1 : 0);
        $command_xml->append_child($usages_xml = $dom->create_element('usages'));
        $command_xml->append_child($description_xml = $dom->create_element('description'));
        $description_xml->append_child($dom->create_text_node(str_replace("\n", "\n ", $command->get_description())));
        if ($short) {
            foreach ($command->get_aliases() as $usage) {
                $usages_xml->append_child($dom->create_element('usage', $usage));
            }
        } else {
            $command->merge_application_definition(false);
            foreach (array_merge([$command->get_synopsis()], $command->get_aliases(), $command->get_usages()) as $usage) {
                $usages_xml->append_child($dom->create_element('usage', $usage));
            }
            $command_xml->append_child($help_xml = $dom->create_element('help'));
            $help_xml->append_child($dom->create_text_node(str_replace("\n", "\n ", $command->get_processed_help())));
            $definition_xml = $this->get_input_definition_document($command->get_definition());
            $this->append_document($command_xml, $definition_xml->get_elements_by_tag_name('definition')->item(0));
        }
        return $dom;
    }
    public function get_application_document(Application $application, ?string $namespace = null, bool $short = false): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($root_xml = $dom->create_element('symfony'));
        if ('UNKNOWN' !== $application->get_name()) {
            $root_xml->set_attribute('name', $application->get_name());
            if ('UNKNOWN' !== $application->get_version()) {
                $root_xml->set_attribute('version', $application->get_version());
            }
        }
        $root_xml->append_child($commands_xml = $dom->create_element('commands'));
        $description = new Application_Description($application, $namespace, true);
        if ($namespace) {
            $commands_xml->set_attribute('namespace', $namespace);
        }
        foreach ($description->get_commands() as $command) {
            $this->append_document($commands_xml, $this->get_command_document($command, $short));
        }
        if (!$namespace) {
            $root_xml->append_child($namespaces_xml = $dom->create_element('namespaces'));
            foreach ($description->get_namespaces() as $namespace_description) {
                $namespaces_xml->append_child($namespace_array_xml = $dom->create_element('namespace'));
                $namespace_array_xml->set_attribute('id', $namespace_description['id']);
                foreach ($namespace_description['commands'] as $name) {
                    $namespace_array_xml->append_child($command_xml = $dom->create_element('command'));
                    $command_xml->append_child($dom->create_text_node($name));
                }
            }
        }
        return $dom;
    }
    protected function describe_input_argument(Input_Argument $argument, array $options = []): void
    {
        $this->write_document($this->get_input_argument_document($argument));
    }
    protected function describe_input_option(Input_Option $option, array $options = []): void
    {
        $this->write_document($this->get_input_option_document($option));
    }
    protected function describe_input_definition(Input_Definition $definition, array $options = []): void
    {
        $this->write_document($this->get_input_definition_document($definition));
    }
    protected function describe_command(Command $command, array $options = []): void
    {
        $this->write_document($this->get_command_document($command, $options['short'] ?? false));
    }
    protected function describe_application(Application $application, array $options = []): void
    {
        $this->write_document($this->get_application_document($application, $options['namespace'] ?? null, $options['short'] ?? false));
    }
    /**
     * Appends document children to parent node.
     */
    private function append_document(\Dom_Node $parent_node, \Dom_Node $imported_parent): void
    {
        foreach ($imported_parent->child_nodes as $child_node) {
            $parent_node->append_child($parent_node->owner_document->import_node($child_node, true));
        }
    }
    /**
     * Writes DOM document.
     */
    private function write_document(\Dom_Document $dom): void
    {
        $dom->format_output = true;
        $this->write($dom->save_xml());
    }
    private function get_input_argument_document(Input_Argument $argument): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($object_xml = $dom->create_element('argument'));
        $object_xml->set_attribute('name', $argument->get_name());
        $object_xml->set_attribute('is_required', $argument->is_required() ? 1 : 0);
        $object_xml->set_attribute('is_array', $argument->is_array() ? 1 : 0);
        $object_xml->append_child($description_xml = $dom->create_element('description'));
        $description_xml->append_child($dom->create_text_node($argument->get_description()));
        $object_xml->append_child($defaults_xml = $dom->create_element('defaults'));
        $defaults = \is_array($argument->get_default()) ? $argument->get_default() : (\is_bool($argument->get_default()) ? [var_export($argument->get_default(), true)] : ($argument->get_default() ? [$argument->get_default()] : []));
        foreach ($defaults as $default) {
            $defaults_xml->append_child($default_xml = $dom->create_element('default'));
            $default_xml->append_child($dom->create_text_node($default));
        }
        return $dom;
    }
    private function get_input_option_document(Input_Option $option): \Dom_Document
    {
        $dom = new \Dom_Document('1.0', 'UTF-8');
        $dom->append_child($object_xml = $dom->create_element('option'));
        $object_xml->set_attribute('name', '--' . $option->get_name());
        $pos = strpos($option->get_shortcut() ?? '', '|');
        if (false !== $pos) {
            $object_xml->set_attribute('shortcut', '-' . substr((string) $option->get_shortcut(), 0, $pos));
            $object_xml->set_attribute('shortcuts', '-' . str_replace('|', '|-', $option->get_shortcut()));
        } else {
            $object_xml->set_attribute('shortcut', $option->get_shortcut() ? '-' . $option->get_shortcut() : '');
        }
        $object_xml->set_attribute('accept_value', $option->accept_value() ? 1 : 0);
        $object_xml->set_attribute('is_value_required', $option->is_value_required() ? 1 : 0);
        $object_xml->set_attribute('is_multiple', $option->is_array() ? 1 : 0);
        $object_xml->append_child($description_xml = $dom->create_element('description'));
        $description_xml->append_child($dom->create_text_node($option->get_description()));
        if ($option->accept_value()) {
            $defaults = \is_array($option->get_default()) ? $option->get_default() : (\is_bool($option->get_default()) ? [var_export($option->get_default(), true)] : ($option->get_default() ? [$option->get_default()] : []));
            $object_xml->append_child($defaults_xml = $dom->create_element('defaults'));
            foreach ($defaults as $default) {
                $defaults_xml->append_child($default_xml = $dom->create_element('default'));
                $default_xml->append_child($dom->create_text_node($default));
            }
        }
        if ($option->is_negatable()) {
            $dom->append_child($object_xml = $dom->create_element('option'));
            $object_xml->set_attribute('name', '--no-' . $option->get_name());
            $object_xml->set_attribute('shortcut', '');
            $object_xml->set_attribute('accept_value', 0);
            $object_xml->set_attribute('is_value_required', 0);
            $object_xml->set_attribute('is_multiple', 0);
            $object_xml->append_child($description_xml = $dom->create_element('description'));
            $description_xml->append_child($dom->create_text_node('Negate the "--' . $option->get_name() . '" option'));
        }
        return $dom;
    }
}