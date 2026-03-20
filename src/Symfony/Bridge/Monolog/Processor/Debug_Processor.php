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

use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Resettable_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Kernel\Log\Debug_Logger_Interface;
use Symfony\Contracts\Service\Reset_Interface;
class Debug_Processor implements Debug_Logger_Interface, Reset_Interface, Resettable_Interface
{
    private array $records = [];
    private array $error_count = [];
    public function __construct(private readonly ?Request_Stack $request_stack = null)
    {
    }
    public function __invoke(Log_Record $record): Log_Record
    {
        $key = $this->request_stack && ($request = $this->request_stack->get_current_request()) ? spl_object_id($request) : '';
        $this->records[$key][] = ['timestamp' => $record->datetime->get_timestamp(), 'timestamp_rfc3339' => $record->datetime->format(\DateTimeInterface::RFC3339_EXTENDED), 'message' => $record->message, 'priority' => $record->level->value, 'priorityName' => $record->level->get_name(), 'context' => $record->context, 'channel' => $record->channel ?? ''];
        if (!isset($this->error_count[$key])) {
            $this->error_count[$key] = 0;
        }
        if ($record->level->is_higher_than(Level::Warning)) {
            ++$this->error_count[$key];
        }
        return $record;
    }
    public function get_logs(?Request $request = null): array
    {
        if (null !== $request) {
            return $this->records[spl_object_id($request)] ?? [];
        }
        if (0 === \count($this->records)) {
            return [];
        }
        return array_merge(...array_values($this->records));
    }
    public function count_errors(?Request $request = null): int
    {
        if (null !== $request) {
            return $this->error_count[spl_object_id($request)] ?? 0;
        }
        return array_sum($this->error_count);
    }
    public function clear(): void
    {
        $this->records = [];
        $this->error_count = [];
    }
    public function reset(): void
    {
        $this->clear();
    }
}