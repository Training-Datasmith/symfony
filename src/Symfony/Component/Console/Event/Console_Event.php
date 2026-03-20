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
use Symfony\Contracts\Event_Dispatcher\Event;
/**
 * Allows to inspect input and output of a command.
 *
 * @author Francesco Levorato <git@flevour.net>
 */
class Console_Event extends Event
{
    public function __construct(protected ?Command $command, private readonly Input_Interface $input, private readonly Output_Interface $output)
    {
    }
    /**
     * Gets the command that is executed.
     */
    public function get_command(): ?Command
    {
        return $this->command;
    }
    /**
     * Gets the input instance.
     */
    public function get_input(): Input_Interface
    {
        return $this->input;
    }
    /**
     * Gets the output instance.
     */
    public function get_output(): Output_Interface
    {
        return $this->output;
    }
}