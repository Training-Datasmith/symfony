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
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Markdown descriptor.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 *
 * @internal
 */
class Markdown_Descriptor extends Descriptor
{
    public function describe(Output_Interface $output, object $object, array $options = []): void
    {
        $decorated = $output->is_decorated();
        $output->set_decorated(false);
        parent::describe($output, $object, $options);
        $output->set_decorated($decorated);
    }
    protected function write(string $content, bool $decorated = true): void
    {
        parent::write($content, $decorated);
    }
    protected function describe_input_argument(Input_Argument $argument, array $options = []): void
    {
        $this->write('#### `' . ($argument->get_name() ?: '<none>') . "`\n\n" . ($argument->get_description() ? preg_replace('/\s*[\r\n]\s*/', "\n", $argument->get_description()) . "\n\n" : '') . '* Is required: ' . ($argument->is_required() ? 'yes' : 'no') . "\n" . '* Is array: ' . ($argument->is_array() ? 'yes' : 'no') . "\n" . '* Default: `' . str_replace("\n", '', var_export($argument->get_default(), true)) . '`');
    }
    protected function describe_input_option(Input_Option $option, array $options = []): void
    {
        $name = '--' . $option->get_name();
        if ($option->is_negatable()) {
            $name .= '|--no-' . $option->get_name();
        }
        if ($option->get_shortcut()) {
            $name .= '|-' . str_replace('|', '|-', $option->get_shortcut()) . '';
        }
        $this->write('#### `' . $name . '`' . "\n\n" . ($option->get_description() ? preg_replace('/\s*[\r\n]\s*/', "\n", $option->get_description()) . "\n\n" : '') . '* Accept value: ' . ($option->accept_value() ? 'yes' : 'no') . "\n" . '* Is value required: ' . ($option->is_value_required() ? 'yes' : 'no') . "\n" . '* Is multiple: ' . ($option->is_array() ? 'yes' : 'no') . "\n" . '* Is negatable: ' . ($option->is_negatable() ? 'yes' : 'no') . "\n" . '* Default: `' . str_replace("\n", '', var_export($option->get_default(), true)) . '`');
    }
    protected function describe_input_definition(Input_Definition $definition, array $options = []): void
    {
        if ($show_arguments = \count($definition->get_arguments()) > 0) {
            $this->write('### Arguments');
            foreach ($definition->get_arguments() as $argument) {
                $this->write("\n\n");
                $this->describe_input_argument($argument);
            }
        }
        if (\count($definition->get_options()) > 0) {
            if ($show_arguments) {
                $this->write("\n\n");
            }
            $this->write('### Options');
            foreach ($definition->get_options() as $option) {
                $this->write("\n\n");
                $this->describe_input_option($option);
            }
        }
    }
    protected function describe_command(Command $command, array $options = []): void
    {
        if ($options['short'] ?? false) {
            $this->write('`' . $command->get_name() . "`\n" . str_repeat('-', Helper::width($command->get_name()) + 2) . "\n\n" . ($command->get_description() ? $command->get_description() . "\n\n" : '') . '### Usage' . "\n\n" . array_reduce($command->get_aliases(), static fn($carry, $usage): string => $carry . '* `' . $usage . '`' . "\n"));
            return;
        }
        $command->merge_application_definition(false);
        $this->write('`' . $command->get_name() . "`\n" . str_repeat('-', Helper::width($command->get_name()) + 2) . "\n\n" . ($command->get_description() ? $command->get_description() . "\n\n" : '') . '### Usage' . "\n\n" . array_reduce(array_merge([$command->get_synopsis()], $command->get_aliases(), $command->get_usages()), static fn($carry, $usage): string => $carry . '* `' . $usage . '`' . "\n"));
        if ($help = $command->get_processed_help()) {
            $this->write("\n");
            $this->write($help);
        }
        $definition = $command->get_definition();
        if ($definition->get_options() || $definition->get_arguments()) {
            $this->write("\n\n");
            $this->describe_input_definition($definition);
        }
    }
    protected function describe_application(Application $application, array $options = []): void
    {
        $described_namespace = $options['namespace'] ?? null;
        $description = new Application_Description($application, $described_namespace);
        $title = $this->get_application_title($application);
        $this->write($title . "\n" . str_repeat('=', Helper::width($title)));
        foreach ($description->get_namespaces() as $namespace) {
            if (Application_Description::GLOBAL_NAMESPACE !== $namespace['id']) {
                $this->write("\n\n");
                $this->write('**' . $namespace['id'] . ':**');
            }
            $this->write("\n\n");
            $this->write(implode("\n", array_map(static fn(string $command_name): string => \sprintf('* [`%s`](#%s)', $command_name, str_replace(':', '', $description->get_command($command_name)->get_name())), $namespace['commands'])));
        }
        foreach ($description->get_commands() as $command) {
            $this->write("\n\n");
            $this->describe_command($command, $options);
        }
    }
    private function get_application_title(Application $application): string
    {
        if ('UNKNOWN' !== $application->get_name()) {
            if ('UNKNOWN' !== $application->get_version()) {
                return \sprintf('%s %s', $application->get_name(), $application->get_version());
            }
            return $application->get_name();
        }
        return 'Console Tool';
    }
}