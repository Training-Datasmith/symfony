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
namespace Symfony\Component\Http_Kernel\Data_Collector;

use Symfony\Component\Error_Handler\Exception\Silenced_Error_Context;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Log\Debug_Logger_Configurator;
use Symfony\Component\Http_Kernel\Log\Debug_Logger_Interface;
use Symfony\Component\Var_Dumper\Cloner\Data;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Logger_Data_Collector extends Data_Collector implements Late_Data_Collector_Interface
{
    private readonly ?Debug_Logger_Interface $logger;
    private ?Request $current_request = null;
    private ?array $processed_logs = null;
    public function __construct(?object $logger = null, private readonly ?string $container_path_prefix = null, private readonly ?Request_Stack $request_stack = null)
    {
        $this->logger = Debug_Logger_Configurator::get_debug_logger($logger);
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->current_request = $this->request_stack && $this->request_stack->get_main_request() !== $request ? $request : null;
    }
    public function late_collect(): void
    {
        if ($this->logger) {
            $container_deprecation_logs = $this->get_container_deprecation_logs();
            $this->data = $this->compute_errors_count($container_deprecation_logs);
            // get compiler logs later (only when they are needed) to improve performance
            $this->data['compiler_logs'] = [];
            $this->data['compiler_logs_filepath'] = $this->container_path_prefix . 'Compiler.log';
            $this->data['logs'] = $this->sanitize_logs(array_merge($this->logger->get_logs($this->current_request), $container_deprecation_logs));
            $this->data = $this->clone_var($this->data);
        }
        $this->current_request = null;
    }
    public function get_logs(): Data|array
    {
        return $this->data['logs'] ?? [];
    }
    public function get_processed_logs(): array
    {
        if (null !== $this->processed_logs) {
            return $this->processed_logs;
        }
        $raw_logs = $this->get_logs();
        if ([] === $raw_logs) {
            return $this->processed_logs = $raw_logs;
        }
        $logs = [];
        foreach ($this->get_logs()->get_value() as $raw_log) {
            $raw_log_data = $raw_log->get_value();
            if ($raw_log_data['priority']->get_value() > 300) {
                $log_type = 'error';
            } elseif (isset($raw_log_data['scream']) && false === $raw_log_data['scream']->get_value()) {
                $log_type = 'deprecation';
            } elseif (isset($raw_log_data['scream']) && true === $raw_log_data['scream']->get_value()) {
                $log_type = 'silenced';
            } else {
                $log_type = 'regular';
            }
            $logs[] = ['type' => $log_type, 'errorCount' => $raw_log['errorCount'] ?? 1, 'timestamp' => $raw_log_data['timestamp_rfc3339']->get_value(), 'priority' => $raw_log_data['priority']->get_value(), 'priorityName' => $raw_log_data['priorityName']->get_value(), 'channel' => $raw_log_data['channel']->get_value(), 'message' => $raw_log_data['message'], 'context' => $raw_log_data['context']];
        }
        // sort logs from oldest to newest
        usort($logs, static fn(array $log_a, array $log_b): int => $log_a['timestamp'] <=> $log_b['timestamp']);
        return $this->processed_logs = $logs;
    }
    public function get_filters(): array
    {
        $filters = ['channel' => [], 'priority' => ['Debug' => 100, 'Info' => 200, 'Notice' => 250, 'Warning' => 300, 'Error' => 400, 'Critical' => 500, 'Alert' => 550, 'Emergency' => 600]];
        $all_channels = [];
        foreach ($this->get_processed_logs() as $log) {
            if ('' === trim($log['channel'] ?? '')) {
                continue;
            }
            $all_channels[] = $log['channel'];
        }
        $channels = array_unique($all_channels);
        sort($channels);
        $filters['channel'] = $channels;
        return $filters;
    }
    public function get_priorities(): Data|array
    {
        return $this->data['priorities'] ?? [];
    }
    public function count_errors(): int
    {
        return $this->data['error_count'] ?? 0;
    }
    public function count_deprecations(): int
    {
        return $this->data['deprecation_count'] ?? 0;
    }
    public function count_warnings(): int
    {
        return $this->data['warning_count'] ?? 0;
    }
    public function count_screams(): int
    {
        return $this->data['scream_count'] ?? 0;
    }
    public function get_compiler_logs(): Data
    {
        return $this->clone_var($this->get_container_compiler_logs($this->data['compiler_logs_filepath'] ?? null));
    }
    public function get_name(): string
    {
        return 'logger';
    }
    private function get_container_deprecation_logs(): array
    {
        if (null === $this->container_path_prefix || !is_file($file = $this->container_path_prefix . 'Deprecations.log')) {
            return [];
        }
        if ('' === $log_content = trim(file_get_contents($file))) {
            return [];
        }
        $boot_time = filemtime($file);
        $logs = [];
        foreach (unserialize($log_content) as $log) {
            $log['context'] = ['exception' => new Silenced_Error_Context($log['type'], $log['file'], $log['line'], $log['trace'], $log['count'])];
            $log['timestamp'] = $boot_time;
            $log['timestamp_rfc3339'] = (new \DateTimeImmutable())->set_timestamp($boot_time)->format(\DateTimeInterface::RFC3339_EXTENDED);
            $log['priority'] = 100;
            $log['priorityName'] = 'DEBUG';
            $log['channel'] = null;
            $log['scream'] = false;
            unset($log['type'], $log['file'], $log['line'], $log['trace'], $log['count']);
            $logs[] = $log;
        }
        return $logs;
    }
    private function get_container_compiler_logs(?string $compiler_logs_filepath = null): array
    {
        if (!$compiler_logs_filepath || !is_file($compiler_logs_filepath)) {
            return [];
        }
        $logs = [];
        foreach (file($compiler_logs_filepath, \FILE_IGNORE_NEW_LINES) as $log) {
            $log = explode(': ', $log, 2);
            if (!isset($log[1]) || !preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)++$/', $log[0])) {
                $log = ['Unknown Compiler Pass', implode(': ', $log)];
            }
            $logs[$log[0]][] = ['message' => $log[1]];
        }
        return $logs;
    }
    private function sanitize_logs(array $logs): array
    {
        $sanitized_logs = [];
        $silenced_logs = [];
        foreach ($logs as $log) {
            if (!$this->is_silenced_or_deprecation_error_log($log)) {
                $sanitized_logs[] = $log;
                continue;
            }
            $message = '_' . $log['message'];
            $exception = $log['context']['exception'];
            if ($exception instanceof Silenced_Error_Context) {
                if (isset($silenced_logs[$id = spl_object_id($exception)])) {
                    continue;
                }
                $silenced_logs[$id] = true;
                if (!isset($sanitized_logs[$message])) {
                    $sanitized_logs[$message] = $log + ['errorCount' => 0, 'scream' => true];
                }
                $sanitized_logs[$message]['errorCount'] += $exception->count;
                continue;
            }
            $error_id = hash('xxh128', "{$exception->get_severity()}/{$exception->get_line()}/{$exception->get_file()}\x00{$message}", true);
            if (isset($sanitized_logs[$error_id])) {
                ++$sanitized_logs[$error_id]['errorCount'];
            } else {
                $log += ['errorCount' => 1, 'scream' => false];
                $sanitized_logs[$error_id] = $log;
            }
        }
        return array_values($sanitized_logs);
    }
    private function is_silenced_or_deprecation_error_log(array $log): bool
    {
        if (!isset($log['context']['exception'])) {
            return false;
        }
        $exception = $log['context']['exception'];
        if ($exception instanceof Silenced_Error_Context) {
            return true;
        }
        if ($exception instanceof \ErrorException && \in_array($exception->get_severity(), [\E_DEPRECATED, \E_USER_DEPRECATED], true)) {
            return true;
        }
        return false;
    }
    private function compute_errors_count(array $container_deprecation_logs): array
    {
        $silenced_logs = [];
        $count = ['error_count' => $this->logger->count_errors($this->current_request), 'deprecation_count' => 0, 'warning_count' => 0, 'scream_count' => 0, 'priorities' => []];
        foreach ($this->logger->get_logs($this->current_request) as $log) {
            if (isset($count['priorities'][$log['priority']])) {
                ++$count['priorities'][$log['priority']]['count'];
            } else {
                $count['priorities'][$log['priority']] = ['count' => 1, 'name' => $log['priorityName']];
            }
            if ('WARNING' === $log['priorityName']) {
                ++$count['warning_count'];
            }
            if ($this->is_silenced_or_deprecation_error_log($log)) {
                $exception = $log['context']['exception'];
                if ($exception instanceof Silenced_Error_Context) {
                    if (isset($silenced_logs[$id = spl_object_id($exception)])) {
                        continue;
                    }
                    $silenced_logs[$id] = true;
                    $count['scream_count'] += $exception->count;
                } else {
                    ++$count['deprecation_count'];
                }
            }
        }
        foreach ($container_deprecation_logs as $deprecation_log) {
            $count['deprecation_count'] += $deprecation_log['context']['exception']->count;
        }
        ksort($count['priorities']);
        return $count;
    }
}