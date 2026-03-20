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
namespace Symfony\Component\Console\Style;

use Symfony\Component\Console\Formatter\Output_Formatter_Interface;
use Symfony\Component\Console\Helper\Progress_Bar;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Decorates output to add console style guide helpers.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
abstract class Output_Style implements Output_Interface, Style_Interface
{
    public function __construct(private readonly Output_Interface $output)
    {
    }
    public function new_line(int $count = 1): void
    {
        $this->output->write(str_repeat(\PHP_EOL, $count));
    }
    public function create_progress_bar(int $max = 0): Progress_Bar
    {
        return new Progress_Bar($this->output, $max);
    }
    public function write(string|iterable $messages, bool $newline = false, int $type = self::OUTPUT_NORMAL): void
    {
        $this->output->write($messages, $newline, $type);
    }
    public function writeln(string|iterable $messages, int $type = self::OUTPUT_NORMAL): void
    {
        $this->output->writeln($messages, $type);
    }
    public function set_verbosity(int $level): void
    {
        $this->output->set_verbosity($level);
    }
    public function get_verbosity(): int
    {
        return $this->output->get_verbosity();
    }
    public function set_decorated(bool $decorated): void
    {
        $this->output->set_decorated($decorated);
    }
    public function is_decorated(): bool
    {
        return $this->output->is_decorated();
    }
    public function set_formatter(Output_Formatter_Interface $formatter): void
    {
        $this->output->set_formatter($formatter);
    }
    public function get_formatter(): Output_Formatter_Interface
    {
        return $this->output->get_formatter();
    }
    public function is_silent(): bool
    {
        return $this->output->is_silent();
    }
    public function is_quiet(): bool
    {
        return $this->output->is_quiet();
    }
    public function is_verbose(): bool
    {
        return $this->output->is_verbose();
    }
    public function is_very_verbose(): bool
    {
        return $this->output->is_very_verbose();
    }
    public function is_debug(): bool
    {
        return $this->output->is_debug();
    }
    protected function get_error_output(): Output_Interface
    {
        if (!$this->output instanceof Console_Output_Interface) {
            return $this->output;
        }
        return $this->output->get_error_output();
    }
}