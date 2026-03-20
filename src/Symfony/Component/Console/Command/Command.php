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
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Exception\Exception_Interface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Helper\Helper_Interface;
use Symfony\Component\Console\Helper\Helper_Set;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Base class for all console commands.
 *
 * To create a command, extend this class and implement configure() and execute():
 *
 *   protected function configure(): void
 *   {
 *       $this->setName('greet')->addArgument('name', InputArgument::REQUIRED);
 *   }
 *
 *   protected function execute(InputInterface $input, OutputInterface $output): int
 *   {
 *       $output->writeln('Hello '.$input->getArgument('name'));
 *       return Command::SUCCESS;
 *   }
 *
 * Alternatively, use the #[AsCommand] attribute on the class to set name and description.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since 2.0
 */
class Command implements Signalable_Command_Interface
{
    // see https://tldp.org/LDP/abs/html/exitcodes.html
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;
    private ?Application $application = null;
    private ?string $name = null;
    private ?string $process_title = null;
    private array $aliases = [];
    private Input_Definition $definition;
    private bool $hidden = false;
    private string $help = '';
    private string $description = '';
    private ?Input_Definition $full_definition = null;
    private bool $ignore_validation_errors = false;
    private ?Invokable_Command $code = null;
    private array $synopsis = [];
    private array $usages = [];
    private ?Helper_Set $helper_set = null;
    /**
     * @param string|null $name The name of the command; passing null means it must be set in configure()
     *
     * @throws LogicException When the command name is empty
     */
    public function __construct(?string $name = null, ?callable $code = null)
    {
        $this->definition = new Input_Definition();
        $attribute = $this->get_command_attribute($code);
        if ($code) {
            $this->set_code($code);
        }
        if (null !== $name ??= $attribute?->name) {
            $aliases = explode('|', $name);
            if ('' === $name = array_shift($aliases)) {
                $this->set_hidden(true);
                $name = array_shift($aliases);
            }
            // we must not overwrite existing aliases, combine new ones with existing ones
            $aliases = array_unique([...$this->aliases, ...$aliases]);
            $this->set_aliases($aliases);
        }
        if (null !== $name) {
            $this->set_name($name);
        }
        if ('' === $this->description) {
            $this->set_description($attribute?->description ?? '');
        }
        if ('' === $this->help) {
            $this->set_help($attribute?->help ?? '');
        }
        foreach ($attribute?->usages ?? [] as $usage) {
            $this->add_usage($usage);
        }
        if (!$code && \is_callable($this) && self::class === (new \ReflectionMethod($this, 'execute'))->class) {
            $this->code = new Invokable_Command($this, $this(...));
        }
        $this->configure();
    }
    /**
     * Ignores validation errors.
     *
     * This is mainly useful for the help command.
     */
    public function ignore_validation_errors(): void
    {
        $this->ignore_validation_errors = true;
    }
    public function set_application(?Application $application): void
    {
        $this->application = $application;
        if ($application) {
            $this->set_helper_set($application->get_helper_set());
        } else {
            $this->helper_set = null;
        }
        $this->full_definition = null;
    }
    public function set_helper_set(Helper_Set $helper_set): void
    {
        $this->helper_set = $helper_set;
    }
    /**
     * Gets the helper set.
     */
    public function get_helper_set(): ?Helper_Set
    {
        return $this->helper_set;
    }
    /**
     * Gets the application instance for this command.
     */
    public function get_application(): ?Application
    {
        return $this->application;
    }
    /**
     * Checks whether the command is enabled or not in the current environment.
     *
     * Override this to check for x or y and return false if the command cannot
     * run properly under the current conditions.
     */
    public function is_enabled(): bool
    {
        return true;
    }
    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
    }
    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * Return Command::SUCCESS (0), Command::FAILURE (1), or Command::INVALID (2).
     * Using these constants instead of raw integers makes intent explicit and
     * allows static analysis to verify exit code correctness.
     *
     * @param Input_Interface  $input  The input interface bound to this command's definition
     * @param Output_Interface $output The output interface for writing messages to the console
     *
     * @return int Command::SUCCESS (0) if everything went fine, or an exit code
     *
     * @throws LogicException When this abstract method is not implemented in a subclass
     *
     * @see set_code() For setting executable code without subclassing
     *
     * @since 2.0
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        throw new LogicException('You must override the execute() method in the concrete command class.');
    }
    /**
     * Interacts with the user.
     *
     * This method is executed before the InputDefinition is validated.
     * This means that this is the only place where the command can
     * interactively ask for values of missing required arguments.
     */
    protected function interact(Input_Interface $input, Output_Interface $output): void
    {
    }
    /**
     * Initializes the command after the input has been bound and before the input
     * is validated.
     *
     * This is mainly useful when a lot of commands extends one main command
     * where some things need to be initialized based on the input arguments and options.
     *
     * @see InputInterface::bind()
     * @see InputInterface::validate()
     */
    protected function initialize(Input_Interface $input, Output_Interface $output): void
    {
    }
    /**
     * Runs the command.
     *
     * The code to execute is either defined directly with the
     * setCode() method or by overriding the execute() method
     * in a sub-class.
     *
     * @return int The command exit code
     *
     * @throws ExceptionInterface When input binding fails. Bypass this by calling {@link ignoreValidationErrors()}.
     *
     * @see setCode()
     * @see execute()
     */
    public function run(Input_Interface $input, Output_Interface $output): int
    {
        // add the application arguments and options
        $this->merge_application_definition();
        // bind the input against the command specific arguments/options
        try {
            $input->bind($this->get_definition());
        } catch (Exception_Interface $e) {
            if (!$this->ignore_validation_errors) {
                throw $e;
            }
        }
        $this->initialize($input, $output);
        if (null !== $this->process_title) {
            if (\function_exists('cli_set_process_title')) {
                if (!@cli_set_process_title($this->process_title)) {
                    if ('Darwin' === \PHP_OS) {
                        $output->writeln('<comment>Running "cli_set_process_title" as an unprivileged user is not supported on MacOS.</comment>', Output_Interface::VERBOSITY_VERY_VERBOSE);
                    } else {
                        cli_set_process_title($this->process_title);
                    }
                }
            } elseif (\function_exists('setproctitle')) {
                setproctitle($this->process_title);
            } elseif (Output_Interface::VERBOSITY_VERY_VERBOSE === $output->get_verbosity()) {
                $output->writeln('<comment>Install the proctitle PECL to be able to change the process title.</comment>');
            }
        }
        if ($input->is_interactive()) {
            $this->interact($input, $output);
            if ($this->code?->is_interactive()) {
                $this->code->interact($input, $output);
            }
        }
        // The command name argument is often omitted when a command is executed directly with its run() method.
        // It would fail the validation if we didn't make sure the command argument is present,
        // since it's required by the application.
        if ($input->has_argument('command') && null === $input->get_argument('command')) {
            $input->set_argument('command', $this->get_name());
        }
        $input->validate();
        if ($this->code) {
            return ($this->code)($input, $output);
        }
        return $this->execute($input, $output);
    }
    /**
     * Supplies suggestions when resolving possible completion options for input (e.g. option or argument).
     */
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        $definition = $this->get_definition();
        if (Completion_Input::TYPE_OPTION_VALUE === $input->get_completion_type() && $definition->has_option($input->get_completion_name())) {
            $definition->get_option($input->get_completion_name())->complete($input, $suggestions);
        } elseif (Completion_Input::TYPE_ARGUMENT_VALUE === $input->get_completion_type() && $definition->has_argument($input->get_completion_name())) {
            $definition->get_argument($input->get_completion_name())->complete($input, $suggestions);
        }
    }
    /**
     * Gets the code that is executed by the command.
     *
     * @return ?callable null if the code has not been set with setCode()
     */
    public function get_code(): ?callable
    {
        return $this->code?->get_code();
    }
    /**
     * Sets the code to execute when running this command.
     *
     * If this method is used, it overrides the code defined
     * in the execute() method.
     *
     * @param callable $code A callable(InputInterface $input, OutputInterface $output)
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     *
     * @see execute()
     */
    public function set_code(callable $code): static
    {
        $this->code = new Invokable_Command($this, $code);
        return $this;
    }
    /**
     * Merges the application definition with the command definition.
     *
     * This method is not part of public API and should not be used directly.
     *
     * @param bool $mergeArgs Whether to merge or not the Application definition arguments to Command definition arguments
     *
     * @internal
     */
    public function merge_application_definition(bool $merge_args = true): void
    {
        if (null === $this->application) {
            return;
        }
        $this->full_definition = new Input_Definition();
        $this->full_definition->set_options($this->definition->get_options());
        $this->full_definition->add_options($this->application->get_definition()->get_options());
        if ($merge_args) {
            $this->full_definition->set_arguments($this->application->get_definition()->get_arguments());
            $this->full_definition->add_arguments($this->definition->get_arguments());
        } else {
            $this->full_definition->set_arguments($this->definition->get_arguments());
        }
    }
    /**
     * Sets an array of argument and option instances.
     *
     * @return $this
     */
    public function set_definition(array|Input_Definition $definition): static
    {
        if ($definition instanceof Input_Definition) {
            $this->definition = $definition;
        } else {
            $this->definition->set_definition($definition);
        }
        $this->full_definition = null;
        return $this;
    }
    /**
     * Gets the InputDefinition attached to this Command.
     */
    public function get_definition(): Input_Definition
    {
        return $this->full_definition ?? $this->get_native_definition();
    }
    /**
     * Gets the InputDefinition to be used to create representations of this Command.
     *
     * Can be overridden to provide the original command representation when it would otherwise
     * be changed by merging with the application InputDefinition.
     *
     * This method is not part of public API and should not be used directly.
     */
    public function get_native_definition(): Input_Definition
    {
        $definition = $this->definition ?? throw new LogicException(\sprintf('Command class "%s" is not correctly initialized. You probably forgot to call the parent constructor.', static::class));
        if ($this->code && !$definition->get_arguments() && !$definition->get_options()) {
            $this->code->configure($definition);
        }
        return $definition;
    }
    /**
     * Adds an argument.
     *
     * @param                                                                               $mode            The argument mode: InputArgument::REQUIRED or InputArgument::OPTIONAL
     * @param                                                                               $default         The default value (for InputArgument::OPTIONAL mode only)
     * @param array|\Closure(CompletionInput,CompletionSuggestions):list<string|Suggestion> $suggestedValues The values used for input completion
     *
     * @return $this
     *
     * @throws InvalidArgumentException When argument mode is not valid
     */
    public function add_argument(string $name, ?int $mode = null, string $description = '', mixed $default = null, array|\Closure $suggested_values = []): static
    {
        $this->definition->add_argument(new Input_Argument($name, $mode, $description, $default, $suggested_values));
        $this->full_definition?->add_argument(new Input_Argument($name, $mode, $description, $default, $suggested_values));
        return $this;
    }
    /**
     * Adds an option.
     *
     * @param                                                                               $shortcut        The shortcuts, can be null, a string of shortcuts delimited by | or an array of shortcuts
     * @param                                                                               $mode            The option mode: One of the InputOption::VALUE_* constants
     * @param                                                                               $default         The default value (must be null for InputOption::VALUE_NONE)
     * @param array|\Closure(CompletionInput,CompletionSuggestions):list<string|Suggestion> $suggestedValues The values used for input completion
     *
     * @return $this
     *
     * @throws InvalidArgumentException If option mode is invalid or incompatible
     */
    public function add_option(string $name, string|array|null $shortcut = null, ?int $mode = null, string $description = '', mixed $default = null, array|\Closure $suggested_values = []): static
    {
        $this->definition->add_option(new Input_Option($name, $shortcut, $mode, $description, $default, $suggested_values));
        $this->full_definition?->add_option(new Input_Option($name, $shortcut, $mode, $description, $default, $suggested_values));
        return $this;
    }
    /**
     * Sets the name of the command.
     *
     * This method can set both the namespace and the name if
     * you separate them by a colon (:)
     *
     *     $command->setName('foo:bar');
     *
     * @return $this
     *
     * @throws InvalidArgumentException When the name is invalid
     */
    public function set_name(string $name): static
    {
        $this->validate_name($name);
        $this->name = $name;
        return $this;
    }
    /**
     * Sets the process title of the command.
     *
     * This feature should be used only when creating a long process command,
     * like a daemon.
     *
     * @return $this
     */
    public function set_process_title(string $title): static
    {
        $this->process_title = $title;
        return $this;
    }
    /**
     * Returns the command name.
     */
    public function get_name(): ?string
    {
        return $this->name;
    }
    /**
     * @param bool $hidden Whether or not the command should be hidden from the list of commands
     *
     * @return $this
     */
    public function set_hidden(bool $hidden = true): static
    {
        $this->hidden = $hidden;
        return $this;
    }
    /**
     * @return bool whether the command should be publicly shown or not
     */
    public function is_hidden(): bool
    {
        return $this->hidden;
    }
    /**
     * Sets the description for the command.
     *
     * @return $this
     */
    public function set_description(string $description): static
    {
        $this->description = $description;
        return $this;
    }
    /**
     * Returns the description for the command.
     */
    public function get_description(): string
    {
        return $this->description;
    }
    /**
     * Sets the help for the command.
     *
     * @return $this
     */
    public function set_help(string $help): static
    {
        $this->help = $help;
        return $this;
    }
    /**
     * Returns the help for the command.
     */
    public function get_help(): string
    {
        return $this->help;
    }
    /**
     * Returns the processed help for the command replacing the %command.name% and
     * %command.full_name% patterns with the real values dynamically.
     */
    public function get_processed_help(): string
    {
        $name = $this->name;
        $is_single_command = $this->application?->is_single_command();
        $placeholders = ['%command.name%', '%command.full_name%'];
        $replacements = [$name, $is_single_command ? $_SERVER['PHP_SELF'] : $_SERVER['PHP_SELF'] . ' ' . $name];
        return str_replace($placeholders, $replacements, $this->get_help() ?: $this->get_description());
    }
    /**
     * Sets the aliases for the command.
     *
     * @param string[] $aliases An array of aliases for the command
     *
     * @return $this
     *
     * @throws InvalidArgumentException When an alias is invalid
     */
    public function set_aliases(iterable $aliases): static
    {
        $list = [];
        foreach ($aliases as $alias) {
            $this->validate_name($alias);
            $list[] = $alias;
        }
        $this->aliases = \is_array($aliases) ? $aliases : $list;
        return $this;
    }
    /**
     * Returns the aliases for the command.
     */
    public function get_aliases(): array
    {
        return $this->aliases;
    }
    /**
     * Returns the synopsis for the command.
     *
     * @param bool $short Whether to show the short version of the synopsis (with options folded) or not
     */
    public function get_synopsis(bool $short = false): string
    {
        $key = $short ? 'short' : 'long';
        if (!isset($this->synopsis[$key])) {
            $this->synopsis[$key] = trim(\sprintf('%s %s', $this->name, $this->definition->get_synopsis($short)));
        }
        return $this->synopsis[$key];
    }
    /**
     * Add a command usage example, it'll be prefixed with the command name.
     *
     * @return $this
     */
    public function add_usage(string $usage): static
    {
        if (!str_starts_with($usage, (string) $this->name)) {
            $usage = \sprintf('%s %s', $this->name, $usage);
        }
        $this->usages[] = $usage;
        return $this;
    }
    /**
     * Returns alternative usages of the command.
     */
    public function get_usages(): array
    {
        return $this->usages;
    }
    /**
     * Gets a helper instance by name.
     *
     * @throws LogicException           if no HelperSet is defined
     * @throws InvalidArgumentException if the helper is not defined
     */
    public function get_helper(string $name): Helper_Interface
    {
        if (null === $this->helper_set) {
            throw new LogicException(\sprintf('Cannot retrieve helper "%s" because there is no HelperSet defined. Did you forget to add your command to the application or to set the application on the command using the setApplication() method? You can also set the HelperSet directly using the setHelperSet() method.', $name));
        }
        return $this->helper_set->get($name);
    }
    public function get_subscribed_signals(): array
    {
        return $this->code?->get_subscribed_signals() ?? [];
    }
    public function handle_signal(int $signal, int|false $previous_exit_code = 0): int|false
    {
        return $this->code?->handle_signal($signal, $previous_exit_code) ?? false;
    }
    /**
     * Validates a command name.
     *
     * It must be non-empty and parts can optionally be separated by ":".
     *
     * @throws InvalidArgumentException When the name is invalid
     */
    private function validate_name(string $name): void
    {
        if (!preg_match('/^[^\:]++(\:[^\:]++)*$/', $name)) {
            throw new InvalidArgumentException(\sprintf('Command name "%s" is invalid.', $name));
        }
    }
    private function get_command_attribute(?callable $code): ?As_Command
    {
        if (null === $code) {
            /** @var AsCommand|null $attribute */
            $attribute = ((new \ReflectionClass(static::class))->get_attributes(As_Command::class)[0] ?? null)?->new_instance();
            return $attribute;
        }
        $reflection = new \ReflectionFunction($code(...));
        if ($reflection->is_anonymous() || !$class = $reflection->get_closure_scope_class()) {
            throw new InvalidArgumentException(\sprintf('The command must be an instance of "%s", an invokable object or a method of an object.', self::class));
        }
        /** @var AsCommand|null $attribute */
        $attribute = ($reflection->get_attributes(As_Command::class)[0] ?? null)?->new_instance();
        if (!$attribute && '__invoke' === $reflection->get_name()) {
            /** @var AsCommand|null $attribute */
            $attribute = ($class->get_attributes(As_Command::class)[0] ?? null)?->new_instance();
        }
        if (!$attribute) {
            throw new LogicException(\sprintf('The command must use the "%s" attribute.', As_Command::class));
        }
        return $attribute;
    }
}