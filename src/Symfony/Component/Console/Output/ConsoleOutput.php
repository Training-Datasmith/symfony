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

use Symfony\Component\Console\Formatter\Output_Formatter_Interface;
/**
 * ConsoleOutput is the default class for all CLI output. It uses STDOUT and STDERR.
 *
 * This class is a convenient wrapper around `StreamOutput` for both STDOUT and STDERR.
 *
 *     $output = new ConsoleOutput();
 *
 * This is equivalent to:
 *
 *     $output = new StreamOutput(fopen('php://stdout', 'w'));
 *     $stdErr = new StreamOutput(fopen('php://stderr', 'w'));
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Console_Output extends Stream_Output implements Console_Output_Interface
{
    private Output_Interface $stderr;
    private array $console_section_outputs = [];
    /**
     * @param int                           $verbosity The verbosity level (one of the VERBOSITY constants in OutputInterface)
     * @param bool|null                     $decorated Whether to decorate messages (null for auto-guessing)
     * @param OutputFormatterInterface|null $formatter Output formatter instance (null to use default OutputFormatter)
     */
    public function __construct(int $verbosity = self::VERBOSITY_NORMAL, ?bool $decorated = null, ?Output_Formatter_Interface $formatter = null)
    {
        parent::__construct($this->open_output_stream(), $verbosity, $decorated, $formatter);
        if (null === $formatter) {
            // for BC reasons, stdErr has it own Formatter only when user don't inject a specific formatter.
            $this->stderr = new Stream_Output($this->open_error_stream(), $verbosity, $decorated);
            return;
        }
        $actual_decorated = $this->is_decorated();
        $this->stderr = new Stream_Output($this->open_error_stream(), $verbosity, $decorated, $this->get_formatter());
        if (null === $decorated) {
            $this->set_decorated($actual_decorated && $this->stderr->is_decorated());
        }
    }
    /**
     * Creates a new output section.
     */
    public function section(): Console_Section_Output
    {
        return new Console_Section_Output($this->get_stream(), $this->console_section_outputs, $this->get_verbosity(), $this->is_decorated(), $this->get_formatter());
    }
    public function set_decorated(bool $decorated): void
    {
        parent::set_decorated($decorated);
        $this->stderr->set_decorated($decorated);
    }
    public function set_formatter(Output_Formatter_Interface $formatter): void
    {
        parent::set_formatter($formatter);
        $this->stderr->set_formatter($formatter);
    }
    public function set_verbosity(int $level): void
    {
        parent::set_verbosity($level);
        $this->stderr->set_verbosity($level);
    }
    public function get_error_output(): Output_Interface
    {
        return $this->stderr;
    }
    public function set_error_output(Output_Interface $error): void
    {
        $this->stderr = $error;
    }
    /**
     * Returns true if current environment supports writing console output to
     * STDOUT.
     */
    protected function has_stdout_support(): bool
    {
        return false === $this->is_running_os400();
    }
    /**
     * Returns true if current environment supports writing console output to
     * STDERR.
     */
    protected function has_stderr_support(): bool
    {
        return false === $this->is_running_os400();
    }
    /**
     * Checks if current executing environment is IBM iSeries (OS400), which
     * doesn't properly convert character-encodings between ASCII to EBCDIC.
     */
    private function is_running_os400(): bool
    {
        $checks = [\function_exists('php_uname') ? php_uname('s') : '', getenv('OSTYPE'), \PHP_OS];
        return false !== stripos(implode(';', $checks), 'OS400');
    }
    /**
     * @return resource
     */
    private function open_output_stream()
    {
        if (!$this->has_stdout_support()) {
            return fopen('php://output', 'w');
        }
        // Use STDOUT when possible to prevent from opening too many file descriptors
        return \defined('STDOUT') ? \STDOUT : (@fopen('php://stdout', 'w') ?: fopen('php://output', 'w'));
    }
    /**
     * @return resource
     */
    private function open_error_stream()
    {
        if (!$this->has_stderr_support()) {
            return fopen('php://output', 'w');
        }
        // Use STDERR when possible to prevent from opening too many file descriptors
        return \defined('STDERR') ? \STDERR : (@fopen('php://stderr', 'w') ?: fopen('php://output', 'w'));
    }
}