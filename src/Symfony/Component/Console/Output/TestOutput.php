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
namespace Symfony\Component\Console\Output;

use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Formatter\Output_Formatter_Interface;
/**
 * @internal
 *
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
final readonly class Test_Output implements Console_Output_Interface
{
    private Output_Interface $inner_output;
    private Output_Interface $inner_error_output;
    private Output_Interface $display_output;
    private Combined_Output $output;
    private Combined_Output $error_output;
    private Output_Formatter_Interface $formatter;
    /**
     * @param OutputInterface::VERBOSITY_* $verbosity
     */
    public function __construct(private bool $decorated, private int $verbosity, ?Output_Formatter_Interface $formatter = null)
    {
        $this->formatter = $formatter ?? new Output_Formatter($decorated);
        $this->formatter->set_decorated($decorated);
        $this->inner_output = self::create_output($this);
        $this->inner_error_output = self::create_output($this);
        $this->display_output = self::create_output($this);
        $this->output = new Combined_Output([$this->inner_output, $this->display_output]);
        $this->error_output = new Combined_Output([$this->inner_error_output, $this->display_output]);
    }
    public function get_output_contents(): string
    {
        return $this->get_stream_contents($this->inner_output);
    }
    public function get_error_output_contents(): string
    {
        return $this->get_stream_contents($this->inner_error_output);
    }
    public function get_display_contents(): string
    {
        return $this->get_stream_contents($this->display_output);
    }
    public function get_error_output(): Output_Interface
    {
        return $this->error_output;
    }
    public function set_error_output(Output_Interface $error): void
    {
        throw new LogicException('TestOutput does not support modifying the error output.');
    }
    public function section(): Console_Section_Output
    {
        throw new LogicException('ConsoleSectionOutput is not supported by TestOutput.');
    }
    public function write(iterable|string $messages, bool $newline = false, int $options = 0): void
    {
        $this->output->write(...\func_get_args());
    }
    public function writeln(iterable|string $messages, int $options = 0): void
    {
        $this->output->writeln(...\func_get_args());
    }
    public function set_verbosity(int $level): void
    {
        throw new LogicException('TestOutput does not support modifying the verbosity.');
    }
    public function get_verbosity(): int
    {
        return $this->verbosity;
    }
    public function is_silent(): bool
    {
        return self::VERBOSITY_SILENT === $this->verbosity;
    }
    public function is_quiet(): bool
    {
        return self::VERBOSITY_QUIET === $this->verbosity;
    }
    public function is_verbose(): bool
    {
        return self::VERBOSITY_VERBOSE <= $this->verbosity;
    }
    public function is_very_verbose(): bool
    {
        return self::VERBOSITY_VERY_VERBOSE <= $this->verbosity;
    }
    public function is_debug(): bool
    {
        return self::VERBOSITY_DEBUG <= $this->verbosity;
    }
    public function set_decorated(bool $decorated): void
    {
        throw new LogicException('TestOutput does not support modifying the decorated flag.');
    }
    public function is_decorated(): bool
    {
        return $this->decorated;
    }
    public function set_formatter(Output_Formatter_Interface $formatter): void
    {
        throw new LogicException('TestOutput does not support modifying the formatter.');
    }
    public function get_formatter(): Output_Formatter_Interface
    {
        return $this->formatter;
    }
    private static function create_output(Output_Interface $config): Stream_Output
    {
        if (false === $stream = fopen('php://memory', 'w')) {
            throw new RuntimeException('Failed to open stream.');
        }
        return new Stream_Output($stream, $config->get_verbosity(), $config->is_decorated(), $config->get_formatter());
    }
    private function get_stream_contents(Stream_Output $output): string
    {
        $stream = $output->get_stream();
        rewind($stream);
        if (false === $contents = stream_get_contents($stream)) {
            throw new RuntimeException('Failed to read stream contents.');
        }
        return $contents;
    }
}