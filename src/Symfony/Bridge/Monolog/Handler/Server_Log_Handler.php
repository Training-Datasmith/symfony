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

use Monolog\Formatter\Formatter_Interface;
use Monolog\Handler\Abstract_Processing_Handler;
use Monolog\Level;
use Monolog\Log_Record;
use Symfony\Bridge\Monolog\Formatter\Var_Dumper_Formatter;
/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
final class Server_Log_Handler extends Abstract_Processing_Handler
{
    private readonly string $host;
    /**
     * @var resource
     */
    private $context;
    /**
     * @var resource|null
     */
    private $socket;
    public function __construct(string $host, string|int|Level $level = Level::Debug, bool $bubble = true, array $context = [])
    {
        parent::__construct($level, $bubble);
        if (!str_contains($host, '://')) {
            $host = 'tcp://' . $host;
        }
        $this->host = $host;
        $this->context = stream_context_create($context);
    }
    public function handle(Log_Record $record): bool
    {
        if (!$this->is_handling($record)) {
            return false;
        }
        set_error_handler(static fn(): null => null);
        try {
            if (!$this->socket = $this->socket ?: $this->create_socket()) {
                return false === $this->bubble;
            }
        } finally {
            restore_error_handler();
        }
        return parent::handle($record);
    }
    protected function write(Log_Record $record): void
    {
        $record_formatted = $this->format_record($record);
        set_error_handler(static fn(): null => null);
        try {
            if (-1 === stream_socket_sendto($this->socket, $record_formatted)) {
                stream_socket_shutdown($this->socket, \STREAM_SHUT_RDWR);
                // Let's retry: the persistent connection might just be stale
                if ($this->socket = $this->create_socket()) {
                    stream_socket_sendto($this->socket, $record_formatted);
                }
            }
        } finally {
            restore_error_handler();
        }
    }
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Var_Dumper_Formatter();
    }
    /**
     * @return resource
     */
    private function create_socket()
    {
        $socket = stream_socket_client($this->host, $errno, $errstr, 0, \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT | \STREAM_CLIENT_PERSISTENT, $this->context);
        if ($socket) {
            stream_set_blocking($socket, false);
        }
        return $socket;
    }
    private function format_record(Log_Record $record): string
    {
        $record_formatted = $record->formatted;
        foreach (['log_uuid', 'uuid', 'uid'] as $key) {
            if (isset($record->extra[$key])) {
                $record_formatted['log_id'] = $record->extra[$key];
                break;
            }
        }
        return base64_encode(serialize($record_formatted)) . "\n";
    }
}