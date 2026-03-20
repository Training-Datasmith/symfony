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
namespace Symfony\Component\Console\Data_Collector;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Debug\Cli_Request;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Signal_Registry\Signal_Map;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Data_Collector\Data_Collector;
use Symfony\Component\Var_Dumper\Cloner\Data;
/**
 * @internal
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
final class Command_Data_Collector extends Data_Collector
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        if (!$request instanceof Cli_Request) {
            return;
        }
        $command = $request->command;
        $application = $command->get_application();
        $this->data = ['command' => $command->invokable_command_info ?? $this->clone_var($command->command), 'exit_code' => $command->exit_code, 'interrupted_by_signal' => $command->interrupted_by_signal, 'duration' => $command->duration, 'max_memory_usage' => $command->max_memory_usage, 'verbosity_level' => match ($command->output->get_verbosity()) {
            Output_Interface::VERBOSITY_SILENT => 'silent',
            Output_Interface::VERBOSITY_QUIET => 'quiet',
            Output_Interface::VERBOSITY_NORMAL => 'normal',
            Output_Interface::VERBOSITY_VERBOSE => 'verbose',
            Output_Interface::VERBOSITY_VERY_VERBOSE => 'very verbose',
            Output_Interface::VERBOSITY_DEBUG => 'debug',
        }, 'interactive' => $command->is_interactive, 'validate_input' => !$command->ignore_validation, 'enabled' => $command->is_enabled(), 'visible' => !$command->is_hidden(), 'input' => $this->clone_var($command->input), 'output' => $this->clone_var($command->output), 'interactive_inputs' => array_map($this->clone_var(...), $command->interactive_inputs), 'signalable' => $command->get_subscribed_signals(), 'handled_signals' => $command->handled_signals, 'helper_set' => array_map($this->clone_var(...), iterator_to_array($command->get_helper_set()))];
        $base_definition = $application->get_definition();
        foreach ($command->arguments as $arg_name => $arg_value) {
            if ($base_definition->has_argument($arg_name)) {
                $this->data['application_inputs'][$arg_name] = $this->clone_var($arg_value);
            } else {
                $this->data['arguments'][$arg_name] = $this->clone_var($arg_value);
            }
        }
        foreach ($command->options as $opt_name => $opt_value) {
            if ($base_definition->has_option($opt_name)) {
                $this->data['application_inputs']['--' . $opt_name] = $this->clone_var($opt_value);
            } else {
                $this->data['options'][$opt_name] = $this->clone_var($opt_value);
            }
        }
    }
    public function get_name(): string
    {
        return 'command';
    }
    /**
     * @return array{
     *     class?: class-string,
     *     executor?: string,
     *     file: string,
     *     line: int,
     * }
     */
    public function get_command(): array
    {
        if (\is_array($this->data['command'])) {
            return $this->data['command'];
        }
        $class = $this->data['command']->get_type();
        $r = new \ReflectionMethod($class, 'execute');
        if (Command::class !== $r->get_declaring_class()) {
            return ['executor' => $class . '::' . $r->name, 'file' => $r->get_file_name(), 'line' => $r->get_start_line()];
        }
        $r = new \ReflectionClass($class);
        return ['class' => $class, 'file' => $r->get_file_name(), 'line' => $r->get_start_line()];
    }
    public function get_interrupted_by_signal(): ?string
    {
        if (isset($this->data['interrupted_by_signal'])) {
            return \sprintf('%s (%d)', Signal_Map::get_signal_name($this->data['interrupted_by_signal']), $this->data['interrupted_by_signal']);
        }
        return null;
    }
    public function get_duration(): string
    {
        return $this->data['duration'];
    }
    public function get_max_memory_usage(): string
    {
        return $this->data['max_memory_usage'];
    }
    public function get_verbosity_level(): string
    {
        return $this->data['verbosity_level'];
    }
    public function get_interactive(): bool
    {
        return $this->data['interactive'];
    }
    public function get_validate_input(): bool
    {
        return $this->data['validate_input'];
    }
    public function get_enabled(): bool
    {
        return $this->data['enabled'];
    }
    public function get_visible(): bool
    {
        return $this->data['visible'];
    }
    public function get_input(): Data
    {
        return $this->data['input'];
    }
    public function get_output(): Data
    {
        return $this->data['output'];
    }
    /**
     * @return Data[]
     */
    public function get_arguments(): array
    {
        return $this->data['arguments'] ?? [];
    }
    /**
     * @return Data[]
     */
    public function get_options(): array
    {
        return $this->data['options'] ?? [];
    }
    /**
     * @return Data[]
     */
    public function get_application_inputs(): array
    {
        return $this->data['application_inputs'] ?? [];
    }
    /**
     * @return Data[]
     */
    public function get_interactive_inputs(): array
    {
        return $this->data['interactive_inputs'] ?? [];
    }
    public function get_signalable(): array
    {
        return array_map(static fn(int $signal): string => \sprintf('%s (%d)', Signal_Map::get_signal_name($signal), $signal), $this->data['signalable']);
    }
    public function get_handled_signals(): array
    {
        $keys = array_map(static fn(int $signal): string => \sprintf('%s (%d)', Signal_Map::get_signal_name($signal), $signal), array_keys($this->data['handled_signals']));
        return array_combine($keys, array_values($this->data['handled_signals']));
    }
    /**
     * @return Data[]
     */
    public function get_helper_set(): array
    {
        return $this->data['helper_set'] ?? [];
    }
    public function reset(): void
    {
        $this->data = [];
    }
}