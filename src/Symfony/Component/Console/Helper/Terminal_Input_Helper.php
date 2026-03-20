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
namespace Symfony\Component\Console\Helper;

/**
 * TerminalInputHelper stops Ctrl-C and similar signals from leaving the terminal in
 * an unusable state if its settings have been modified when reading user input.
 * This can be an issue on non-Windows platforms.
 *
 * Usage:
 *
 *     $inputHelper = new TerminalInputHelper($inputStream);
 *
 *     ...change terminal settings
 *
 *     // Wait for input before all input reads
 *     $inputHelper->waitForInput();
 *
 *     ...read input
 *
 *     // Call finish to restore terminal settings and signal handlers
 *     $inputHelper->finish()
 *
 * @internal
 */
final class Terminal_Input_Helper
{
    private readonly bool $is_stdin;
    private string $initial_state = '';
    private int $signal_to_kill = 0;
    private array $signal_handlers = [];
    private array $target_signals = [];
    /**
     * @param resource $inputStream
     *
     * @throws \RuntimeException If unable to read terminal settings
     */
    public function __construct(private $input_stream, private readonly bool $with_stty = true)
    {
        $this->is_stdin = 'php://stdin' === stream_get_meta_data($this->input_stream)['uri'];
        if ($this->with_stty) {
            if (!\is_string($state = shell_exec('stty -g'))) {
                throw new \RuntimeException('Unable to read the terminal settings.');
            }
            $this->initial_state = $state;
            $this->create_signal_handlers();
        }
    }
    /**
     * Waits for input.
     */
    public function wait_for_input(): void
    {
        if ($this->is_stdin) {
            $r = [$this->input_stream];
            $w = [];
            // Allow signal handlers to run
            while (0 === @stream_select($r, $w, $w, 0, 100)) {
                $r = [$this->input_stream];
            }
        }
        if ($this->with_stty) {
            $this->check_for_kill_signal();
        }
    }
    /**
     * Restores terminal state and signal handlers.
     */
    public function finish(): void
    {
        if (!$this->with_stty) {
            return;
        }
        // Safeguard in case an unhandled kill signal exists
        $this->check_for_kill_signal();
        shell_exec('stty ' . $this->initial_state);
        $this->signal_to_kill = 0;
        foreach ($this->signal_handlers as $signal => $original_handler) {
            pcntl_signal($signal, $original_handler);
        }
        $this->signal_handlers = [];
        $this->target_signals = [];
    }
    private function create_signal_handlers(): void
    {
        if (!\function_exists('pcntl_async_signals') || !\function_exists('pcntl_signal')) {
            return;
        }
        pcntl_async_signals(true);
        $this->target_signals = [\SIGINT, \SIGQUIT, \SIGTERM];
        foreach ($this->target_signals as $signal) {
            $this->signal_handlers[$signal] = pcntl_signal_get_handler($signal);
            pcntl_signal($signal, function ($signal): void {
                // Save current state, then restore to initial state
                $current_state = shell_exec('stty -g');
                shell_exec('stty ' . $this->initial_state);
                $original_handler = $this->signal_handlers[$signal];
                if (\is_callable($original_handler)) {
                    $original_handler($signal);
                    // Handler did not exit, so restore to current state
                    shell_exec('stty ' . $current_state);
                    return;
                }
                // Not a callable, so SIG_DFL or SIG_IGN
                if (\SIG_DFL === $original_handler) {
                    $this->signal_to_kill = $signal;
                }
            });
        }
    }
    private function check_for_kill_signal(): void
    {
        if (\in_array($this->signal_to_kill, $this->target_signals, true)) {
            // Try posix_kill
            if (\function_exists('posix_kill')) {
                pcntl_signal($this->signal_to_kill, \SIG_DFL);
                posix_kill(getmypid(), $this->signal_to_kill);
            }
            // Best attempt fallback
            exit(128 + $this->signal_to_kill);
        }
    }
}