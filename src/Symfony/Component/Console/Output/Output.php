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

use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Formatter\Output_Formatter_Interface;
/**
 * Base class for output classes.
 *
 * There are six levels of verbosity:
 *
 *  * normal: no option passed (normal output)
 *  * verbose: -v (more output)
 *  * very verbose: -vv (highly extended output)
 *  * debug: -vvv (all debug output)
 *  * quiet: -q (only output errors)
 *  * silent: --silent (no output)
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Output implements Output_Interface
{
    private int $verbosity;
    private Output_Formatter_Interface $formatter;
    /**
     * @param int|null                      $verbosity The verbosity level (one of the VERBOSITY constants in OutputInterface)
     * @param bool                          $decorated Whether to decorate messages
     * @param OutputFormatterInterface|null $formatter Output formatter instance (null to use default OutputFormatter)
     */
    public function __construct(?int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = false, ?Output_Formatter_Interface $formatter = null)
    {
        $this->verbosity = $verbosity ?? self::VERBOSITY_NORMAL;
        $this->formatter = $formatter ?? new Output_Formatter();
        $this->formatter->set_decorated($decorated);
    }
    public function set_formatter(Output_Formatter_Interface $formatter): void
    {
        $this->formatter = $formatter;
    }
    public function get_formatter(): Output_Formatter_Interface
    {
        return $this->formatter;
    }
    public function set_decorated(bool $decorated): void
    {
        $this->formatter->set_decorated($decorated);
    }
    public function is_decorated(): bool
    {
        return $this->formatter->is_decorated();
    }
    public function set_verbosity(int $level): void
    {
        $this->verbosity = $level;
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
    public function writeln(string|iterable $messages, int $options = self::OUTPUT_NORMAL): void
    {
        $this->write($messages, true, $options);
    }
    public function write(string|iterable $messages, bool $newline = false, int $options = self::OUTPUT_NORMAL): void
    {
        if (!is_iterable($messages)) {
            $messages = [$messages];
        }
        $types = self::OUTPUT_NORMAL | self::OUTPUT_RAW | self::OUTPUT_PLAIN;
        $type = $types & $options ?: self::OUTPUT_NORMAL;
        $verbosities = self::VERBOSITY_QUIET | self::VERBOSITY_NORMAL | self::VERBOSITY_VERBOSE | self::VERBOSITY_VERY_VERBOSE | self::VERBOSITY_DEBUG;
        $verbosity = $verbosities & $options ?: self::VERBOSITY_NORMAL;
        if ($verbosity > $this->get_verbosity()) {
            return;
        }
        foreach ($messages as $message) {
            switch ($type) {
                case Output_Interface::OUTPUT_NORMAL:
                    $message = $this->formatter->format($message);
                    break;
                case Output_Interface::OUTPUT_RAW:
                    break;
                case Output_Interface::OUTPUT_PLAIN:
                    $message = strip_tags((string) $this->formatter->format($message));
                    break;
            }
            $this->do_write($message ?? '', $newline);
        }
    }
    /**
     * Writes a message to the output.
     */
    abstract protected function do_write(string $message, bool $newline): void;
}