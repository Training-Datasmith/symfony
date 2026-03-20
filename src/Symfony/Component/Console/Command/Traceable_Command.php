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
namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Helper\Helper_Interface;
use Symfony\Component\Console\Helper\Helper_Set;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Stopwatch\Stopwatch;
/**
 * @internal
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
final class Traceable_Command extends Command
{
    public readonly Command $command;
    public int $exit_code;
    public ?int $interrupted_by_signal = null;
    public bool $ignore_validation;
    public bool $is_interactive = false;
    public string $duration = 'n/a';
    public string $max_memory_usage = 'n/a';
    public Input_Interface $input;
    public Output_Interface $output;
    /** @var array<string, mixed> */
    public array $arguments;
    /** @var array<string, mixed> */
    public array $options;
    /** @var array<string, mixed> */
    public array $interactive_inputs = [];
    public array $handled_signals = [];
    public ?array $invokable_command_info = null;
    public function __construct(Command $command, private readonly Stopwatch $stopwatch)
    {
        if ($command instanceof Lazy_Command) {
            $command = $command->get_command();
        }
        $this->command = $command;
        // prevent call to self::getDefaultDescription()
        $this->set_description($command->get_description());
        parent::__construct($command->get_name());
        // init below enables calling {@see parent::run()}
        [$code, $process_title, $ignore_validation_errors] = \Closure::bind(fn(): array => [$this->code, $this->process_title, $this->ignore_validation_errors], $command, Command::class)();
        if (\is_callable($code)) {
            $this->set_code($code);
        }
        if ($process_title) {
            parent::set_process_title($process_title);
        }
        if ($ignore_validation_errors) {
            parent::ignore_validation_errors();
        }
        $this->ignore_validation = $ignore_validation_errors;
    }
    public function __call(string $name, array $arguments): mixed
    {
        return $this->command->{$name}(...$arguments);
    }
    public function get_subscribed_signals(): array
    {
        return $this->command->get_subscribed_signals();
    }
    public function handle_signal(int $signal, int|false $previous_exit_code = 0): int|false
    {
        $event = $this->stopwatch->start($this->get_name() . '.handle_signal');
        $exit = $this->command->handle_signal($signal, $previous_exit_code);
        $event->stop();
        if (!isset($this->handled_signals[$signal])) {
            $this->handled_signals[$signal] = ['handled' => 0, 'duration' => 0, 'memory' => 0];
        }
        ++$this->handled_signals[$signal]['handled'];
        $this->handled_signals[$signal]['duration'] += $event->get_duration();
        $this->handled_signals[$signal]['memory'] = max($this->handled_signals[$signal]['memory'], $event->get_memory() >> 20);
        return $exit;
    }
    /**
     * {@inheritdoc}
     *
     * Calling parent method is required to be used in {@see parent::run()}.
     */
    public function ignore_validation_errors(): void
    {
        $this->ignore_validation = true;
        $this->command->ignore_validation_errors();
        parent::ignore_validation_errors();
    }
    public function set_application(?Application $application = null): void
    {
        $this->command->set_application($application);
    }
    public function get_application(): ?Application
    {
        return $this->command->get_application();
    }
    public function set_helper_set(Helper_Set $helper_set): void
    {
        $this->command->set_helper_set($helper_set);
    }
    public function get_helper_set(): ?Helper_Set
    {
        return $this->command->get_helper_set();
    }
    public function is_enabled(): bool
    {
        return $this->command->is_enabled();
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        $this->command->complete($input, $suggestions);
    }
    /**
     * {@inheritdoc}
     *
     * Calling parent method is required to be used in {@see parent::run()}.
     */
    public function set_code(callable $code): static
    {
        if ($code instanceof Invokable_Command) {
            $r = \Closure::bind(fn(): \ReflectionFunction => $this->invokable, $code, Invokable_Command::class)();
            $this->invokable_command_info = ['class' => $r->get_closure_scope_class()->name, 'file' => $r->get_file_name(), 'line' => $r->get_start_line()];
            // Pass the original callable to avoid double-wrapping in Command::setCode()
            $this->command->set_code($code->get_code());
        } else {
            $this->command->set_code($code);
        }
        return parent::set_code(function (Input_Interface $input, Output_Interface $output) use ($code): int {
            $event = $this->stopwatch->start($this->get_name() . '.code');
            $this->exit_code = $code($input, $output);
            $event->stop();
            return $this->exit_code;
        });
    }
    /**
     * @internal
     */
    public function merge_application_definition(bool $merge_args = true): void
    {
        $this->command->merge_application_definition($merge_args);
    }
    public function set_definition(array|Input_Definition $definition): static
    {
        $this->command->set_definition($definition);
        return $this;
    }
    public function get_definition(): Input_Definition
    {
        return $this->command->get_definition();
    }
    public function get_native_definition(): Input_Definition
    {
        return $this->command->get_native_definition();
    }
    public function add_argument(string $name, ?int $mode = null, string $description = '', mixed $default = null, array|\Closure $suggested_values = []): static
    {
        $this->command->add_argument($name, $mode, $description, $default, $suggested_values);
        return $this;
    }
    public function add_option(string $name, string|array|null $shortcut = null, ?int $mode = null, string $description = '', mixed $default = null, array|\Closure $suggested_values = []): static
    {
        $this->command->add_option($name, $shortcut, $mode, $description, $default, $suggested_values);
        return $this;
    }
    /**
     * {@inheritdoc}
     *
     * Calling parent method is required to be used in {@see parent::run()}.
     */
    public function set_process_title(string $title): static
    {
        $this->command->set_process_title($title);
        return parent::set_process_title($title);
    }
    public function set_help(string $help): static
    {
        $this->command->set_help($help);
        return $this;
    }
    public function get_help(): string
    {
        return $this->command->get_help();
    }
    public function get_processed_help(): string
    {
        return $this->command->get_processed_help();
    }
    public function get_synopsis(bool $short = false): string
    {
        return $this->command->get_synopsis($short);
    }
    public function add_usage(string $usage): static
    {
        $this->command->add_usage($usage);
        return $this;
    }
    public function get_usages(): array
    {
        return $this->command->get_usages();
    }
    public function get_helper(string $name): Helper_Interface
    {
        return $this->command->get_helper($name);
    }
    public function run(Input_Interface $input, Output_Interface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $initial_arguments = $input->get_arguments();
        $initial_options = $input->get_options();
        $event = $this->stopwatch->start($this->get_name(), 'command');
        try {
            $this->exit_code = $this->command->run($input, $output);
        } finally {
            $event->stop();
            if ($output instanceof Console_Output_Interface && $output->is_debug()) {
                $output->get_error_output()->writeln((string) $event);
            }
            $this->duration = $event->get_duration() . ' ms';
            $this->max_memory_usage = ($event->get_memory() >> 20) . ' MiB';
            $this->arguments = $input->get_arguments();
            $this->options = $input->get_options();
            $this->extract_interactive_inputs($initial_arguments, $initial_options);
            $this->is_interactive = $this->is_interactive || $this->interactive_inputs;
        }
        return $this->exit_code;
    }
    protected function initialize(Input_Interface $input, Output_Interface $output): void
    {
        $event = $this->stopwatch->start($this->get_name() . '.init', 'command');
        $this->command->initialize($input, $output);
        $event->stop();
    }
    protected function interact(Input_Interface $input, Output_Interface $output): void
    {
        if (!$this->is_interactive = Command::class !== (new \ReflectionMethod($this->command, 'interact'))->class) {
            return;
        }
        $event = $this->stopwatch->start($this->get_name() . '.interact', 'command');
        $this->command->interact($input, $output);
        $event->stop();
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $event = $this->stopwatch->start($this->get_name() . '.execute', 'command');
        $exit_code = $this->command->execute($input, $output);
        $event->stop();
        return $exit_code;
    }
    private function extract_interactive_inputs(array $initial_arguments, array $initial_options): void
    {
        $native_definition = $this->command->get_native_definition();
        foreach ($native_definition->get_arguments() as $arg_name => $argument) {
            if (\array_key_exists($arg_name, $initial_arguments) && $initial_arguments[$arg_name] === $this->arguments[$arg_name]) {
                continue;
            }
            $this->interactive_inputs[$arg_name] = $this->arguments[$arg_name];
        }
        foreach ($native_definition->get_options() as $opt_name => $option) {
            if (\array_key_exists($opt_name, $initial_options) && $initial_options[$opt_name] === $this->options[$opt_name]) {
                continue;
            }
            $this->interactive_inputs['--' . $opt_name] = $this->options[$opt_name];
        }
    }
}