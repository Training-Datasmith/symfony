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
 * Allows to manipulate the exit code of a command after its execution.
 *
 * @author Francesco Levorato <git@flevour.net>
 * @author Jules Pietri <jules@heahprod.com>
 */
final class Console_Terminate_Event extends Console_Event
{
    public function __construct(Command $command, Input_Interface $input, Output_Interface $output, private int $exit_code, private readonly ?int $interrupting_signal = null)
    {
        parent::__construct($command, $input, $output);
    }
    public function set_exit_code(int $exit_code): void
    {
        $this->exit_code = $exit_code;
    }
    public function get_exit_code(): int
    {
        return $this->exit_code;
    }
    public function get_interrupting_signal(): ?int
    {
        return $this->interrupting_signal;
    }
}