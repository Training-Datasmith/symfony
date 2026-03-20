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
namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Handler\Abstract_Handler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Log_Record;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notifier_Interface;
/**
 * Uses Notifier as a log handler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Notifier_Handler extends Abstract_Handler
{
    public function __construct(private readonly Notifier_Interface $notifier, string|int|Level $level = Level::Error, bool $bubble = true)
    {
        parent::__construct(Logger::to_monolog_level($level)->is_lower_than(Level::Error) ? Level::Error : $level, $bubble);
    }
    public function handle(Log_Record $record): bool
    {
        if (!$this->is_handling($record)) {
            return false;
        }
        $this->notify([$record]);
        return !$this->bubble;
    }
    public function handle_batch(array $records): void
    {
        if ($records = array_filter($records, $this->is_handling(...))) {
            $this->notify($records);
        }
    }
    private function notify(array $records): void
    {
        $record = $this->get_highest_record($records);
        if (($record->context['exception'] ?? null) instanceof \Throwable) {
            $notification = Notification::from_throwable($record->context['exception']);
        } else {
            $notification = new Notification($record->message);
        }
        $notification->importance_from_log_level_name($record->level->get_name());
        $this->notifier->send($notification, ...$this->notifier->get_admin_recipients());
    }
    private function get_highest_record(array $records): array|Log_Record
    {
        $highest_record = null;
        foreach ($records as $record) {
            if (null === $highest_record || $highest_record->level->is_lower_than($record->level)) {
                $highest_record = $record;
            }
        }
        return $highest_record;
    }
}