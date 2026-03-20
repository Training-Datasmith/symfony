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
use Symfony\Component\Console\Formatter\Output_Formatter_Interface;
/**
 * @internal
 */
final readonly class Combined_Output implements Output_Interface
{
    /**
     * @param OutputInterface[] $outputs
     */
    public function __construct(private array $outputs)
    {
        if (!$outputs) {
            throw new LogicException('Expected at least one output.');
        }
    }
    public function write(iterable|string $messages, bool $newline = false, int $options = 0): void
    {
        foreach ($this->outputs as $output) {
            $output->write(...\func_get_args());
        }
    }
    public function writeln(iterable|string $messages, int $options = 0): void
    {
        foreach ($this->outputs as $output) {
            $output->writeln(...\func_get_args());
        }
    }
    public function set_verbosity(int $level): void
    {
        foreach ($this->outputs as $output) {
            $output->set_verbosity($level);
        }
    }
    public function get_verbosity(): int
    {
        return array_first($this->outputs)->get_verbosity();
    }
    public function is_silent(): bool
    {
        return array_first($this->outputs)->is_silent();
    }
    public function is_quiet(): bool
    {
        return array_first($this->outputs)->is_quiet();
    }
    public function is_verbose(): bool
    {
        return array_first($this->outputs)->is_verbose();
    }
    public function is_very_verbose(): bool
    {
        return array_first($this->outputs)->is_very_verbose();
    }
    public function is_debug(): bool
    {
        return array_first($this->outputs)->is_debug();
    }
    public function set_decorated(bool $decorated): void
    {
        foreach ($this->outputs as $output) {
            $output->set_decorated($decorated);
        }
    }
    public function is_decorated(): bool
    {
        return array_first($this->outputs)->is_decorated();
    }
    public function set_formatter(Output_Formatter_Interface $formatter): void
    {
        foreach ($this->outputs as $output) {
            $output->set_formatter($formatter);
        }
    }
    public function get_formatter(): Output_Formatter_Interface
    {
        return array_first($this->outputs)->get_formatter();
    }
}