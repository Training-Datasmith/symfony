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
use Symfony\Component\String\Unicode_String;
class Re_Structured_Text_Descriptor extends Descriptor
{
    // <h1>
    private string $part_char = '=';
    // <h2>
    private string $chapter_char = '-';
    // <h3>
    private string $section_char = '~';
    // <h4>
    private string $subsection_char = '.';
    // <h5>
    private string $subsubsection_char = '^';
    // <h6>
    private string $paragraphs_char = '"';
    private array $visible_namespaces = [];
    public function describe(Output_Interface $output, object $object, array $options = []): void
    {
        $decorated = $output->is_decorated();
        $output->set_decorated(false);
        parent::describe($output, $object, $options);
        $output->set_decorated($decorated);
    }
    /**
     * Override parent method to set $decorated = true.
     */
    protected function write(string $content, bool $decorated = true): void
    {
        parent::write($content, $decorated);
    }
    protected function describe_input_argument(Input_Argument $argument, array $options = []): void
    {
        $this->write($argument->get_name() ?: '<none>' . "\n" . str_repeat($this->paragraphs_char, Helper::width($argument->get_name())) . "\n\n" . ($argument->get_description() ? preg_replace('/\s*[\r\n]\s*/', "\n", $argument->get_description()) . "\n\n" : '') . '- **Is required**: ' . ($argument->is_required() ? 'yes' : 'no') . "\n" . '- **Is array**: ' . ($argument->is_array() ? 'yes' : 'no') . "\n" . '- **Default**: ``' . str_replace("\n", '', var_export($argument->get_default(), true)) . '``');
    }
    protected function describe_input_option(Input_Option $option, array $options = []): void
    {
        $name = '\-\-' . $option->get_name();
        if ($option->is_negatable()) {
            $name .= '|\-\-no-' . $option->get_name();
        }
        if ($option->get_shortcut()) {
            $name .= '|-' . str_replace('|', '|-', $option->get_shortcut());
        }
        $option_description = $option->get_description() ? preg_replace('/\s*[\r\n]\s*/', "\n\n", $option->get_description()) . "\n\n" : '';
        $option_description = (new Unicode_String($option_description))->ascii();
        $this->write($name . "\n" . str_repeat($this->paragraphs_char, Helper::width($name)) . "\n\n" . $option_description . '- **Accept value**: ' . ($option->accept_value() ? 'yes' : 'no') . "\n" . '- **Is value required**: ' . ($option->is_value_required() ? 'yes' : 'no') . "\n" . '- **Is multiple**: ' . ($option->is_array() ? 'yes' : 'no') . "\n" . '- **Is negatable**: ' . ($option->is_negatable() ? 'yes' : 'no') . "\n" . '- **Default**: ``' . str_replace("\n", '', var_export($option->get_default(), true)) . '``' . "\n");
    }
    protected function describe_input_definition(Input_Definition $definition, array $options = []): void
    {
        if ($show_arguments = (bool) $definition->get_arguments()) {
            $this->write("Arguments\n" . str_repeat($this->subsubsection_char, 9));
            foreach ($definition->get_arguments() as $argument) {
                $this->write("\n\n");
                $this->describe_input_argument($argument);
            }
        }
        if ($non_default_options = $this->get_non_default_options($definition)) {
            if ($show_arguments) {
                $this->write("\n\n");
            }
            $this->write("Options\n" . str_repeat($this->subsubsection_char, 7) . "\n\n");
            foreach ($non_default_options as $option) {
                $this->describe_input_option($option);
                $this->write("\n");
            }
        }
    }
    protected function describe_command(Command $command, array $options = []): void
    {
        if ($options['short'] ?? false) {
            $this->write('``' . $command->get_name() . "``\n" . str_repeat($this->subsection_char, Helper::width($command->get_name())) . "\n\n" . ($command->get_description() ? $command->get_description() . "\n\n" : '') . "Usage\n" . str_repeat($this->paragraphs_char, 5) . "\n\n" . array_reduce($command->get_aliases(), static fn($carry, $usage): string => $carry . '- ``' . $usage . '``' . "\n"));
            return;
        }
        $command->merge_application_definition(false);
        foreach ($command->get_aliases() as $alias) {
            $this->write('.. _' . $alias . ":\n\n");
        }
        $this->write($command->get_name() . "\n" . str_repeat($this->subsection_char, Helper::width($command->get_name())) . "\n\n" . ($command->get_description() ? $command->get_description() . "\n\n" : '') . "Usage\n" . str_repeat($this->subsubsection_char, 5) . "\n\n" . array_reduce(array_merge([$command->get_synopsis()], $command->get_aliases(), $command->get_usages()), static fn($carry, $usage): string => $carry . '- ``' . $usage . '``' . "\n"));
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
        $description = new Application_Description($application, $options['namespace'] ?? null);
        $title = $this->get_application_title($application);
        $this->write($title . "\n" . str_repeat($this->part_char, Helper::width($title)));
        $this->create_table_of_contents($description, $application);
        $this->describe_commands($application, $options);
    }
    private function get_application_title(Application $application): string
    {
        if ('UNKNOWN' === $application->get_name()) {
            return 'Console Tool';
        }
        if ('UNKNOWN' !== $application->get_version()) {
            return \sprintf('%s %s', $application->get_name(), $application->get_version());
        }
        return $application->get_name();
    }
    private function describe_commands(\Symfony\Component\Console\Application $application, array $options): void
    {
        $title = 'Commands';
        $this->write("\n\n{$title}\n" . str_repeat($this->chapter_char, Helper::width($title)) . "\n\n");
        foreach ($this->visible_namespaces as $namespace) {
            if ('_global' === $namespace) {
                $commands = $application->all('');
                $this->write('Global' . "\n" . str_repeat($this->section_char, Helper::width('Global')) . "\n\n");
            } else {
                $commands = $application->all($namespace);
                $this->write($namespace . "\n" . str_repeat($this->section_char, Helper::width($namespace)) . "\n\n");
            }
            foreach ($this->remove_aliases_and_hidden_commands($commands) as $command) {
                $this->describe_command($command, $options);
                $this->write("\n\n");
            }
        }
    }
    private function create_table_of_contents(Application_Description $description, Application $application): void
    {
        $this->set_visible_namespaces($description);
        $chapter_title = 'Table of Contents';
        $this->write("\n\n{$chapter_title}\n" . str_repeat($this->chapter_char, Helper::width($chapter_title)) . "\n\n");
        foreach ($this->visible_namespaces as $namespace) {
            if ('_global' === $namespace) {
                $commands = $application->all('');
            } else {
                $commands = $application->all($namespace);
                $this->write("\n\n");
                $this->write($namespace . "\n" . str_repeat($this->section_char, Helper::width($namespace)) . "\n\n");
            }
            $commands = $this->remove_aliases_and_hidden_commands($commands);
            $this->write("\n\n");
            $this->write(implode("\n", array_map(static fn(int|string $command_name): string => \sprintf('- `%s`_', $command_name), array_keys($commands))));
        }
    }
    private function get_non_default_options(Input_Definition $definition): array
    {
        $global_options = ['help', 'silent', 'quiet', 'verbose', 'version', 'ansi', 'no-interaction'];
        $non_default_options = [];
        foreach ($definition->get_options() as $option) {
            // Skip global options.
            if (!\in_array($option->get_name(), $global_options, true)) {
                $non_default_options[] = $option;
            }
        }
        return $non_default_options;
    }
    private function set_visible_namespaces(Application_Description $description): void
    {
        $commands = $description->get_commands();
        foreach ($description->get_namespaces() as $namespace) {
            try {
                $namespace_commands = $namespace['commands'];
                foreach ($namespace_commands as $key => $command_name) {
                    if (!\array_key_exists($command_name, $commands)) {
                        // If the array key does not exist, then this is an alias.
                        unset($namespace_commands[$key]);
                    } elseif ($commands[$command_name]->is_hidden()) {
                        unset($namespace_commands[$key]);
                    }
                }
                if (!$namespace_commands) {
                    // If the namespace contained only aliases or hidden commands, skip the namespace.
                    continue;
                }
            } catch (\Exception) {
            }
            $this->visible_namespaces[] = $namespace['id'];
        }
    }
    private function remove_aliases_and_hidden_commands(array $commands): array
    {
        foreach ($commands as $key => $command) {
            if ($command->is_hidden() || \in_array($key, $command->get_aliases(), true)) {
                unset($commands[$key]);
            }
        }
        unset($commands['completion']);
        return $commands;
    }
}