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
 * JSON descriptor.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 *
 * @internal
 */
class Json_Descriptor extends Descriptor
{
    protected function describe_input_argument(Input_Argument $argument, array $options = []): void
    {
        $this->write_data($this->get_input_argument_data($argument), $options);
    }
    protected function describe_input_option(Input_Option $option, array $options = []): void
    {
        $this->write_data($this->get_input_option_data($option), $options);
        if ($option->is_negatable()) {
            $this->write_data($this->get_input_option_data($option, true), $options);
        }
    }
    protected function describe_input_definition(Input_Definition $definition, array $options = []): void
    {
        $this->write_data($this->get_input_definition_data($definition), $options);
    }
    protected function describe_command(Command $command, array $options = []): void
    {
        $this->write_data($this->get_command_data($command, $options['short'] ?? false), $options);
    }
    protected function describe_application(Application $application, array $options = []): void
    {
        $described_namespace = $options['namespace'] ?? null;
        $description = new Application_Description($application, $described_namespace, true);
        $commands = [];
        foreach ($description->get_commands() as $command) {
            $commands[] = $this->get_command_data($command, $options['short'] ?? false);
        }
        $data = [];
        if ('UNKNOWN' !== $application->get_name()) {
            $data['application']['name'] = $application->get_name();
            if ('UNKNOWN' !== $application->get_version()) {
                $data['application']['version'] = $application->get_version();
            }
        }
        $data['commands'] = $commands;
        if ($described_namespace) {
            $data['namespace'] = $described_namespace;
        } else {
            $data['namespaces'] = array_values($description->get_namespaces());
        }
        $this->write_data($data, $options);
    }
    /**
     * Writes data as json.
     */
    private function write_data(array $data, array $options): void
    {
        $flags = $options['json_encoding'] ?? 0;
        $this->write(json_encode($data, $flags));
    }
    private function get_input_argument_data(Input_Argument $argument): array
    {
        return ['name' => $argument->get_name(), 'is_required' => $argument->is_required(), 'is_array' => $argument->is_array(), 'description' => preg_replace('/\s*[\r\n]\s*/', ' ', $argument->get_description()), 'default' => \INF === $argument->get_default() ? 'INF' : $argument->get_default()];
    }
    private function get_input_option_data(Input_Option $option, bool $negated = false): array
    {
        return $negated ? ['name' => '--no-' . $option->get_name(), 'shortcut' => '', 'accept_value' => false, 'is_value_required' => false, 'is_multiple' => false, 'description' => 'Negate the "--' . $option->get_name() . '" option', 'default' => null === $option->get_default() ? null : !$option->get_default()] : ['name' => '--' . $option->get_name(), 'shortcut' => $option->get_shortcut() ? '-' . str_replace('|', '|-', $option->get_shortcut()) : '', 'accept_value' => $option->accept_value(), 'is_value_required' => $option->is_value_required(), 'is_multiple' => $option->is_array(), 'description' => preg_replace('/\s*[\r\n]\s*/', ' ', $option->get_description()), 'default' => \INF === $option->get_default() ? 'INF' : $option->get_default()];
    }
    private function get_input_definition_data(Input_Definition $definition): array
    {
        $input_arguments = [];
        foreach ($definition->get_arguments() as $name => $argument) {
            $input_arguments[$name] = $this->get_input_argument_data($argument);
        }
        $input_options = [];
        foreach ($definition->get_options() as $name => $option) {
            $input_options[$name] = $this->get_input_option_data($option);
            if ($option->is_negatable()) {
                $input_options['no-' . $name] = $this->get_input_option_data($option, true);
            }
        }
        return ['arguments' => $input_arguments, 'options' => $input_options];
    }
    private function get_command_data(Command $command, bool $short = false): array
    {
        $data = ['name' => $command->get_name(), 'description' => $command->get_description()];
        if ($short) {
            $data += ['usage' => $command->get_aliases()];
        } else {
            $command->merge_application_definition(false);
            $data += ['usage' => array_merge([$command->get_synopsis()], $command->get_usages(), $command->get_aliases()), 'help' => $command->get_processed_help(), 'definition' => $this->get_input_definition_data($command->get_definition())];
        }
        $data['hidden'] = $command->is_hidden();
        return $data;
    }
}