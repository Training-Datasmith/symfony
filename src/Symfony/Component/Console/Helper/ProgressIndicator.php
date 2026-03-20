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

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Output\Console_Section_Output;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class Progress_Indicator
{
    private const FORMATS = ['normal' => ' %indicator% %message%', 'normal_no_ansi' => ' %message%', 'verbose' => ' %indicator% %message% (%elapsed:6s%)', 'verbose_no_ansi' => ' %message% (%elapsed:6s%)', 'very_verbose' => ' %indicator% %message% (%elapsed:6s%, %memory:6s%)', 'very_verbose_no_ansi' => ' %message% (%elapsed:6s%, %memory:6s%)'];
    private int $start_time;
    private ?string $format = null;
    private ?string $message = null;
    private array $indicator_values;
    private int $indicator_current;
    private string $finished_indicator_value;
    private float $indicator_update_time;
    private bool $started = false;
    private bool $finished = false;
    /**
     * @var array<string, callable>
     */
    private static array $formatters;
    /**
     * @param int        $indicatorChangeInterval Change interval in milliseconds
     * @param array|null $indicatorValues         Animated indicator characters
     */
    public function __construct(private readonly Output_Interface $output, ?string $format = null, private readonly int $indicator_change_interval = 100, ?array $indicator_values = null, ?string $finished_indicator_value = null)
    {
        $format ??= $this->determine_best_format();
        $indicator_values ??= ['-', '\\', '|', '/'];
        $indicator_values = array_values($indicator_values);
        $finished_indicator_value ??= '✔';
        if (2 > \count($indicator_values)) {
            throw new InvalidArgumentException('Must have at least 2 indicator value characters.');
        }
        $this->format = self::get_format_definition($format);
        $this->indicator_values = $indicator_values;
        $this->finished_indicator_value = $finished_indicator_value;
        $this->start_time = time();
    }
    /**
     * Sets the current indicator message.
     */
    public function set_message(?string $message): void
    {
        $this->message = $message;
        $this->display();
    }
    /**
     * Starts the indicator output.
     */
    public function start(string $message): void
    {
        if ($this->started) {
            throw new LogicException('Progress indicator already started.');
        }
        $this->message = $message;
        $this->started = true;
        $this->finished = false;
        $this->start_time = time();
        $this->indicator_update_time = $this->get_current_time_in_milliseconds() + $this->indicator_change_interval;
        $this->indicator_current = 0;
        $this->display();
    }
    /**
     * Advances the indicator.
     */
    public function advance(): void
    {
        if (!$this->started) {
            throw new LogicException('Progress indicator has not yet been started.');
        }
        if (!$this->output->is_decorated()) {
            return;
        }
        $current_time = $this->get_current_time_in_milliseconds();
        if ($current_time < $this->indicator_update_time) {
            return;
        }
        $this->indicator_update_time = $current_time + $this->indicator_change_interval;
        ++$this->indicator_current;
        $this->display();
    }
    /**
     * Finish the indicator with message.
     */
    public function finish(string $message, ?string $finished_indicator = null): void
    {
        if (!$this->started) {
            throw new LogicException('Progress indicator has not yet been started.');
        }
        if (null !== $finished_indicator) {
            $this->finished_indicator_value = $finished_indicator;
        }
        $this->finished = true;
        $this->message = $message;
        $this->display();
        if (!$this->output instanceof Console_Section_Output) {
            $this->output->writeln('');
        }
        $this->started = false;
    }
    /**
     * Gets the format for a given name.
     */
    public static function get_format_definition(string $name): ?string
    {
        return self::FORMATS[$name] ?? null;
    }
    /**
     * Sets a placeholder formatter for a given name.
     *
     * This method also allow you to override an existing placeholder.
     */
    public static function set_placeholder_formatter_definition(string $name, callable $callable): void
    {
        self::$formatters ??= self::init_placeholder_formatters();
        self::$formatters[$name] = $callable;
    }
    /**
     * Gets the placeholder formatter for a given name (including the delimiter char like %).
     */
    public static function get_placeholder_formatter_definition(string $name): ?callable
    {
        self::$formatters ??= self::init_placeholder_formatters();
        return self::$formatters[$name] ?? null;
    }
    private function display(): void
    {
        if (Output_Interface::VERBOSITY_QUIET === $this->output->get_verbosity()) {
            return;
        }
        $this->overwrite(preg_replace_callback('{%([a-z\-_]+)(?:\:([^%]+))?%}i', function (array $matches) {
            if ($formatter = self::get_placeholder_formatter_definition($matches[1])) {
                return $formatter($this);
            }
            return $matches[0];
        }, $this->format ?? ''));
    }
    private function determine_best_format(): string
    {
        return match ($this->output->get_verbosity()) {
            // OutputInterface::VERBOSITY_QUIET: display is disabled anyway
            Output_Interface::VERBOSITY_VERBOSE => $this->output->is_decorated() ? 'verbose' : 'verbose_no_ansi',
            Output_Interface::VERBOSITY_VERY_VERBOSE, Output_Interface::VERBOSITY_DEBUG => $this->output->is_decorated() ? 'very_verbose' : 'very_verbose_no_ansi',
            default => $this->output->is_decorated() ? 'normal' : 'normal_no_ansi',
        };
    }
    /**
     * Overwrites a previous message to the output.
     */
    private function overwrite(string $message): void
    {
        if ($this->output instanceof Console_Section_Output) {
            $this->output->overwrite($message);
        } elseif ($this->output->is_decorated()) {
            $this->output->write("\r\x1b[2K");
            $this->output->write($message);
        } else {
            $this->output->writeln($message);
        }
    }
    private function get_current_time_in_milliseconds(): float
    {
        return round(microtime(true) * 1000);
    }
    /**
     * @return array<string, \Closure>
     */
    private static function init_placeholder_formatters(): array
    {
        return ['indicator' => static fn(self $indicator) => $indicator->finished ? $indicator->finished_indicator_value : $indicator->indicator_values[$indicator->indicator_current % \count($indicator->indicator_values)], 'message' => static fn(self $indicator): ?string => $indicator->message, 'elapsed' => static fn(self $indicator): string => Helper::format_time(time() - $indicator->start_time, 2), 'memory' => static fn(): string => Helper::format_memory(memory_get_usage(true))];
    }
}