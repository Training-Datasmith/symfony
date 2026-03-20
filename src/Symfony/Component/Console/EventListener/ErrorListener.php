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
namespace Symfony\Component\Console\Event_Listener;

use Psr\Log\Logger_Interface;
use Symfony\Component\Console\Console_Events;
use Symfony\Component\Console\Event\Console_Error_Event;
use Symfony\Component\Console\Event\Console_Event;
use Symfony\Component\Console\Event\Console_Terminate_Event;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * @author James Halsall <james.t.halsall@googlemail.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class Error_Listener implements Event_Subscriber_Interface
{
    public function __construct(private readonly ?Logger_Interface $logger = null)
    {
    }
    public function on_console_error(Console_Error_Event $event): void
    {
        if (null === $this->logger) {
            return;
        }
        $error = $event->get_error();
        if (!$input_string = self::get_input_string($event)) {
            $this->logger->critical('An error occurred while using the console. Message: "{message}"', ['exception' => $error, 'message' => $error->get_message()]);
            return;
        }
        $this->logger->critical('Error thrown while running command "{command}". Message: "{message}"', ['exception' => $error, 'command' => $input_string, 'message' => $error->get_message()]);
    }
    public function on_console_terminate(Console_Terminate_Event $event): void
    {
        if (null === $this->logger) {
            return;
        }
        $exit_code = $event->get_exit_code();
        if (0 === $exit_code) {
            return;
        }
        if (!$input_string = self::get_input_string($event)) {
            $this->logger->debug('The console exited with code "{code}"', ['code' => $exit_code]);
            return;
        }
        $this->logger->debug('Command "{command}" exited with code "{code}"', ['command' => $input_string, 'code' => $exit_code]);
    }
    public static function get_subscribed_events(): array
    {
        return [Console_Events::ERROR => ['onConsoleError', -128], Console_Events::TERMINATE => ['onConsoleTerminate', -128]];
    }
    private static function get_input_string(Console_Event $event): string
    {
        $command_name = $event->get_command()?->get_name();
        $input_string = (string) $event->get_input();
        if ($command_name) {
            return str_replace(["'{$command_name}'", "\"{$command_name}\""], $command_name, $input_string);
        }
        return $input_string;
    }
}