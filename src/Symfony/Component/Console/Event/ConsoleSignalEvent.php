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
namespace Symfony\Component\Console\Event;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @author marie <marie@users.noreply.github.com>
 */
final class Console_Signal_Event extends Console_Event
{
    public function __construct(Command $command, Input_Interface $input, Output_Interface $output, private readonly int $handling_signal, private int|false $exit_code = 0)
    {
        parent::__construct($command, $input, $output);
    }
    public function get_handling_signal(): int
    {
        return $this->handling_signal;
    }
    public function set_exit_code(int $exit_code): void
    {
        if ($exit_code < 0 || $exit_code > 255) {
            throw new \InvalidArgumentException('Exit code must be between 0 and 255.');
        }
        $this->exit_code = $exit_code;
    }
    public function abort_exit(): void
    {
        $this->exit_code = false;
    }
    public function get_exit_code(): int|false
    {
        return $this->exit_code;
    }
}