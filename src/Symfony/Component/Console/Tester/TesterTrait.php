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

use Php_Unit\Framework\Assert;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Console_Output;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Output\Stream_Output;
use Symfony\Component\Console\Tester\Constraint\Command_Failed;
use Symfony\Component\Console\Tester\Constraint\Command_Is_Invalid;
use Symfony\Component\Console\Tester\Constraint\Command_Is_Successful;
/**
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 */
trait Tester_Trait
{
    private Stream_Output $output;
    /**
     * @var list<string>
     */
    private array $inputs = [];
    private bool $capture_streams_independently = false;
    private Input_Interface $input;
    private int $status_code;
    /**
     * Gets the display returned by the last execution of the command or application.
     *
     * @throws \RuntimeException If it's called before the execute method
     */
    public function get_display(bool $normalize = false): string
    {
        if (!isset($this->output)) {
            throw new \RuntimeException('Output not initialized, did you execute the command before requesting the display?');
        }
        rewind($this->output->get_stream());
        $display = stream_get_contents($this->output->get_stream());
        if ($normalize) {
            return str_replace(\PHP_EOL, "\n", $display);
        }
        return $display;
    }
    /**
     * Gets the output written to STDERR by the application.
     *
     * @param bool $normalize Whether to normalize end of lines to \n or not
     */
    public function get_error_output(bool $normalize = false): string
    {
        if (!$this->capture_streams_independently) {
            throw new \LogicException('The error output is not available when the tester is run without "capture_stderr_separately" option set.');
        }
        rewind($this->output->get_error_output()->get_stream());
        $display = stream_get_contents($this->output->get_error_output()->get_stream());
        if ($normalize) {
            return str_replace(\PHP_EOL, "\n", $display);
        }
        return $display;
    }
    /**
     * Gets the input instance used by the last execution of the command or application.
     */
    public function get_input(): Input_Interface
    {
        return $this->input;
    }
    /**
     * Gets the output instance used by the last execution of the command or application.
     */
    public function get_output(): Output_Interface
    {
        return $this->output;
    }
    /**
     * Gets the status code returned by the last execution of the command or application.
     *
     * @throws \RuntimeException If it's called before the execute method
     */
    public function get_status_code(): int
    {
        return $this->status_code ?? throw new \RuntimeException('Status code not initialized, did you execute the command before requesting the status code?');
    }
    public function assert_command_is_successful(string $message = ''): void
    {
        Assert::assert_that($this->status_code, new Command_Is_Successful(), $message);
    }
    public function assert_command_failed(string $message = ''): void
    {
        Assert::assert_that($this->status_code, new Command_Failed(), $message);
    }
    public function assert_command_is_invalid(string $message = ''): void
    {
        Assert::assert_that($this->status_code, new Command_Is_Invalid(), $message);
    }
    /**
     * Sets the user inputs.
     *
     * @param list<string> $inputs An array of strings representing each input
     *                             passed to the command input stream
     *
     * @return $this
     */
    public function set_inputs(array $inputs): static
    {
        $this->inputs = $inputs;
        return $this;
    }
    /**
     * Initializes the output property.
     *
     * Available options:
     *
     *  * decorated:                 Sets the output decorated flag
     *  * verbosity:                 Sets the output verbosity flag
     *  * capture_stderr_separately: Make output of stdOut and stdErr separately available
     */
    private function init_output(array $options): void
    {
        $this->capture_streams_independently = $options['capture_stderr_separately'] ?? false;
        if (!$this->capture_streams_independently) {
            $this->output = new Stream_Output(fopen('php://memory', 'w', false));
            if (isset($options['decorated'])) {
                $this->output->set_decorated($options['decorated']);
            }
            if (isset($options['verbosity'])) {
                $this->output->set_verbosity($options['verbosity']);
            }
        } else {
            $this->output = new Console_Output($options['verbosity'] ?? Console_Output::VERBOSITY_NORMAL, $options['decorated'] ?? null);
            $error_output = new Stream_Output(fopen('php://memory', 'w', false));
            $error_output->set_formatter($this->output->get_formatter());
            $error_output->set_verbosity($this->output->get_verbosity());
            $error_output->set_decorated($this->output->is_decorated());
            $reflected_output = new \Reflection_Object($this->output);
            $str_err_property = $reflected_output->get_property('stderr');
            $str_err_property->set_value($this->output, $error_output);
            $reflected_parent = $reflected_output->get_parent_class();
            $stream_property = $reflected_parent->get_property('stream');
            $stream_property->set_value($this->output, fopen('php://memory', 'w', false));
        }
    }
    /**
     * @param list<string> $inputs
     *
     * @return resource
     */
    private static function create_stream(array $inputs)
    {
        $stream = fopen('php://memory', 'r+', false);
        foreach ($inputs as $input) {
            fwrite($stream, $input);
            if (!str_ends_with($input, "\x04")) {
                fwrite($stream, \PHP_EOL);
            }
        }
        rewind($stream);
        return $stream;
    }
}