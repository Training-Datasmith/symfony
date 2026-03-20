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
namespace Symfony\Bridge\Monolog\Processor;

use Monolog\Log_Record;
use Monolog\Resettable_Interface;
use Symfony\Component\Console\Console_Events;
use Symfony\Component\Console\Event\Console_Event;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Adds the current console command information to the log entry.
 *
 * @author Piotr Stankowski <git@trakos.pl>
 */
final class Console_Command_Processor implements Event_Subscriber_Interface, Reset_Interface, Resettable_Interface
{
    private array $command_data;
    public function __construct(private readonly bool $include_arguments = true, private readonly bool $include_options = false)
    {
    }
    public function __invoke(Log_Record $record): Log_Record
    {
        if (isset($this->command_data) && !isset($record->extra['command'])) {
            $record->extra['command'] = $this->command_data;
        }
        return $record;
    }
    public function reset(): void
    {
        unset($this->command_data);
    }
    public function add_command_data(Console_Event $event): void
    {
        $this->command_data = ['name' => $event->get_command()->get_name()];
        if ($this->include_arguments) {
            $this->command_data['arguments'] = $event->get_input()->get_arguments();
        }
        if ($this->include_options) {
            $this->command_data['options'] = $event->get_input()->get_options();
        }
    }
    public static function get_subscribed_events(): array
    {
        return [Console_Events::COMMAND => ['addCommandData', 1]];
    }
}