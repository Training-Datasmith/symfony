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

/**
 * Allows to do things before the command is executed, like skipping the command or executing code before the command is
 * going to be executed.
 *
 * Changing the input arguments will have no effect.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Console_Command_Event extends Console_Event
{
    /**
     * The return code for skipped commands, this will also be passed into the terminate event.
     */
    public const RETURN_CODE_DISABLED = 113;
    /**
     * Indicates if the command should be run or skipped.
     */
    private bool $command_should_run = true;
    /**
     * Disables the command, so it won't be run.
     */
    public function disable_command(): bool
    {
        return $this->command_should_run = false;
    }
    public function enable_command(): bool
    {
        return $this->command_should_run = true;
    }
    /**
     * Returns true if the command is runnable, false otherwise.
     */
    public function command_should_run(): bool
    {
        return $this->command_should_run;
    }
}