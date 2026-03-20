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
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Option;
/**
 * Text descriptor.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 *
 * @internal
 */
class Text_Descriptor extends Descriptor
{
    protected function describe_input_argument(Input_Argument $argument, array $options = []): void
    {
        if (null !== $argument->get_default() && (!\is_array($argument->get_default()) || \count($argument->get_default()))) {
            $default = \sprintf('<comment> [default: %s]</comment>', $this->format_default_value($argument->get_default()));
        } else {
            $default = '';
        }
        $total_width = $options['total_width'] ?? Helper::width($argument->get_name());
        $spacing_width = $total_width - \strlen($argument->get_name());
        $this->write_text(\sprintf(
            '  <info>%s</info>  %s%s%s',
            $argument->get_name(),
            str_repeat(' ', $spacing_width),
            // + 4 = 2 spaces before <info>, 2 spaces after </info>
            preg_replace('/\s*[\r\n]\s*/', "\n" . str_repeat(' ', $total_width + 4), $argument->get_description()),
            $default
        ), $options);
    }
    protected function describe_input_option(Input_Option $option, array $options = []): void
    {
        if ($option->accept_value() && null !== $option->get_default() && (!\is_array($option->get_default()) || \count($option->get_default()))) {
            $default = \sprintf('<comment> [default: %s]</comment>', $this->format_default_value($option->get_default()));
        } else {
            $default = '';
        }
        $value = '';
        if ($option->accept_value()) {
            $value = '=' . strtoupper($option->get_name());
            if ($option->is_value_optional()) {
                $value = '[' . $value . ']';
            }
        }
        $total_width = $options['total_width'] ?? $this->calculate_total_width_for_options([$option]);
        $synopsis = \sprintf('%s%s', $option->get_shortcut() ? \sprintf('-%s, ', $option->get_shortcut()) : '    ', \sprintf($option->is_negatable() ? '--%1$s|--no-%1$s' : '--%1$s%2$s', $option->get_name(), $value));
        $spacing_width = $total_width - Helper::width($synopsis);
        $this->write_text(\sprintf(
            '  <info>%s</info>  %s%s%s%s',
            $synopsis,
            str_repeat(' ', $spacing_width),
            // + 4 = 2 spaces before <info>, 2 spaces after </info>
            preg_replace('/\s*[\r\n]\s*/', "\n" . str_repeat(' ', $total_width + 4), $option->get_description()),
            $default,
            $option->is_array() ? '<comment> (multiple values allowed)</comment>' : ''
        ), $options);
    }
    protected function describe_input_definition(Input_Definition $definition, array $options = []): void
    {
        $total_width = $this->calculate_total_width_for_options($definition->get_options());
        foreach ($definition->get_arguments() as $argument) {
            $total_width = max($total_width, Helper::width($argument->get_name()));
        }
        if ($definition->get_arguments()) {
            $this->write_text('<comment>Arguments:</comment>', $options);
            $this->write_text("\n");
            foreach ($definition->get_arguments() as $argument) {
                $this->describe_input_argument($argument, array_merge($options, ['total_width' => $total_width]));
                $this->write_text("\n");
            }
        }
        if ($definition->get_arguments() && $definition->get_options()) {
            $this->write_text("\n");
        }
        if ($definition->get_options()) {
            $later_options = [];
            $this->write_text('<comment>Options:</comment>', $options);
            foreach ($definition->get_options() as $option) {
                if (\strlen($option->get_shortcut() ?? '') > 1) {
                    $later_options[] = $option;
                    continue;
                }
                $this->write_text("\n");
                $this->describe_input_option($option, array_merge($options, ['total_width' => $total_width]));
            }
            foreach ($later_options as $option) {
                $this->write_text("\n");
                $this->describe_input_option($option, array_merge($options, ['total_width' => $total_width]));
            }
        }
    }
    protected function describe_command(Command $command, array $options = []): void
    {
        $command->merge_application_definition(false);
        if ($description = $command->get_description()) {
            $this->write_text('<comment>Description:</comment>', $options);
            $this->write_text("\n");
            $this->write_text('  ' . $description);
            $this->write_text("\n\n");
        }
        $this->write_text('<comment>Usage:</comment>', $options);
        foreach (array_merge([$command->get_synopsis(true)], $command->get_aliases(), $command->get_usages()) as $usage) {
            $this->write_text("\n");
            $this->write_text('  ' . Output_Formatter::escape($usage), $options);
        }
        $this->write_text("\n");
        $definition = $command->get_definition();
        if ($definition->get_options() || $definition->get_arguments()) {
            $this->write_text("\n");
            $this->describe_input_definition($definition, $options);
            $this->write_text("\n");
        }
        $help = $command->get_processed_help();
        if ($help && $help !== $description) {
            $this->write_text("\n");
            $this->write_text('<comment>Help:</comment>', $options);
            $this->write_text("\n");
            $this->write_text('  ' . str_replace("\n", "\n  ", $help), $options);
            $this->write_text("\n");
        }
    }
    protected function describe_application(Application $application, array $options = []): void
    {
        $described_namespace = $options['namespace'] ?? null;
        $description = new Application_Description($application, $described_namespace);
        if (isset($options['raw_text']) && $options['raw_text']) {
            $width = $this->get_column_width($description->get_commands());
            foreach ($description->get_commands() as $command) {
                $this->write_text(\sprintf("%-{$width}s %s", $command->get_name(), $command->get_description()), $options);
                $this->write_text("\n");
            }
        } else {
            if ('' != $help = $application->get_help()) {
                $this->write_text("{$help}\n\n", $options);
            }
            $this->write_text("<comment>Usage:</comment>\n", $options);
            $this->write_text("  command [options] [arguments]\n\n", $options);
            $this->describe_input_definition(new Input_Definition($application->get_definition()->get_options()), $options);
            $this->write_text("\n");
            $this->write_text("\n");
            $commands = $description->get_commands();
            $namespaces = $description->get_namespaces();
            if ($described_namespace && $namespaces) {
                // make sure all alias commands are included when describing a specific namespace
                $described_namespace_info = reset($namespaces);
                foreach ($described_namespace_info['commands'] as $name) {
                    $commands[$name] = $description->get_command($name);
                }
            }
            // calculate max. width based on available commands per namespace
            $width = $this->get_column_width(array_merge(...array_values(array_map(static fn(array $namespace): array => array_intersect($namespace['commands'], array_keys($commands)), array_values($namespaces)))));
            if ($described_namespace) {
                $this->write_text(\sprintf('<comment>Available commands for the "%s" namespace:</comment>', $described_namespace), $options);
            } else {
                $this->write_text('<comment>Available commands:</comment>', $options);
            }
            foreach ($namespaces as $namespace) {
                $namespace['commands'] = array_filter($namespace['commands'], static fn($name): bool => isset($commands[$name]));
                if (!$namespace['commands']) {
                    continue;
                }
                if (!$described_namespace && Application_Description::GLOBAL_NAMESPACE !== $namespace['id']) {
                    $this->write_text("\n");
                    $this->write_text(' <comment>' . $namespace['id'] . '</comment>', $options);
                }
                foreach ($namespace['commands'] as $name) {
                    $this->write_text("\n");
                    $spacing_width = $width - Helper::width($name);
                    $command = $commands[$name];
                    $command_aliases = $name === $command->get_name() ? $this->get_command_aliases_text($command) : '';
                    $this->write_text(\sprintf('  <info>%s</info>%s%s', $name, str_repeat(' ', $spacing_width), $command_aliases . $command->get_description()), $options);
                }
            }
            $this->write_text("\n");
        }
    }
    private function write_text(string $content, array $options = []): void
    {
        $this->write(isset($options['raw_text']) && $options['raw_text'] ? strip_tags($content) : $content, isset($options['raw_output']) ? !$options['raw_output'] : true);
    }
    /**
     * Formats command aliases to show them in the command description.
     */
    private function get_command_aliases_text(Command $command): string
    {
        $text = '';
        $aliases = $command->get_aliases();
        if ($aliases) {
            return '[' . implode('|', $aliases) . '] ';
        }
        return $text;
    }
    /**
     * Formats input option/argument default value.
     */
    private function format_default_value(mixed $default): string
    {
        if (\INF === $default) {
            return 'INF';
        }
        if (\is_string($default)) {
            $default = Output_Formatter::escape($default);
        } elseif (\is_array($default)) {
            foreach ($default as $key => $value) {
                if (\is_string($value)) {
                    $default[$key] = Output_Formatter::escape($value);
                }
            }
        }
        return str_replace('\\\\', '\\', json_encode($default, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    }
    /**
     * @param array<Command|string> $commands
     */
    private function get_column_width(array $commands): int
    {
        $widths = [];
        foreach ($commands as $command) {
            if ($command instanceof Command) {
                $widths[] = Helper::width($command->get_name());
                foreach ($command->get_aliases() as $alias) {
                    $widths[] = Helper::width($alias);
                }
            } else {
                $widths[] = Helper::width($command);
            }
        }
        return $widths ? max($widths) + 2 : 0;
    }
    /**
     * @param InputOption[] $options
     */
    private function calculate_total_width_for_options(array $options): int
    {
        $total_width = 0;
        foreach ($options as $option) {
            // "-" + shortcut + ", --" + name
            $name_length = 1 + max(Helper::width($option->get_shortcut()), 1) + 4 + Helper::width($option->get_name());
            if ($option->is_negatable()) {
                $name_length += 6 + Helper::width($option->get_name());
                // |--no- + name
            } elseif ($option->accept_value()) {
                $value_length = 1 + Helper::width($option->get_name());
                // = + value
                $value_length += $option->is_value_optional() ? 2 : 0;
                // [ + ]
                $name_length += $value_length;
            }
            $total_width = max($total_width, $name_length);
        }
        return $total_width;
    }
}