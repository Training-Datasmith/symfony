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
namespace Symfony\Component\Console\Tester;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Formatter\Output_Formatter_Interface;
use Symfony\Component\Console\Input\Array_Input;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Output\Test_Output;
/**
 * Eases the testing of console commands.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
class Command_Tester
{
    use Tester_Trait;
    private Command $command;
    /**
     * @param OutputInterface::VERBOSITY_* $verbosity
     */
    public function __construct(callable|Command $command, private ?bool $interactive = null, private bool $decorated = false, private int $verbosity = Output_Interface::VERBOSITY_NORMAL, private ?Output_Formatter_Interface $output_formatter = new Output_Formatter())
    {
        $this->command = $command instanceof Command ? $command : new Command(null, $command);
    }
    public function set_interactive(bool $interactive): void
    {
        $this->interactive = $interactive;
    }
    public function set_decorated(bool $decorated): void
    {
        $this->decorated = $decorated;
    }
    /**
     * @param OutputInterface::VERBOSITY_* $level
     */
    public function set_verbosity(int $level): void
    {
        $this->verbosity = $level;
    }
    public function set_output_formatter(Output_Formatter_Interface $output_formatter): void
    {
        $this->output_formatter = $output_formatter;
    }
    /**
     * Runs the command with the result-based testing API.
     *
     * This method is intended for new tests and returns an ExecutionResult,
     * which exposes output, error output and combined display in a single object.
     *
     * Unlike execute(), this method does not rely on state read back from TesterTrait.
     *
     * @param array                           $input             An array of command arguments and options
     * @param string[]                        $interactiveInputs An array of strings representing each input passed to the command input stream
     * @param OutputInterface::VERBOSITY_*    $verbosity
     * @param array<\Closure(string): string> $normalizers
     */
    public function run(array $input = [], array $interactive_inputs = [], ?bool $interactive = null, ?bool $decorated = null, ?int $verbosity = null, array $normalizers = []): Execution_Result
    {
        $input = $this->create_input($input, $interactive_inputs, $interactive);
        $test_output = new Test_Output($decorated ?? $this->decorated, $verbosity ?? $this->verbosity, $this->output_formatter);
        $status_code = $this->command->run($input, $test_output);
        return Execution_Result::from_execution($input, $status_code, $test_output, $normalizers);
    }
    /**
     * Executes the command with the legacy stateful testing API.
     *
     * Use this method when interacting with the historical TesterTrait-based API,
     * e.g. getDisplay(), getErrorOutput(), getStatusCode() and assertCommandIsSuccessful().
     *
     * Prefer run() for new tests, as it returns an ExecutionResult object with
     * explicit output streams and dedicated assertions.
     *
     * Available execution options:
     *
     *  * interactive:               Sets the input interactive flag
     *  * decorated:                 Sets the output decorated flag
     *  * verbosity:                 Sets the output verbosity flag
     *  * capture_stderr_separately: Make output of stdOut and stdErr separately available
     *
     * @param array $input   An array of command arguments and options
     * @param array $options An array of execution options
     *
     * @return int The command exit code
     */
    public function execute(array $input, array $options = []): int
    {
        $this->input = $this->create_input($input, $this->inputs, $options['interactive'] ?? $this->interactive);
        if (!isset($options['decorated'])) {
            $options['decorated'] = $this->decorated;
        }
        $this->init_output($options);
        return $this->status_code = $this->command->run($this->input, $this->output);
    }
    private function create_input(array $input, array $interactive_inputs = [], ?bool $interactive = null): Input_Interface
    {
        if (!isset($input['command']) && $this->command->get_application()?->get_definition()->has_argument('command')) {
            $input = array_merge(['command' => $this->command->get_name()], $input);
        }
        $input = new Array_Input($input);
        // Use an in-memory input stream even if no inputs are set so that QuestionHelper::ask() does not rely on the blocking STDIN.
        $input->set_stream(self::create_stream($interactive_inputs));
        if (null !== $interactive ??= $this->interactive) {
            $input->set_interactive($interactive);
        }
        return $input;
    }
}