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
 * OutputInterface is the interface implemented by all Output classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Output_Interface
{
    public const VERBOSITY_SILENT = 8;
    public const VERBOSITY_QUIET = 16;
    public const VERBOSITY_NORMAL = 32;
    public const VERBOSITY_VERBOSE = 64;
    public const VERBOSITY_VERY_VERBOSE = 128;
    public const VERBOSITY_DEBUG = 256;
    public const OUTPUT_NORMAL = 1;
    public const OUTPUT_RAW = 2;
    public const OUTPUT_PLAIN = 4;
    /**
     * Writes a message to the output.
     *
     * @param bool $newline Whether to add a newline
     * @param int  $options A bitmask of options (one of the OUTPUT or VERBOSITY constants),
     *                      0 is considered the same as self::OUTPUT_NORMAL | self::VERBOSITY_NORMAL
     */
    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void;
    /**
     * Writes a message to the output and adds a newline at the end.
     *
     * @param int $options A bitmask of options (one of the OUTPUT or VERBOSITY constants),
     *                     0 is considered the same as self::OUTPUT_NORMAL | self::VERBOSITY_NORMAL
     */
    public function writeln(string|iterable $messages, int $options = 0): void;
    /**
     * Sets the verbosity of the output.
     *
     * @param self::VERBOSITY_* $level
     */
    public function set_verbosity(int $level): void;
    /**
     * Gets the current verbosity of the output.
     *
     * @return self::VERBOSITY_*
     */
    public function get_verbosity(): int;
    public function is_silent(): bool;
    /**
     * Returns whether verbosity is quiet (-q).
     */
    public function is_quiet(): bool;
    /**
     * Returns whether verbosity is verbose (-v).
     */
    public function is_verbose(): bool;
    /**
     * Returns whether verbosity is very verbose (-vv).
     */
    public function is_very_verbose(): bool;
    /**
     * Returns whether verbosity is debug (-vvv).
     */
    public function is_debug(): bool;
    /**
     * Sets the decorated flag.
     */
    public function set_decorated(bool $decorated): void;
    /**
     * Gets the decorated flag.
     */
    public function is_decorated(): bool;
    public function set_formatter(Output_Formatter_Interface $formatter): void;
    /**
     * Returns current output formatter instance.
     */
    public function get_formatter(): Output_Formatter_Interface;
}