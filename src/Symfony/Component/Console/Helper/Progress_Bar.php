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

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Console_Section_Output;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Terminal;
/**
 * The ProgressBar provides helpers to display progress output.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Chris Jones <leeked@gmail.com>
 */
final class Progress_Bar
{
    public const FORMAT_VERBOSE = 'verbose';
    public const FORMAT_VERY_VERBOSE = 'very_verbose';
    public const FORMAT_DEBUG = 'debug';
    public const FORMAT_NORMAL = 'normal';
    private const FORMAT_VERBOSE_NOMAX = 'verbose_nomax';
    private const FORMAT_VERY_VERBOSE_NOMAX = 'very_verbose_nomax';
    private const FORMAT_DEBUG_NOMAX = 'debug_nomax';
    private const FORMAT_NORMAL_NOMAX = 'normal_nomax';
    private int $bar_width = 28;
    private string $bar_char;
    private string $empty_bar_char = '-';
    private string $progress_char = '>';
    private ?string $format = null;
    private ?string $internal_format = null;
    private ?int $redraw_freq = 1;
    private int $write_count = 0;
    private float $last_write_time = 0;
    private float $min_seconds_between_redraws = 0;
    private float $max_seconds_between_redraws = 1;
    private readonly Output_Interface $output;
    private int $step = 0;
    private int $starting_step = 0;
    private ?int $max = null;
    private int $start_time;
    private int $step_width;
    private float $percent = 0.0;
    private array $messages = [];
    private bool $overwrite = true;
    private readonly Terminal $terminal;
    private ?string $previous_message = null;
    private readonly Cursor $cursor;
    private array $placeholders = [];
    private static array $formatters;
    private static array $formats;
    /**
     * @param int $max Maximum steps (0 if unknown)
     */
    public function __construct(Output_Interface $output, int $max = 0, float $min_seconds_between_redraws = 1 / 25)
    {
        if ($output instanceof Console_Output_Interface) {
            $output = $output->get_error_output();
        }
        $this->output = $output;
        $this->set_max_steps($max);
        $this->terminal = new Terminal();
        if (0 < $min_seconds_between_redraws) {
            $this->redraw_freq = null;
            $this->min_seconds_between_redraws = $min_seconds_between_redraws;
        }
        if (!$this->output->is_decorated()) {
            // disable overwrite when output does not support ANSI codes.
            $this->overwrite = false;
            // set a reasonable redraw frequency so output isn't flooded
            $this->redraw_freq = null;
        }
        $this->start_time = time();
        $this->cursor = new Cursor($output);
    }
    /**
     * Sets a placeholder formatter for a given name, globally for all instances of ProgressBar.
     *
     * This method also allow you to override an existing placeholder.
     *
     * @param string                       $name     The placeholder name (including the delimiter char like %)
     * @param callable(ProgressBar):string $callable A PHP callable
     */
    public static function set_placeholder_formatter_definition(string $name, callable $callable): void
    {
        self::$formatters ??= self::init_placeholder_formatters();
        self::$formatters[$name] = $callable;
    }
    /**
     * Gets the placeholder formatter for a given name.
     *
     * @param string $name The placeholder name (including the delimiter char like %)
     */
    public static function get_placeholder_formatter_definition(string $name): ?callable
    {
        self::$formatters ??= self::init_placeholder_formatters();
        return self::$formatters[$name] ?? null;
    }
    /**
     * Sets a placeholder formatter for a given name, for this instance only.
     *
     * @param callable(ProgressBar):string $callable A PHP callable
     */
    public function set_placeholder_formatter(string $name, callable $callable): void
    {
        $this->placeholders[$name] = $callable;
    }
    /**
     * Gets the placeholder formatter for a given name.
     *
     * @param string $name The placeholder name (including the delimiter char like %)
     */
    public function get_placeholder_formatter(string $name): ?callable
    {
        return $this->placeholders[$name] ?? $this::get_placeholder_formatter_definition($name);
    }
    /**
     * Sets a format for a given name.
     *
     * This method also allow you to override an existing format.
     *
     * @param string $name   The format name
     * @param string $format A format string
     */
    public static function set_format_definition(string $name, string $format): void
    {
        self::$formats ??= self::init_formats();
        self::$formats[$name] = $format;
    }
    /**
     * Gets the format for a given name.
     *
     * @param string $name The format name
     */
    public static function get_format_definition(string $name): ?string
    {
        self::$formats ??= self::init_formats();
        return self::$formats[$name] ?? null;
    }
    /**
     * Associates a text with a named placeholder.
     *
     * The text is displayed when the progress bar is rendered but only
     * when the corresponding placeholder is part of the custom format line
     * (by wrapping the name with %).
     *
     * @param string $message The text to associate with the placeholder
     * @param string $name    The name of the placeholder
     */
    public function set_message(string $message, string $name = 'message'): void
    {
        $this->messages[$name] = $message;
    }
    public function get_message(string $name = 'message'): ?string
    {
        return $this->messages[$name] ?? null;
    }
    public function get_start_time(): int
    {
        return $this->start_time;
    }
    public function get_max_steps(): int
    {
        return $this->max ?? 0;
    }
    public function get_progress(): int
    {
        return $this->step;
    }
    private function get_step_width(): int
    {
        return $this->step_width;
    }
    public function get_progress_percent(): float
    {
        return $this->percent;
    }
    public function get_bar_offset(): float
    {
        return floor(null !== $this->max ? $this->percent * $this->bar_width : (null === $this->redraw_freq ? (int) (min(5, $this->bar_width / 15) * $this->write_count) : $this->step) % $this->bar_width);
    }
    public function get_estimated(): float
    {
        if (0 === $this->step || $this->step === $this->starting_step) {
            return 0;
        }
        return round((time() - $this->start_time) / ($this->step - $this->starting_step) * $this->max);
    }
    public function get_remaining(): float
    {
        if (0 === $this->step || $this->step === $this->starting_step) {
            return 0;
        }
        return round((time() - $this->start_time) / ($this->step - $this->starting_step) * ($this->max - $this->step));
    }
    public function set_bar_width(int $size): void
    {
        $this->bar_width = max(1, $size);
    }
    public function get_bar_width(): int
    {
        return $this->bar_width;
    }
    public function set_bar_character(string $char): void
    {
        $this->bar_char = $char;
    }
    public function get_bar_character(): string
    {
        return $this->bar_char ?? (null !== $this->max ? '=' : $this->empty_bar_char);
    }
    public function set_empty_bar_character(string $char): void
    {
        $this->empty_bar_char = $char;
    }
    public function get_empty_bar_character(): string
    {
        return $this->empty_bar_char;
    }
    public function set_progress_character(string $char): void
    {
        $this->progress_char = $char;
    }
    public function get_progress_character(): string
    {
        return $this->progress_char;
    }
    public function set_format(string $format): void
    {
        $this->format = null;
        $this->internal_format = $format;
    }
    /**
     * Sets the redraw frequency.
     *
     * @param int|null $freq The frequency in steps
     */
    public function set_redraw_frequency(?int $freq): void
    {
        $this->redraw_freq = null !== $freq ? max(1, $freq) : null;
    }
    public function min_seconds_between_redraws(float $seconds): void
    {
        $this->min_seconds_between_redraws = $seconds;
    }
    public function max_seconds_between_redraws(float $seconds): void
    {
        $this->max_seconds_between_redraws = $seconds;
    }
    /**
     * Returns an iterator that will automatically update the progress bar when iterated.
     *
     * @template TKey
     * @template TValue
     *
     * @param iterable<TKey, TValue> $iterable
     * @param int|null               $max      Number of steps to complete the bar (0 if indeterminate), if null it will be inferred from $iterable
     *
     * @return iterable<TKey, TValue>
     */
    public function iterate(iterable $iterable, ?int $max = null): iterable
    {
        if (0 === $max) {
            $max = null;
        }
        $max ??= is_countable($iterable) ? \count($iterable) : null;
        if (0 === $max) {
            $this->max = 0;
            $this->step_width = 2;
            $this->finish();
            return;
        }
        $this->start($max);
        foreach ($iterable as $key => $value) {
            yield $key => $value;
            $this->advance();
        }
        $this->finish();
    }
    /**
     * Starts the progress output.
     *
     * @param int|null $max     Number of steps to complete the bar (0 if indeterminate), null to leave unchanged
     * @param int      $startAt The starting point of the bar (useful e.g. when resuming a previously started bar)
     */
    public function start(?int $max = null, int $start_at = 0): void
    {
        $this->start_time = time();
        $this->step = $start_at;
        $this->starting_step = $start_at;
        $start_at > 0 ? $this->set_progress($start_at) : $this->percent = 0.0;
        if (null !== $max) {
            $this->set_max_steps($max);
        }
        $this->display();
    }
    /**
     * Advances the progress output X steps.
     *
     * @param int $step Number of steps to advance
     */
    public function advance(int $step = 1): void
    {
        $this->set_progress($this->step + $step);
    }
    /**
     * Sets whether to overwrite the progressbar, false for new line.
     */
    public function set_overwrite(bool $overwrite): void
    {
        $this->overwrite = $overwrite;
    }
    public function set_progress(int $step): void
    {
        if ($this->max && $step > $this->max) {
            $this->max = $step;
        } elseif ($step < 0) {
            $step = 0;
        }
        $redraw_freq = $this->redraw_freq ?? ($this->max ?? 10) / 10;
        $prev_period = $redraw_freq ? (int) ($this->step / $redraw_freq) : 0;
        $curr_period = $redraw_freq ? (int) ($step / $redraw_freq) : 0;
        $this->step = $step;
        $this->percent = match ($this->max) {
            null => 0,
            0 => 1,
            default => (float) $this->step / $this->max,
        };
        $time_interval = microtime(true) - $this->last_write_time;
        // Draw regardless of other limits
        if ($this->max === $step) {
            $this->display();
            return;
        }
        // Throttling
        if ($time_interval < $this->min_seconds_between_redraws) {
            return;
        }
        // Draw each step period, but not too late
        if ($prev_period !== $curr_period || $time_interval >= $this->max_seconds_between_redraws) {
            $this->display();
        }
    }
    public function set_max_steps(?int $max): void
    {
        if (0 === $max) {
            $max = null;
        }
        $this->format = null;
        if (null === $max) {
            $this->max = null;
            $this->step_width = 4;
        } else {
            $this->max = max(0, $max);
            $this->step_width = Helper::width((string) $this->max);
        }
    }
    /**
     * Finishes the progress output.
     */
    public function finish(): void
    {
        if (null === $this->max) {
            $this->max = $this->step;
        }
        if (($this->step === $this->max || null === $this->max) && !$this->overwrite) {
            // prevent double 100% output
            return;
        }
        $this->set_progress($this->max ?? $this->step);
    }
    /**
     * Outputs the current progress string.
     */
    public function display(): void
    {
        if (Output_Interface::VERBOSITY_QUIET === $this->output->get_verbosity()) {
            return;
        }
        if (null === $this->format) {
            $this->set_real_format($this->internal_format ?: $this->determine_best_format());
        }
        $this->overwrite($this->build_line());
    }
    /**
     * Removes the progress bar from the current line.
     *
     * This is useful if you wish to write some output
     * while a progress bar is running.
     * Call display() to show the progress bar again.
     */
    public function clear(): void
    {
        if (!$this->overwrite) {
            return;
        }
        if (null === $this->format) {
            $this->set_real_format($this->internal_format ?: $this->determine_best_format());
        }
        $this->overwrite('');
    }
    private function set_real_format(string $format): void
    {
        // try to use the _nomax variant if available
        if (!$this->max && null !== self::get_format_definition($format . '_nomax')) {
            $this->format = self::get_format_definition($format . '_nomax');
        } elseif (null !== self::get_format_definition($format)) {
            $this->format = self::get_format_definition($format);
        } else {
            $this->format = $format;
        }
    }
    /**
     * Overwrites a previous message to the output.
     */
    private function overwrite(string $message): void
    {
        if ($this->previous_message === $message) {
            return;
        }
        $original_message = $message;
        if ($this->overwrite) {
            if (null !== $this->previous_message) {
                if ($this->output instanceof Console_Section_Output) {
                    $message_lines = explode("\n", $this->previous_message);
                    $line_count = \count($message_lines);
                    $last_line_without_decoration = Helper::remove_decoration($this->output->get_formatter(), end($message_lines) ?? '');
                    // When the last previous line is empty (without formatting) it is already cleared by the section output, so we don't need to clear it again
                    if ('' === $last_line_without_decoration) {
                        --$line_count;
                    }
                    foreach ($message_lines as $message_line) {
                        $message_line_length = Helper::width(Helper::remove_decoration($this->output->get_formatter(), $message_line));
                        if ($message_line_length > $this->terminal->get_width()) {
                            $line_count += floor($message_line_length / $this->terminal->get_width());
                        }
                    }
                    $this->output->clear($line_count);
                } else {
                    $line_count = substr_count($this->previous_message, "\n");
                    for ($i = 0; $i < $line_count; ++$i) {
                        $this->cursor->move_to_column(1);
                        $this->cursor->clear_line();
                        $this->cursor->move_up();
                    }
                    $this->cursor->move_to_column(1);
                    $this->cursor->clear_line();
                }
            }
        } elseif ($this->step > 0) {
            $message = \PHP_EOL . $message;
        }
        $this->previous_message = $original_message;
        $this->last_write_time = microtime(true);
        $this->output->write($message);
        ++$this->write_count;
    }
    private function determine_best_format(): string
    {
        return match ($this->output->get_verbosity()) {
            // OutputInterface::VERBOSITY_QUIET: display is disabled anyway
            Output_Interface::VERBOSITY_VERBOSE => $this->max ? self::FORMAT_VERBOSE : self::FORMAT_VERBOSE_NOMAX,
            Output_Interface::VERBOSITY_VERY_VERBOSE => $this->max ? self::FORMAT_VERY_VERBOSE : self::FORMAT_VERY_VERBOSE_NOMAX,
            Output_Interface::VERBOSITY_DEBUG => $this->max ? self::FORMAT_DEBUG : self::FORMAT_DEBUG_NOMAX,
            default => $this->max ? self::FORMAT_NORMAL : self::FORMAT_NORMAL_NOMAX,
        };
    }
    private static function init_placeholder_formatters(): array
    {
        return ['bar' => static function (self $bar, Output_Interface $output): string {
            $complete_bars = $bar->get_bar_offset();
            $display = str_repeat($bar->get_bar_character(), $complete_bars);
            if ($complete_bars < $bar->get_bar_width()) {
                $empty_bars = $bar->get_bar_width() - $complete_bars - Helper::length(Helper::remove_decoration($output->get_formatter(), $bar->get_progress_character()));
                $display .= $bar->get_progress_character() . str_repeat($bar->get_empty_bar_character(), $empty_bars);
            }
            return $display;
        }, 'elapsed' => static fn(self $bar): string => Helper::format_time(time() - $bar->get_start_time(), 2), 'remaining' => static function (self $bar): string {
            if (null === $bar->max) {
                throw new LogicException('Unable to display the remaining time if the maximum number of steps is not set.');
            }
            return Helper::format_time($bar->get_remaining(), 2);
        }, 'estimated' => static function (self $bar): string {
            if (null === $bar->max) {
                throw new LogicException('Unable to display the estimated time if the maximum number of steps is not set.');
            }
            return Helper::format_time($bar->get_estimated(), 2);
        }, 'memory' => static fn(self $bar): string => Helper::format_memory(memory_get_usage(true)), 'current' => static fn(self $bar): string => str_pad($bar->get_progress(), $bar->get_step_width(), ' ', \STR_PAD_LEFT), 'max' => static fn(self $bar): int => $bar->get_max_steps(), 'percent' => static fn(self $bar): float => floor($bar->get_progress_percent() * 100)];
    }
    private static function init_formats(): array
    {
        return [self::FORMAT_NORMAL => ' %current%/%max% [%bar%] %percent:3s%%', self::FORMAT_NORMAL_NOMAX => ' %current% [%bar%]', self::FORMAT_VERBOSE => ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%', self::FORMAT_VERBOSE_NOMAX => ' %current% [%bar%] %elapsed:6s%', self::FORMAT_VERY_VERBOSE => ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%', self::FORMAT_VERY_VERBOSE_NOMAX => ' %current% [%bar%] %elapsed:6s%', self::FORMAT_DEBUG => ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%', self::FORMAT_DEBUG_NOMAX => ' %current% [%bar%] %elapsed:6s% %memory:6s%'];
    }
    private function build_line(): string
    {
        \assert(null !== $this->format);
        $regex = '{%([a-z\-_]+)(?:\:([^%]+))?%}i';
        $callback = function ($matches) {
            if ($formatter = $this->get_placeholder_formatter($matches[1])) {
                $text = $formatter($this, $this->output);
            } elseif (isset($this->messages[$matches[1]])) {
                $text = $this->messages[$matches[1]];
            } else {
                return $matches[0];
            }
            if (isset($matches[2])) {
                return \sprintf('%' . $matches[2], $text);
            }
            return $text;
        };
        $line = preg_replace_callback($regex, $callback, $this->format);
        // gets string length for each sub line with multiline format
        $lines_length = array_map(fn($sub_line): int => Helper::width(Helper::remove_decoration($this->output->get_formatter(), rtrim((string) $sub_line, "\r"))), explode("\n", (string) $line));
        $lines_width = max($lines_length);
        $terminal_width = $this->terminal->get_width();
        if ($lines_width <= $terminal_width) {
            return $line;
        }
        $this->set_bar_width($this->bar_width - $lines_width + $terminal_width);
        return preg_replace_callback($regex, $callback, (string) $this->format);
    }
}