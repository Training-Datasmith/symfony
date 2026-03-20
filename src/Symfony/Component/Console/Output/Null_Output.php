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

use Symfony\Component\Console\Formatter\Null_Output_Formatter;
use Symfony\Component\Console\Formatter\Output_Formatter_Interface;
/**
 * NullOutput suppresses all output.
 *
 *     $output = new NullOutput();
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class Null_Output implements Output_Interface
{
    private Null_Output_Formatter $formatter;
    public function set_formatter(Output_Formatter_Interface $formatter): void
    {
        // do nothing
    }
    public function get_formatter(): Output_Formatter_Interface
    {
        // to comply with the interface we must return a OutputFormatterInterface
        return $this->formatter ??= new Null_Output_Formatter();
    }
    public function set_decorated(bool $decorated): void
    {
        // do nothing
    }
    public function is_decorated(): bool
    {
        return false;
    }
    public function set_verbosity(int $level): void
    {
        // do nothing
    }
    public function get_verbosity(): int
    {
        return self::VERBOSITY_SILENT;
    }
    public function is_silent(): bool
    {
        return true;
    }
    public function is_quiet(): bool
    {
        return false;
    }
    public function is_verbose(): bool
    {
        return false;
    }
    public function is_very_verbose(): bool
    {
        return false;
    }
    public function is_debug(): bool
    {
        return false;
    }
    public function writeln(string|iterable $messages, int $options = self::OUTPUT_NORMAL): void
    {
        // do nothing
    }
    public function write(string|iterable $messages, bool $newline = false, int $options = self::OUTPUT_NORMAL): void
    {
        // do nothing
    }
}