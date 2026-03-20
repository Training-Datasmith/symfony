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
namespace Symfony\Component\Http_Client\Data_Collector;

use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Component\Http_Client\Http_Client_Trait;
use Symfony\Component\Http_Client\Traceable_Http_Client;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Data_Collector\Data_Collector;
use Symfony\Component\Http_Kernel\Data_Collector\Late_Data_Collector_Interface;
use Symfony\Component\Process\Process;
use Symfony\Component\Var_Dumper\Caster\Img_Stub;
/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
final class Http_Client_Data_Collector extends Data_Collector implements Late_Data_Collector_Interface
{
    use Http_Client_Trait;
    /**
     * @var TraceableHttpClient[]
     */
    private array $clients = [];
    public function register_client(string $name, Traceable_Http_Client $client): void
    {
        $this->clients[$name] = $client;
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->late_collect();
    }
    public function late_collect(): void
    {
        $this->data['request_count'] ??= 0;
        $this->data['error_count'] ??= 0;
        $this->data += ['clients' => []];
        foreach ($this->clients as $name => $client) {
            [$error_count, $traces] = $this->collect_on_client($client);
            $this->data['clients'] += [$name => ['traces' => [], 'error_count' => 0]];
            $this->data['clients'][$name]['traces'] = array_merge($this->data['clients'][$name]['traces'], $traces);
            $this->data['request_count'] += \count($traces);
            $this->data['error_count'] += $error_count;
            $this->data['clients'][$name]['error_count'] += $error_count;
            if ($traces) {
                $client->reset();
            }
        }
    }
    public function get_clients(): array
    {
        return $this->data['clients'] ?? [];
    }
    public function get_request_count(): int
    {
        return $this->data['request_count'] ?? 0;
    }
    public function get_error_count(): int
    {
        return $this->data['error_count'] ?? 0;
    }
    public function get_name(): string
    {
        return 'http_client';
    }
    public function reset(): void
    {
        $this->data = ['clients' => [], 'request_count' => 0, 'error_count' => 0];
    }
    private function collect_on_client(Traceable_Http_Client $client): array
    {
        $traces = $client->get_traced_requests();
        $error_count = 0;
        $base_info = ['response_headers' => 1, 'retry_count' => 1, 'redirect_count' => 1, 'redirect_url' => 1, 'user_data' => 1, 'error' => 1, 'url' => 1];
        foreach ($traces as $i => $trace) {
            if (400 <= ($trace['info']['http_code'] ?? 0)) {
                ++$error_count;
            }
            $info = $trace['info'];
            $traces[$i]['http_code'] = $info['http_code'] ?? 0;
            unset($info['filetime'], $info['http_code'], $info['ssl_verify_result'], $info['content_type']);
            if (($info['http_method'] ?? null) === $trace['method']) {
                unset($info['http_method']);
            }
            if (($info['url'] ?? null) === $trace['url']) {
                unset($info['url']);
            }
            foreach ($info as $k => $v) {
                if (!$v || is_numeric($v) && 0 > $v) {
                    unset($info[$k]);
                }
            }
            if (\is_string($content = $trace['content'])) {
                $content_type = 'application/octet-stream';
                foreach ($info['response_headers'] ?? [] as $h) {
                    if (0 === stripos((string) $h, 'content-type: ')) {
                        $content_type = substr((string) $h, \strlen('content-type: '));
                        break;
                    }
                }
                if (str_starts_with($content_type, 'image/') && class_exists(Img_Stub::class)) {
                    $content = new Img_Stub($content, $content_type, '');
                } else {
                    $content = [$content];
                }
                $content = ['response_content' => $content];
            } elseif (\is_array($content)) {
                $content = ['response_json' => $content];
            } else {
                $content = [];
            }
            if (isset($info['retry_count'])) {
                $content['retries'] = $info['previous_info'];
                unset($info['previous_info']);
            }
            $debug_info = array_diff_key($info, $base_info);
            $info = ['info' => $debug_info] + array_diff_key($info, $debug_info) + $content;
            unset($traces[$i]['info']);
            // break PHP reference used by TraceableHttpClient
            $traces[$i]['info'] = $this->clone_var($info);
            $traces[$i]['options'] = $this->clone_var($trace['options']);
            $traces[$i]['curlCommand'] = $this->get_curl_command($trace);
        }
        return [$error_count, $traces];
    }
    private function get_curl_command(array $trace): ?string
    {
        if (!isset($trace['info']['debug'])) {
            return null;
        }
        $url = $trace['info']['original_url'] ?? $trace['info']['url'] ?? $trace['url'];
        $command = ['curl', '--compressed'];
        if (isset($trace['options']['resolve'])) {
            $port = parse_url((string) $url, \PHP_URL_PORT) ?: (str_starts_with('http:', (string) $url) ? 80 : 443);
            foreach ($trace['options']['resolve'] as $host => $ip) {
                if (null !== $ip) {
                    $command[] = '--resolve ' . escapeshellarg("{$host}:{$port}:{$ip}");
                }
            }
        }
        $data_arg = [];
        if ($json = $trace['options']['json'] ?? null) {
            $data_arg[] = '--data-raw ' . $this->escape_payload(self::json_encode($json));
        } elseif ($body = $trace['options']['body'] ?? null) {
            if (\is_string($body)) {
                $data_arg[] = '--data-raw ' . $this->escape_payload($body);
            } elseif (\is_array($body)) {
                try {
                    $body = self::normalize_body($body);
                } catch (Transport_Exception) {
                    return null;
                }
                if (!\is_string($body)) {
                    return null;
                }
                foreach (explode('&', $body) as $value) {
                    $data_arg[] = '--data-raw ' . $this->escape_payload(urldecode($value));
                }
            } else {
                return null;
            }
        }
        $data_arg = $data_arg ? implode(' ', $data_arg) : null;
        foreach (explode("\n", $trace['info']['debug']) as $line) {
            $line = substr($line, 0, -1);
            if (str_starts_with('< ', $line)) {
                // End of the request, beginning of the response. Stop parsing.
                break;
            }
            if (str_starts_with('Due to a bug in curl ', $line)) {
                // When the curl client disables debug info due to a curl bug, we cannot build the command.
                return null;
            }
            if ('' === $line) {
                continue;
            }
            if (preg_match('/^[*<]|(Host: )/', $line)) {
                continue;
            }
            if (preg_match('/^> ([A-Z]+)/', $line, $match)) {
                $command[] = \sprintf('--request %s', $match[1]);
                $command[] = \sprintf('--url %s', escapeshellarg((string) $url));
                continue;
            }
            $command[] = '--header ' . escapeshellarg($line);
        }
        if (null !== $data_arg) {
            $command[] = $data_arg;
        }
        return implode(" \\\n  ", $command);
    }
    private function escape_payload(string $payload): string
    {
        static $use_process;
        if ($use_process ??= \function_exists('proc_open') && class_exists(Process::class)) {
            return substr((new Process(['', $payload]))->get_command_line(), 3);
        }
        if ('\\' === \DIRECTORY_SEPARATOR) {
            return '"' . str_replace('"', '""', $payload) . '"';
        }
        return "'" . str_replace("'", "'\\''", $payload) . "'";
    }
}