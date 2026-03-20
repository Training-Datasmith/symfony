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
namespace Symfony\Component\Console\Signal_Registry;

final class Signal_Registry
{
    /**
     * @var array<int, array<callable>>
     */
    private array $signal_handlers = [];
    /**
     * @var array<array<int, array<callable>>>
     */
    private array $stack = [];
    /**
     * @var array<int, callable|int|string>
     */
    private array $original_handlers = [];
    public function __construct()
    {
        if (\function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }
    }
    public function register(int $signal, callable $signal_handler): void
    {
        $previous = pcntl_signal_get_handler($signal);
        if (!isset($this->original_handlers[$signal])) {
            $this->original_handlers[$signal] = $previous;
        }
        if (!isset($this->signal_handlers[$signal])) {
            if (\is_callable($previous) && $this->handle(...) !== $previous) {
                $this->signal_handlers[$signal][] = $previous;
            }
        }
        $this->signal_handlers[$signal][] = $signal_handler;
        pcntl_signal($signal, $this->handle(...));
    }
    public static function is_supported(): bool
    {
        return \function_exists('pcntl_signal');
    }
    /**
     * @internal
     */
    public function handle(int $signal): void
    {
        $count = \count($this->signal_handlers[$signal]);
        foreach ($this->signal_handlers[$signal] as $i => $signal_handler) {
            $has_next = $i !== $count - 1;
            $signal_handler($signal, $has_next);
        }
    }
    /**
     * Pushes the current active handlers onto the stack and clears the active list.
     *
     * This prepares the registry for a new set of handlers within a specific scope.
     *
     * @internal
     */
    public function push_current_handlers(): void
    {
        $this->stack[] = $this->signal_handlers;
        $this->signal_handlers = [];
    }
    /**
     * Restores the previous handlers from the stack, making them active.
     *
     * This also restores the original OS-level signal handler if no
     * more handlers are registered for a signal that was just popped.
     *
     * @internal
     */
    public function pop_previous_handlers(): void
    {
        $popped = $this->signal_handlers;
        $this->signal_handlers = array_pop($this->stack) ?? [];
        // Restore OS handler if no more Symfony handlers for this signal
        foreach ($popped as $signal => $handlers) {
            if (!($this->signal_handlers[$signal] ?? false) && isset($this->original_handlers[$signal])) {
                pcntl_signal($signal, $this->original_handlers[$signal]);
            }
        }
    }
    /**
     * @internal
     */
    public function schedule_alarm(int $seconds): void
    {
        pcntl_alarm($seconds);
    }
}