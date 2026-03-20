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
namespace Symfony\Component\Console;

use Symfony\Component\Console\Argument_Resolver\Argument_Resolver_Interface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\Complete_Command;
use Symfony\Component\Console\Command\Dump_Completion_Command;
use Symfony\Component\Console\Command\Help_Command;
use Symfony\Component\Console\Command\Lazy_Command;
use Symfony\Component\Console\Command\List_Command;
use Symfony\Component\Console\Command_Loader\Command_Loader_Interface;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Event\Console_Alarm_Event;
use Symfony\Component\Console\Event\Console_Command_Event;
use Symfony\Component\Console\Event\Console_Error_Event;
use Symfony\Component\Console\Event\Console_Signal_Event;
use Symfony\Component\Console\Event\Console_Terminate_Event;
use Symfony\Component\Console\Exception\Command_Not_Found_Exception;
use Symfony\Component\Console\Exception\Exception_Interface;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\Namespace_Not_Found_Exception;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Helper\Debug_Formatter_Helper;
use Symfony\Component\Console\Helper\Descriptor_Helper;
use Symfony\Component\Console\Helper\Formatter_Helper;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Helper_Set;
use Symfony\Component\Console\Helper\Process_Helper;
use Symfony\Component\Console\Helper\Question_Helper;
use Symfony\Component\Console\Input\Argv_Input;
use Symfony\Component\Console\Input\Array_Input;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Aware_Interface;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Console_Output;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Signal_Registry\Signal_Registry;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Error_Handler\Error_Handler;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * An Application is the container for a collection of commands.
 *
 * It is the main entry point of a Console application.
 *
 * This class is optimized for a standard CLI environment.
 *
 * Commands can be registered:
 *  - Eagerly via add() / addCommands()
 *  - Lazily via a CommandLoaderInterface (loaded on first access by name)
 *
 * Signal handling (SIGINT, SIGTERM, etc.) is supported when the pcntl extension
 * is available. Register signals via getSignalRegistry().
 *
 * Usage:
 *
 *     $app = new Application('myapp', '1.0 (stable)');
 *     $app->add(new SimpleCommand());
 *     $app->run();
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since 2.0
 */
class Application implements Reset_Interface
{
    private array $commands = [];
    private bool $want_helps = false;
    private ?Command $running_command = null;
    private ?Command_Loader_Interface $command_loader = null;
    private bool $catch_exceptions = true;
    private bool $catch_errors = false;
    private bool $auto_exit = true;
    private Input_Definition $definition;
    private Helper_Set $helper_set;
    private ?Event_Dispatcher_Interface $dispatcher = null;
    private ?Argument_Resolver_Interface $argument_resolver = null;
    private readonly Terminal $terminal;
    private string $default_command;
    private bool $single_command = false;
    private bool $initialized = false;
    private ?Signal_Registry $signal_registry = null;
    private array $signals_to_dispatch_event = [];
    private ?int $alarm_interval = null;
    /**
     * Creates a new Console Application.
     *
     * @param string $name    The application name displayed in help output (default: 'UNKNOWN')
     * @param string $version The application version displayed in help output (default: 'UNKNOWN')
     *
     * @since 2.0
     */
    public function __construct(private string $name = 'UNKNOWN', private string $version = 'UNKNOWN')
    {
        $this->terminal = new Terminal();
        $this->default_command = 'list';
        if (\defined('SIGINT') && Signal_Registry::is_supported()) {
            $this->signal_registry = new Signal_Registry();
            $this->signals_to_dispatch_event = [\SIGINT, \SIGQUIT, \SIGTERM, \SIGUSR1, \SIGUSR2, \SIGALRM];
        }
    }
    /**
     * @final
     */
    public function set_dispatcher(Event_Dispatcher_Interface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }
    public function get_dispatcher(): ?Event_Dispatcher_Interface
    {
        return $this->dispatcher;
    }
    /**
     * @final
     */
    public function set_argument_resolver(Argument_Resolver_Interface $argument_resolver): void
    {
        $this->argument_resolver = $argument_resolver;
    }
    public function get_argument_resolver(): ?Argument_Resolver_Interface
    {
        return $this->argument_resolver;
    }
    public function set_command_loader(Command_Loader_Interface $command_loader): void
    {
        $this->command_loader = $command_loader;
    }
    public function get_signal_registry(): Signal_Registry
    {
        if (!$this->signal_registry) {
            throw new RuntimeException('Signals are not supported. Make sure that the "pcntl" extension is installed and that "pcntl_*" functions are not disabled by your php.ini\'s "disable_functions" directive.');
        }
        return $this->signal_registry;
    }
    public function set_signals_to_dispatch_event(int ...$signals_to_dispatch_event): void
    {
        $this->signals_to_dispatch_event = $signals_to_dispatch_event;
    }
    /**
     * Sets the interval to schedule a SIGALRM signal in seconds.
     */
    public function set_alarm_interval(?int $seconds): void
    {
        $this->alarm_interval = $seconds;
        $this->schedule_alarm();
    }
    /**
     * Gets the interval in seconds on which a SIGALRM signal is dispatched.
     */
    public function get_alarm_interval(): ?int
    {
        return $this->alarm_interval;
    }
    private function schedule_alarm(): void
    {
        if (null !== $this->alarm_interval) {
            $this->get_signal_registry()->schedule_alarm($this->alarm_interval);
        }
    }
    /**
     * Runs the current application.
     *
     * @return int 0 if everything went fine, or an error code
     *
     * @throws \Exception When running fails. Bypass this when {@link setCatchExceptions()}.
     */
    public function run(?Input_Interface $input = null, ?Output_Interface $output = null): int
    {
        if (\function_exists('putenv')) {
            @putenv('LINES=' . $this->terminal->get_height());
            @putenv('COLUMNS=' . $this->terminal->get_width());
        }
        $input ??= new Argv_Input();
        $output ??= new Console_Output();
        $render_exception = function (\Throwable $e) use ($output): void {
            if ($output instanceof Console_Output_Interface) {
                $this->render_throwable($e, $output->get_error_output());
            } else {
                $this->render_throwable($e, $output);
            }
        };
        if ($php_handler = set_exception_handler($render_exception)) {
            restore_exception_handler();
            if (!\is_array($php_handler) || !$php_handler[0] instanceof Error_Handler) {
                $error_handler = true;
            } elseif ($error_handler = $php_handler[0]->set_exception_handler($render_exception)) {
                $php_handler[0]->set_exception_handler($error_handler);
            }
        }
        $empty = new \stdClass();
        $prev_shell_verbosity = [$_ENV['SHELL_VERBOSITY'] ?? $empty, $_SERVER['SHELL_VERBOSITY'] ?? $empty, getenv('SHELL_VERBOSITY')];
        try {
            $this->configure_io($input, $output);
            $exit_code = $this->do_run($input, $output);
        } catch (\Throwable $e) {
            if ($e instanceof \Exception && !$this->catch_exceptions) {
                throw $e;
            }
            if (!$e instanceof \Exception && !$this->catch_errors) {
                throw $e;
            }
            $render_exception($e);
            $exit_code = $e->get_code();
            if (is_numeric($exit_code)) {
                $exit_code = (int) $exit_code;
                if ($exit_code <= 0) {
                    $exit_code = 1;
                }
            } else {
                $exit_code = 1;
            }
        } finally {
            // if the exception handler changed, keep it
            // otherwise, unregister $renderException
            if (!$php_handler) {
                if (set_exception_handler($render_exception) === $render_exception) {
                    restore_exception_handler();
                }
                restore_exception_handler();
            } elseif (!$error_handler) {
                $final_handler = $php_handler[0]->set_exception_handler(null);
                if ($final_handler !== $render_exception) {
                    $php_handler[0]->set_exception_handler($final_handler);
                }
            }
            // SHELL_VERBOSITY is set by Application::configureIO so we need to unset/reset it
            // to its previous value to avoid one command verbosity to spread to other commands
            if ($empty === $_ENV['SHELL_VERBOSITY'] = $prev_shell_verbosity[0]) {
                unset($_ENV['SHELL_VERBOSITY']);
            }
            if ($empty === $_SERVER['SHELL_VERBOSITY'] = $prev_shell_verbosity[1]) {
                unset($_SERVER['SHELL_VERBOSITY']);
            }
            if (\function_exists('putenv')) {
                @putenv('SHELL_VERBOSITY' . (false === ($prev_shell_verbosity[2] ?? false) ? '' : '=' . $prev_shell_verbosity[2]));
            }
        }
        if ($this->auto_exit) {
            if ($exit_code > 255) {
                $exit_code = 255;
            }
            exit($exit_code);
        }
        return $exit_code;
    }
    /**
     * Runs the current application.
     *
     * @return int 0 if everything went fine, or an error code
     */
    public function do_run(Input_Interface $input, Output_Interface $output): int
    {
        if (true === $input->has_parameter_option(['--version', '-V'], true)) {
            $output->writeln($this->get_long_version());
            return 0;
        }
        try {
            // Makes ArgvInput::getFirstArgument() able to distinguish an option from an argument.
            $input->bind($this->get_definition());
        } catch (Exception_Interface) {
            // Errors must be ignored, full binding/validation happens later when the command is known.
        }
        $name = $this->get_command_name($input);
        if (true === $input->has_parameter_option(['--help', '-h'], true)) {
            if (!$name) {
                $name = 'help';
                $input = new Array_Input(['command_name' => $this->default_command]);
            } else {
                $this->want_helps = true;
            }
        }
        if (!$name) {
            $name = $this->default_command;
            $definition = $this->get_definition();
            $definition->set_arguments(array_merge($definition->get_arguments(), ['command' => new Input_Argument('command', Input_Argument::OPTIONAL, $definition->get_argument('command')->get_description(), $name)]));
        }
        try {
            $this->running_command = null;
            // the command name MUST be the first element of the input
            $command = $this->find($name);
        } catch (\Throwable $e) {
            if ($e instanceof Command_Not_Found_Exception && !$e instanceof Namespace_Not_Found_Exception && 1 === \count($alternatives = $e->get_alternatives()) && $input->is_interactive()) {
                $alternative = $alternatives[0];
                $style = new Symfony_Style($input, $output);
                $output->writeln('');
                $formatted_block = (new Formatter_Helper())->format_block(\sprintf('Command "%s" is not defined.', $name), 'error', true);
                $output->writeln($formatted_block);
                if (!$style->confirm(\sprintf('Do you want to run "%s" instead? ', $alternative), false)) {
                    if (null !== $this->dispatcher) {
                        $event = new Console_Error_Event($input, $output, $e);
                        $this->dispatcher->dispatch($event, Console_Events::ERROR);
                        return $event->get_exit_code();
                    }
                    return 1;
                }
                $command = $this->find($alternative);
            } else {
                if (null !== $this->dispatcher) {
                    $event = new Console_Error_Event($input, $output, $e);
                    $this->dispatcher->dispatch($event, Console_Events::ERROR);
                    if (0 === $event->get_exit_code()) {
                        return 0;
                    }
                    $e = $event->get_error();
                }
                try {
                    if ($e instanceof Command_Not_Found_Exception && $namespace = $this->find_namespace($name)) {
                        $helper = new Descriptor_Helper();
                        $helper->describe($output instanceof Console_Output_Interface ? $output->get_error_output() : $output, $this, ['format' => 'txt', 'raw_text' => false, 'namespace' => $namespace, 'short' => false]);
                        return isset($event) ? $event->get_exit_code() : 1;
                    }
                    throw $e;
                } catch (Namespace_Not_Found_Exception) {
                    throw $e;
                }
            }
        }
        if ($command instanceof Lazy_Command) {
            $command = $command->get_command();
        }
        $this->running_command = $command;
        $exit_code = $this->do_run_command($command, $input, $output);
        $this->running_command = null;
        return $exit_code;
    }
    public function reset(): void
    {
    }
    public function set_helper_set(Helper_Set $helper_set): void
    {
        $this->helper_set = $helper_set;
    }
    /**
     * Get the helper set associated with the command.
     */
    public function get_helper_set(): Helper_Set
    {
        return $this->helper_set ??= $this->get_default_helper_set();
    }
    public function set_definition(Input_Definition $definition): void
    {
        $this->definition = $definition;
    }
    /**
     * Gets the InputDefinition related to this Application.
     */
    public function get_definition(): Input_Definition
    {
        $this->definition ??= $this->get_default_input_definition();
        if ($this->single_command) {
            $this->definition->set_arguments();
        }
        return $this->definition;
    }
    /**
     * Adds suggestions to $suggestions for the current completion input (e.g. option or argument).
     */
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if (Completion_Input::TYPE_ARGUMENT_VALUE === $input->get_completion_type() && 'command' === $input->get_completion_name()) {
            foreach ($this->all() as $name => $command) {
                // skip hidden commands and aliased commands as they already get added below
                if ($command->is_hidden()) {
                    continue;
                }
                if ($command->get_name() !== $name) {
                    continue;
                }
                $suggestions->suggest_value(new Suggestion($command->get_name(), $command->get_description()));
                foreach ($command->get_aliases() as $name) {
                    $suggestions->suggest_value(new Suggestion($name, $command->get_description()));
                }
            }
            return;
        }
        if (Completion_Input::TYPE_OPTION_NAME === $input->get_completion_type()) {
            $suggestions->suggest_options($this->get_definition()->get_options());
        }
        if (Completion_Input::TYPE_OPTION_VALUE === $input->get_completion_type() && ($definition = $this->get_definition())->has_option($input->get_completion_name())) {
            $definition->get_option($input->get_completion_name())->complete($input, $suggestions);
            return;
        }
    }
    /**
     * Gets the help message.
     */
    public function get_help(): string
    {
        return $this->get_long_version();
    }
    /**
     * Gets whether to catch exceptions or not during commands execution.
     */
    public function are_exceptions_caught(): bool
    {
        return $this->catch_exceptions;
    }
    /**
     * Sets whether to catch exceptions or not during commands execution.
     */
    public function set_catch_exceptions(bool $boolean): void
    {
        $this->catch_exceptions = $boolean;
    }
    /**
     * Sets whether to catch errors or not during commands execution.
     */
    public function set_catch_errors(bool $catch_errors = true): void
    {
        $this->catch_errors = $catch_errors;
    }
    /**
     * Gets whether to automatically exit after a command execution or not.
     */
    public function is_auto_exit_enabled(): bool
    {
        return $this->auto_exit;
    }
    /**
     * Sets whether to automatically exit after a command execution or not.
     */
    public function set_auto_exit(bool $boolean): void
    {
        $this->auto_exit = $boolean;
    }
    /**
     * Gets the name of the application.
     */
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * Sets the application name.
     */
    public function set_name(string $name): void
    {
        $this->name = $name;
    }
    /**
     * Gets the application version.
     */
    public function get_version(): string
    {
        return $this->version;
    }
    /**
     * Sets the application version.
     */
    public function set_version(string $version): void
    {
        $this->version = $version;
    }
    /**
     * Returns the long version of the application.
     */
    public function get_long_version(): string
    {
        if ('UNKNOWN' !== $this->get_name()) {
            if ('UNKNOWN' !== $this->get_version()) {
                return \sprintf('%s <info>%s</info>', $this->get_name(), $this->get_version());
            }
            return $this->get_name();
        }
        return 'Console Tool';
    }
    /**
     * Registers a new command.
     */
    public function register(string $name): Command
    {
        return $this->add_command(new Command($name));
    }
    /**
     * Adds an array of command objects.
     *
     * If a Command is not enabled it will not be added.
     *
     * @param callable[]|Command[] $commands An array of commands
     */
    public function add_commands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->add_command($command);
        }
    }
    /**
     * Adds a command object.
     *
     * If a command with the same name already exists, it will be overridden.
     * If the command is not enabled it will not be added.
     */
    public function add_command(callable|Command $command): ?Command
    {
        $this->init();
        if (!$command instanceof Command) {
            $command = new Command(null, $command);
        }
        $command->set_application($this);
        if (!$command->is_enabled()) {
            $command->set_application(null);
            return null;
        }
        if (!$command instanceof Lazy_Command) {
            // Will throw if the command is not correctly initialized.
            $command->get_definition();
        }
        if (!$command->get_name()) {
            throw new LogicException(\sprintf('The command defined in "%s" cannot have an empty name.', get_debug_type($command)));
        }
        $this->commands[$command->get_name()] = $command;
        foreach ($command->get_aliases() as $alias) {
            $this->commands[$alias] = $command;
        }
        return $command;
    }
    /**
     * Returns a registered command by name or alias.
     *
     * @throws CommandNotFoundException When given command name does not exist
     */
    public function get(string $name): Command
    {
        $this->init();
        if (!$this->has($name)) {
            throw new Command_Not_Found_Exception(\sprintf('The command "%s" does not exist.', $name));
        }
        // When the command has a different name than the one used at the command loader level
        if (!isset($this->commands[$name])) {
            throw new Command_Not_Found_Exception(\sprintf('The "%s" command cannot be found because it is registered under multiple names. Make sure you don\'t set a different name via constructor or "setName()".', $name));
        }
        $command = $this->commands[$name];
        if ($this->want_helps) {
            $this->want_helps = false;
            $help_command = $this->get('help');
            $help_command->set_command($command);
            return $help_command;
        }
        return $command;
    }
    /**
     * Returns true if the command exists, false otherwise.
     */
    public function has(string $name): bool
    {
        $this->init();
        return isset($this->commands[$name]) || $this->command_loader?->has($name) && $this->add_command($this->command_loader->get($name));
    }
    /**
     * Returns an array of all unique namespaces used by currently registered commands.
     *
     * It does not return the global namespace which always exists.
     *
     * @return string[]
     */
    public function get_namespaces(): array
    {
        $namespaces = [];
        foreach ($this->all() as $command) {
            if ($command->is_hidden()) {
                continue;
            }
            $namespaces[] = $this->extract_all_namespaces($command->get_name());
            foreach ($command->get_aliases() as $alias) {
                $namespaces[] = $this->extract_all_namespaces($alias);
            }
        }
        return array_values(array_unique(array_filter(array_merge([], ...$namespaces))));
    }
    /**
     * Finds a registered namespace by a name or an abbreviation.
     *
     * @throws NamespaceNotFoundException When namespace is incorrect or ambiguous
     */
    public function find_namespace(string $namespace): string
    {
        $all_namespaces = $this->get_namespaces();
        $expr = implode('[^:]*:', array_map(preg_quote(...), explode(':', $namespace))) . '[^:]*';
        $namespaces = preg_grep('{^' . $expr . '}', $all_namespaces);
        if (!$namespaces) {
            $message = \sprintf('There are no commands defined in the "%s" namespace.', $namespace);
            if ($alternatives = $this->find_alternatives($namespace, $all_namespaces)) {
                if (1 == \count($alternatives)) {
                    $message .= "\n\nDid you mean this?\n    ";
                } else {
                    $message .= "\n\nDid you mean one of these?\n    ";
                }
                $message .= implode("\n    ", $alternatives);
            }
            throw new Namespace_Not_Found_Exception($message, $alternatives);
        }
        $exact = \in_array($namespace, $namespaces, true);
        if (\count($namespaces) > 1 && !$exact) {
            sort($namespaces);
            throw new Namespace_Not_Found_Exception(\sprintf("The namespace \"%s\" is ambiguous.\nDid you mean one of these?\n%s.", $namespace, $this->get_abbreviation_suggestions(array_values($namespaces))), array_values($namespaces));
        }
        return $exact ? $namespace : reset($namespaces);
    }
    /**
     * Finds a command by name or alias.
     *
     * Contrary to get, this command tries to find the best
     * match if you give it an abbreviation of a name or alias.
     *
     * @throws CommandNotFoundException When command name is incorrect or ambiguous
     */
    public function find(string $name): Command
    {
        $this->init();
        $aliases = [];
        foreach ($this->commands as $command) {
            foreach ($command->get_aliases() as $alias) {
                if (!$this->has($alias)) {
                    $this->commands[$alias] = $command;
                }
            }
        }
        if ($this->has($name)) {
            return $this->get($name);
        }
        $all_commands = $this->command_loader ? array_merge($this->command_loader->get_names(), array_keys($this->commands)) : array_keys($this->commands);
        $expr = implode('[^:]*:', array_map(preg_quote(...), explode(':', $name))) . '[^:]*';
        $commands = preg_grep('{^' . $expr . '}', $all_commands);
        if (!$commands) {
            $commands = preg_grep('{^' . $expr . '}i', $all_commands);
        }
        // if no commands matched or we just matched namespaces
        if (!$commands || \count(preg_grep('{^' . $expr . '$}i', $commands)) < 1) {
            if (false !== $pos = strrpos($name, ':')) {
                // check if a namespace exists and contains commands
                $this->find_namespace(substr($name, 0, $pos));
            }
            $message = \sprintf('Command "%s" is not defined.', $name);
            if ($alternatives = $this->find_alternatives($name, $all_commands)) {
                $want_helps = $this->want_helps;
                $this->want_helps = false;
                // remove hidden commands
                if ($alternatives = array_filter($alternatives, fn(string $name): bool => !$this->get($name)->is_hidden())) {
                    $message .= \sprintf("\n\nDid you mean %s?\n    %s", 1 === \count($alternatives) ? 'this' : 'one of these', implode("\n    ", $alternatives));
                }
                $this->want_helps = $want_helps;
            }
            throw new Command_Not_Found_Exception($message, array_values($alternatives));
        }
        // filter out aliases for commands which are already on the list
        if (\count($commands) > 1) {
            $command_list = $this->command_loader ? array_merge(array_flip($this->command_loader->get_names()), $this->commands) : $this->commands;
            $commands = array_unique(array_filter($commands, function ($name_or_alias) use (&$command_list, $commands, &$aliases): bool {
                if (!$command_list[$name_or_alias] instanceof Command) {
                    $command_list[$name_or_alias] = $this->command_loader->get($name_or_alias);
                }
                $command_name = $command_list[$name_or_alias]->get_name();
                $aliases[$name_or_alias] = $command_name;
                return $command_name === $name_or_alias || !\in_array($command_name, $commands, true);
            }));
        }
        // check whether all commands left are aliases to the same one
        if (\count($commands) > 1) {
            $unique_commands = array_unique(array_map(function ($name_or_alias) use (&$command_list): ?string {
                if (!$command_list[$name_or_alias] instanceof Command) {
                    $command_list[$name_or_alias] = $this->command_loader->get($name_or_alias);
                }
                return $command_list[$name_or_alias]->get_name();
            }, $commands));
            if (1 === \count($unique_commands)) {
                $commands = [reset($unique_commands)];
            }
        }
        if (\count($commands) > 1) {
            sort($commands);
            $usable_width = $this->terminal->get_width() - 10;
            $abbrevs = array_values($commands);
            $max_len = 0;
            foreach ($abbrevs as $abbrev) {
                $max_len = max(Helper::width($abbrev), $max_len);
            }
            $abbrevs = array_map(static function ($cmd) use ($command_list, $usable_width, $max_len, &$commands): false|string {
                if ($command_list[$cmd]->is_hidden()) {
                    unset($commands[array_search($cmd, $commands)]);
                    return false;
                }
                $abbrev = str_pad($cmd, $max_len, ' ') . ' ' . $command_list[$cmd]->get_description();
                return Helper::width($abbrev) > $usable_width ? Helper::substr($abbrev, 0, $usable_width - 3) . '...' : $abbrev;
            }, array_values($commands));
            if (\count($commands) > 1) {
                $suggestions = $this->get_abbreviation_suggestions(array_filter($abbrevs));
                throw new Command_Not_Found_Exception(\sprintf("Command \"%s\" is ambiguous.\nDid you mean one of these?\n%s.", $name, $suggestions), array_values($commands));
            }
        }
        $command = $commands ? $this->get(reset($commands)) : null;
        if (!$command || $command->is_hidden()) {
            throw new Command_Not_Found_Exception(\sprintf('The command "%s" does not exist.', $name));
        }
        return $command;
    }
    /**
     * Gets the commands (registered in the given namespace if provided).
     *
     * The array keys are the full names and the values the command instances.
     *
     * @return Command[]
     */
    public function all(?string $namespace = null): array
    {
        $this->init();
        if (null === $namespace) {
            if (!$this->command_loader) {
                return $this->commands;
            }
            $commands = $this->commands;
            foreach ($this->command_loader->get_names() as $name) {
                if (!isset($commands[$name]) && $this->has($name)) {
                    $commands[$name] = $this->get($name);
                }
            }
            return $commands;
        }
        $commands = [];
        foreach ($this->commands as $name => $command) {
            if ($namespace === $this->extract_namespace($name, substr_count($namespace, ':') + 1)) {
                $commands[$name] = $command;
            }
        }
        if ($this->command_loader) {
            foreach ($this->command_loader->get_names() as $name) {
                if (!isset($commands[$name]) && $namespace === $this->extract_namespace($name, substr_count($namespace, ':') + 1) && $this->has($name)) {
                    $commands[$name] = $this->get($name);
                }
            }
        }
        return $commands;
    }
    /**
     * Returns an array of possible abbreviations given a set of names.
     *
     * @return string[][]
     */
    public static function get_abbreviations(array $names): array
    {
        $abbrevs = [];
        foreach ($names as $name) {
            for ($len = \strlen((string) $name); $len > 0; --$len) {
                $abbrev = substr((string) $name, 0, $len);
                $abbrevs[$abbrev][] = $name;
            }
        }
        return $abbrevs;
    }
    public function render_throwable(\Throwable $e, Output_Interface $output): void
    {
        $output->writeln('', Output_Interface::VERBOSITY_QUIET);
        $this->do_render_throwable($e, $output);
        if (null !== $this->running_command) {
            $output->writeln(\sprintf('<info>%s</info>', Output_Formatter::escape(\sprintf($this->running_command->get_synopsis(), $this->get_name()))), Output_Interface::VERBOSITY_QUIET);
            $output->writeln('', Output_Interface::VERBOSITY_QUIET);
        }
    }
    protected function do_render_throwable(\Throwable $e, Output_Interface $output): void
    {
        do {
            $message = trim($e->get_message());
            if ('' === $message || Output_Interface::VERBOSITY_VERBOSE <= $output->get_verbosity()) {
                $class = get_debug_type($e);
                $title = \sprintf('  [%s%s]  ', $class, 0 !== ($code = $e->get_code()) ? ' (' . $code . ')' : '');
                $len = Helper::width($title);
            } else {
                $len = 0;
            }
            if (str_contains($message, "@anonymous\x00")) {
                $message = preg_replace_callback('/[a-zA-Z_\x7f-\xff][\\\\a-zA-Z0-9_\x7f-\xff]*+@anonymous\x00.*?\.php(?:0x?|:[0-9]++\$)?[0-9a-fA-F]++/', static fn($m): string => class_exists($m[0], false) ? ((get_parent_class($m[0]) ?: key(class_implements($m[0]))) ?: 'class') . '@anonymous' : $m[0], $message);
            }
            $width = $this->terminal->get_width() ? $this->terminal->get_width() - 1 : \PHP_INT_MAX;
            $lines = [];
            foreach ('' !== $message ? preg_split('/\r?\n/', (string) $message) : [] as $line) {
                foreach ($this->split_string_by_width($line, $width - 4) as $line) {
                    // pre-format lines to get the right string length
                    $line_length = Helper::width($line) + 4;
                    $lines[] = [$line, $line_length];
                    $len = max($line_length, $len);
                }
            }
            $messages = [];
            if (!$e instanceof Exception_Interface || Output_Interface::VERBOSITY_VERBOSE <= $output->get_verbosity()) {
                $messages[] = \sprintf('<comment>%s</comment>', Output_Formatter::escape(\sprintf('In %s line %s:', basename($e->get_file()) ?: 'n/a', $e->get_line() ?: 'n/a')));
            }
            $messages[] = $empty_line = \sprintf('<error>%s</error>', str_repeat(' ', $len));
            if ('' === $message || Output_Interface::VERBOSITY_VERBOSE <= $output->get_verbosity()) {
                $messages[] = \sprintf('<error>%s%s</error>', $title, str_repeat(' ', max(0, $len - Helper::width($title))));
            }
            foreach ($lines as $line) {
                $messages[] = \sprintf('<error>  %s  %s</error>', Output_Formatter::escape($line[0]), str_repeat(' ', $len - $line[1]));
            }
            $messages[] = $empty_line;
            $messages[] = '';
            $output->writeln($messages, Output_Interface::VERBOSITY_QUIET);
            if (Output_Interface::VERBOSITY_VERBOSE <= $output->get_verbosity()) {
                $output->writeln('<comment>Exception trace:</comment>', Output_Interface::VERBOSITY_QUIET);
                // exception related properties
                $trace = $e->get_trace();
                array_unshift($trace, ['function' => '', 'file' => $e->get_file() ?: 'n/a', 'line' => $e->get_line() ?: 'n/a', 'args' => []]);
                for ($i = 0, $count = \count($trace); $i < $count; ++$i) {
                    $class = $trace[$i]['class'] ?? '';
                    $type = $trace[$i]['type'] ?? '';
                    $function = $trace[$i]['function'] ?? '';
                    $file = $trace[$i]['file'] ?? 'n/a';
                    $line = $trace[$i]['line'] ?? 'n/a';
                    $output->writeln(\sprintf(' %s%s at <info>%s:%s</info>', $class, $function ? $type . $function . '()' : '', $file, $line), Output_Interface::VERBOSITY_QUIET);
                }
                $output->writeln('', Output_Interface::VERBOSITY_QUIET);
            }
        } while ($e = $e->get_previous());
    }
    /**
     * Configures the input and output instances based on the user arguments and options.
     */
    protected function configure_io(Input_Interface $input, Output_Interface $output): void
    {
        if ($input->has_parameter_option(['--ansi'], true)) {
            $output->set_decorated(true);
        } elseif ($input->has_parameter_option(['--no-ansi'], true)) {
            $output->set_decorated(false);
        }
        $shell_verbosity = match (true) {
            $input->has_parameter_option(['--silent'], true) => -2,
            $input->has_parameter_option(['--quiet', '-q'], true) => -1,
            $input->has_parameter_option('-vvv', true) || $input->has_parameter_option('--verbose=3', true) || 3 === $input->get_parameter_option('--verbose', false, true) => 3,
            $input->has_parameter_option('-vv', true) || $input->has_parameter_option('--verbose=2', true) || 2 === $input->get_parameter_option('--verbose', false, true) => 2,
            $input->has_parameter_option('-v', true) || $input->has_parameter_option('--verbose=1', true) || $input->has_parameter_option('--verbose', true) || $input->get_parameter_option('--verbose', false, true) => 1,
            default => (int) ($_ENV['SHELL_VERBOSITY'] ?? $_SERVER['SHELL_VERBOSITY'] ?? getenv('SHELL_VERBOSITY')),
        };
        $output->set_verbosity(match ($shell_verbosity) {
            -2 => Output_Interface::VERBOSITY_SILENT,
            -1 => Output_Interface::VERBOSITY_QUIET,
            1 => Output_Interface::VERBOSITY_VERBOSE,
            2 => Output_Interface::VERBOSITY_VERY_VERBOSE,
            3 => Output_Interface::VERBOSITY_DEBUG,
            default => ($shell_verbosity = 0) ?: $output->get_verbosity(),
        });
        if (0 > $shell_verbosity || $input->has_parameter_option(['--no-interaction', '-n'], true)) {
            $input->set_interactive(false);
        }
        if (\function_exists('putenv')) {
            @putenv('SHELL_VERBOSITY=' . $shell_verbosity);
        }
        $_ENV['SHELL_VERBOSITY'] = $shell_verbosity;
        $_SERVER['SHELL_VERBOSITY'] = $shell_verbosity;
    }
    /**
     * Runs the current command.
     *
     * If an event dispatcher has been attached to the application,
     * events are also dispatched during the life-cycle of the command.
     *
     * @return int 0 if everything went fine, or an error code
     */
    protected function do_run_command(Command $command, Input_Interface $input, Output_Interface $output): int
    {
        foreach ($command->get_helper_set() as $helper) {
            if ($helper instanceof Input_Aware_Interface) {
                $helper->set_input($input);
            }
        }
        $registered_signals = false;
        if (($command_signals = $command->get_subscribed_signals()) || $this->dispatcher && $this->signals_to_dispatch_event) {
            $signal_registry = $this->get_signal_registry();
            $registered_signals = true;
            $this->get_signal_registry()->push_current_handlers();
            if ($this->dispatcher) {
                // We register application signals, so that we can dispatch the event
                foreach ($this->signals_to_dispatch_event as $signal) {
                    $signal_event = new Console_Signal_Event($command, $input, $output, $signal);
                    $alarm_event = \SIGALRM === $signal ? new Console_Alarm_Event($command, $input, $output) : null;
                    $signal_registry->register($signal, function ($signal) use ($signal_event, $alarm_event, $command, $command_signals, $input, $output): void {
                        $this->dispatcher->dispatch($signal_event, Console_Events::SIGNAL);
                        $exit_code = $signal_event->get_exit_code();
                        if (null !== $alarm_event) {
                            if (false !== $exit_code) {
                                $alarm_event->set_exit_code($exit_code);
                            } else {
                                $alarm_event->abort_exit();
                            }
                            $this->dispatcher->dispatch($alarm_event);
                            $exit_code = $alarm_event->get_exit_code();
                        }
                        // If the command is signalable, we call the handleSignal() method
                        if (\in_array($signal, $command_signals, true)) {
                            $exit_code = $command->handle_signal($signal, $exit_code);
                        }
                        if (\SIGALRM === $signal) {
                            $this->schedule_alarm();
                        }
                        if (false !== $exit_code) {
                            $event = new Console_Terminate_Event($command, $input, $output, $exit_code, $signal);
                            $this->dispatcher->dispatch($event, Console_Events::TERMINATE);
                            exit($event->get_exit_code());
                        }
                    });
                }
                // then we register command signals, but not if already handled after the dispatcher
                $command_signals = array_diff($command_signals, $this->signals_to_dispatch_event);
            }
            foreach ($command_signals as $signal) {
                $signal_registry->register($signal, function (int $signal) use ($command): void {
                    if (\SIGALRM === $signal) {
                        $this->schedule_alarm();
                    }
                    if (false !== $exit_code = $command->handle_signal($signal)) {
                        exit($exit_code);
                    }
                });
            }
        }
        if (null === $this->dispatcher) {
            try {
                return $command->run($input, $output);
            } finally {
                if ($registered_signals) {
                    $this->get_signal_registry()->pop_previous_handlers();
                }
            }
        }
        // bind before the console.command event, so the listeners have access to input options/arguments
        try {
            $command->merge_application_definition();
            $input->bind($command->get_definition());
        } catch (Exception_Interface) {
            // ignore invalid options/arguments for now, to allow the event listeners to customize the InputDefinition
        }
        $event = new Console_Command_Event($command, $input, $output);
        $e = null;
        try {
            $this->dispatcher->dispatch($event, Console_Events::COMMAND);
            if ($event->command_should_run()) {
                $exit_code = $command->run($input, $output);
            } else {
                $exit_code = Console_Command_Event::RETURN_CODE_DISABLED;
            }
        } catch (\Throwable $e) {
            $event = new Console_Error_Event($input, $output, $e, $command);
            $this->dispatcher->dispatch($event, Console_Events::ERROR);
            $e = $event->get_error();
            if (0 === $exit_code = $event->get_exit_code()) {
                $e = null;
            }
        } finally {
            if ($registered_signals) {
                $this->get_signal_registry()->pop_previous_handlers();
            }
        }
        $event = new Console_Terminate_Event($command, $input, $output, $exit_code);
        $this->dispatcher->dispatch($event, Console_Events::TERMINATE);
        if (null !== $e) {
            throw $e;
        }
        return $event->get_exit_code();
    }
    /**
     * Gets the name of the command based on input.
     */
    protected function get_command_name(Input_Interface $input): ?string
    {
        return $this->single_command ? $this->default_command : $input->get_first_argument();
    }
    /**
     * Gets the default input definition.
     */
    protected function get_default_input_definition(): Input_Definition
    {
        return new Input_Definition([new Input_Argument('command', Input_Argument::REQUIRED, 'The command to execute'), new Input_Option('--help', '-h', Input_Option::VALUE_NONE, 'Display help for the given command. When no command is given display help for the <info>' . $this->default_command . '</info> command'), new Input_Option('--silent', null, Input_Option::VALUE_NONE, 'Do not output any message'), new Input_Option('--quiet', '-q', Input_Option::VALUE_NONE, 'Only errors are displayed. All other output is suppressed'), new Input_Option('--verbose', '-v|vv|vvv', Input_Option::VALUE_NONE, 'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug'), new Input_Option('--version', '-V', Input_Option::VALUE_NONE, 'Display this application version'), new Input_Option('--ansi', '', Input_Option::VALUE_NEGATABLE, 'Force (or disable --no-ansi) ANSI output'), new Input_Option('--no-interaction', '-n', Input_Option::VALUE_NONE, 'Do not ask any interactive question')]);
    }
    /**
     * Gets the default commands that should always be available.
     *
     * @return Command[]
     */
    protected function get_default_commands(): array
    {
        return [new Help_Command(), new List_Command(), new Complete_Command(), new Dump_Completion_Command()];
    }
    /**
     * Gets the default helper set with the helpers that should always be available.
     */
    protected function get_default_helper_set(): Helper_Set
    {
        return new Helper_Set([new Formatter_Helper(), new Debug_Formatter_Helper(), new Process_Helper(), new Question_Helper()]);
    }
    /**
     * Returns abbreviated suggestions in string format.
     */
    private function get_abbreviation_suggestions(array $abbrevs): string
    {
        return '    ' . implode("\n    ", $abbrevs);
    }
    /**
     * Returns the namespace part of the command name.
     *
     * This method is not part of public API and should not be used directly.
     */
    public function extract_namespace(string $name, ?int $limit = null): string
    {
        $parts = explode(':', $name, -1);
        return implode(':', null === $limit ? $parts : \array_slice($parts, 0, $limit));
    }
    /**
     * Finds alternative of $name among $collection,
     * if nothing is found in $collection, try in $abbrevs.
     *
     * @return string[]
     */
    private function find_alternatives(string $name, iterable $collection): array
    {
        $threshold = 1000.0;
        $alternatives = [];
        $collection_parts = [];
        foreach ($collection as $item) {
            $collection_parts[$item] = explode(':', (string) $item);
        }
        foreach (explode(':', $name) as $i => $subname) {
            foreach ($collection_parts as $collection_name => $parts) {
                $exists = isset($alternatives[$collection_name]);
                if (!isset($parts[$i]) && $exists) {
                    $alternatives[$collection_name] += $threshold;
                    continue;
                }
                if (!isset($parts[$i])) {
                    continue;
                }
                $lev = levenshtein($subname, $parts[$i]);
                if ($lev <= \strlen($subname) / 3 || '' !== $subname && str_contains($parts[$i], $subname)) {
                    $alternatives[$collection_name] = $exists ? $alternatives[$collection_name] + $lev : $lev;
                } elseif ($exists) {
                    $alternatives[$collection_name] += $threshold;
                }
            }
        }
        foreach ($collection as $item) {
            $lev = levenshtein($name, $item);
            if ($lev <= \strlen($name) / 3 || str_contains((string) $item, $name)) {
                $alternatives[$item] = isset($alternatives[$item]) ? $alternatives[$item] - $lev : $lev;
            }
        }
        $alternatives = array_filter($alternatives, static fn(float|int $lev): bool => $lev < 2 * $threshold);
        ksort($alternatives, \SORT_NATURAL | \SORT_FLAG_CASE);
        return array_keys($alternatives);
    }
    /**
     * Sets the default Command name.
     *
     * @return $this
     */
    public function set_default_command(string $command_name, bool $is_single_command = false): static
    {
        $this->default_command = explode('|', ltrim($command_name, '|'))[0];
        if ($is_single_command) {
            // Ensure the command exist
            $this->find($command_name);
            $this->single_command = true;
        }
        return $this;
    }
    /**
     * @internal
     */
    public function is_single_command(): bool
    {
        return $this->single_command;
    }
    private function split_string_by_width(string $string, int $width): array
    {
        // str_split is not suitable for multi-byte characters, we should use preg_split to get char array properly.
        // additionally, array_slice() is not enough as some character has doubled width.
        // we need a function to split string not by character count but by string width
        if (false === $encoding = mb_detect_encoding($string, null, true)) {
            return str_split($string, $width);
        }
        $utf8String = mb_convert_encoding($string, 'utf8', $encoding);
        $lines = [];
        $line = '';
        $offset = 0;
        while (preg_match('/.{1,10000}/u', $utf8String, $m, 0, $offset)) {
            $offset += \strlen($m[0]);
            foreach (preg_split('//u', $m[0]) as $char) {
                // test if $char could be appended to current line
                if (Helper::width($line . $char) <= $width) {
                    $line .= $char;
                    continue;
                }
                // if not, push current line to array and make new line
                $lines[] = str_pad($line, $width);
                $line = $char;
            }
        }
        $lines[] = \count($lines) ? str_pad($line, $width) : $line;
        mb_convert_variables($encoding, 'utf8', $lines);
        return $lines;
    }
    /**
     * Returns all namespaces of the command name.
     *
     * @return string[]
     */
    private function extract_all_namespaces(string $name): array
    {
        // -1 as third argument is needed to skip the command short name when exploding
        $parts = explode(':', $name, -1);
        $namespaces = [];
        foreach ($parts as $part) {
            if (\count($namespaces)) {
                $namespaces[] = end($namespaces) . ':' . $part;
            } else {
                $namespaces[] = $part;
            }
        }
        return $namespaces;
    }
    private function init(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;
        foreach ($this->get_default_commands() as $command) {
            $this->add_command($command);
        }
    }
}