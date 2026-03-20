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
namespace Symfony\Component\Console\Messenger;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\Run_Command_Failed_Exception;
use Symfony\Component\Console\Input\String_Input;
use Symfony\Component\Console\Output\Buffered_Output;
use Symfony\Component\Messenger\Exception\Recoverable_Exception_Interface;
use Symfony\Component\Messenger\Exception\Unrecoverable_Exception_Interface;
/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final readonly class Run_Command_Message_Handler
{
    public function __construct(private Application $application)
    {
    }
    public function __invoke(Run_Command_Message $message): Run_Command_Context
    {
        $input = new String_Input($message->input);
        $output = new Buffered_Output();
        $this->application->set_catch_exceptions($message->catch_exceptions);
        try {
            $exit_code = $this->application->run($input, $output);
        } catch (Unrecoverable_Exception_Interface|Recoverable_Exception_Interface $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new Run_Command_Failed_Exception($e, new Run_Command_Context($message, Command::FAILURE, $output->fetch()));
        }
        if ($message->throw_on_failure && Command::SUCCESS !== $exit_code) {
            throw new Run_Command_Failed_Exception(\sprintf('Command "%s" exited with code "%s".', $message->input, $exit_code), new Run_Command_Context($message, $exit_code, $output->fetch()));
        }
        return new Run_Command_Context($message, $exit_code, $output->fetch());
    }
}