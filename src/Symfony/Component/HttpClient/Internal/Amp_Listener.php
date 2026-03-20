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
namespace Symfony\Component\Http_Client\Internal;

use Amp\Deferred_Cancellation;
use Amp\Http\Client\Application_Interceptor;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Event_Listener;
use Amp\Http\Client\Network_Interceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Socket\Internet_Address;
use Revolt\Event_Loop;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Amp_Listener implements Event_Listener
{
    private array $info;
    private ?string $connect_timer_id = null;
    /**
     * @param resource|null $handle
     */
    public function __construct(array &$info, private readonly array $pin_sha256, private readonly \Closure $on_progress, private &$handle, private readonly float $max_connect_duration, private readonly Deferred_Cancellation $canceller)
    {
        $info += ['connect_time' => 0.0, 'pretransfer_time' => 0.0, 'starttransfer_time' => 0.0, 'total_time' => 0.0, 'namelookup_time' => 0.0, 'primary_ip' => '', 'primary_port' => 0];
        $this->info =& $info;
    }
    public function request_start(Request $request): void
    {
        $this->info['start_time'] ??= microtime(true);
        if (0 < $this->max_connect_duration) {
            $this->connect_timer_id = Event_Loop::delay($this->max_connect_duration, function (): void {
                $this->canceller->cancel(new Transport_Exception(\sprintf('Max connect duration was reached for "%s".', $this->info['url'])));
            });
        }
        ($this->on_progress)();
    }
    public function connection_acquired(Request $request, Connection $connection, int $stream_count): void
    {
        if (null !== $this->connect_timer_id) {
            Event_Loop::cancel($this->connect_timer_id);
            $this->connect_timer_id = null;
        }
        $this->info['namelookup_time'] = microtime(true) - $this->info['start_time'];
        // see https://github.com/amphp/socket/issues/114
        $this->info['connect_time'] = microtime(true) - $this->info['start_time'];
        ($this->on_progress)();
    }
    public function request_header_start(Request $request, Stream $stream): void
    {
        $host = $stream->get_remote_address()->to_string();
        if ($stream->get_remote_address() instanceof Internet_Address) {
            $host = $stream->get_remote_address()->get_address();
            $this->info['primary_port'] = $stream->get_remote_address()->get_port();
        }
        $this->info['primary_ip'] = $host;
        if (str_contains((string) $host, ':')) {
            $host = '[' . $host . ']';
        }
        $this->info['pretransfer_time'] = microtime(true) - $this->info['start_time'];
        $this->info['debug'] .= \sprintf("* Connected to %s (%s) port %d\n", $request->get_uri()->get_host(), $host, $this->info['primary_port']);
        if ((isset($this->info['peer_certificate_chain']) || $this->pin_sha256) && null !== $tls_info = $stream->get_tls_info()) {
            foreach ($tls_info->get_peer_certificates() as $cert) {
                $this->info['peer_certificate_chain'][] = openssl_x509_read($cert->to_pem());
            }
            if ($this->pin_sha256) {
                $pin = openssl_pkey_get_public($this->info['peer_certificate_chain'][0]);
                $pin = openssl_pkey_get_details($pin)['key'];
                $pin = \array_slice(explode("\n", (string) $pin), 1, -2);
                $pin = base64_decode(implode('', $pin));
                $pin = base64_encode(hash('sha256', $pin, true));
                if (!\in_array($pin, $this->pin_sha256, true)) {
                    throw new Transport_Exception(\sprintf('SSL public key does not match pinned public key for "%s".', $this->info['url']));
                }
            }
        }
        ($this->on_progress)();
        $uri = $request->get_uri();
        $request_uri = $uri->get_path() ?: '/';
        if ('' !== $query = $uri->get_query()) {
            $request_uri .= '?' . $query;
        }
        if ('CONNECT' === $method = $request->get_method()) {
            $request_uri = $uri->get_host() . ': ' . ($uri->get_port() ?? ('https' === $uri->get_scheme() ? 443 : 80));
        }
        $this->info['debug'] .= \sprintf("> %s %s HTTP/%s \r\n", $method, $request_uri, $request->get_protocol_versions()[0]);
        foreach ($request->get_header_pairs() as [$name, $value]) {
            $this->info['debug'] .= $name . ': ' . $value . "\r\n";
        }
        $this->info['debug'] .= "\r\n";
    }
    public function request_body_end(Request $request, Stream $stream): void
    {
        ($this->on_progress)();
    }
    public function response_header_start(Request $request, Stream $stream): void
    {
        ($this->on_progress)();
    }
    public function request_end(Request $request, Response $response): void
    {
        ($this->on_progress)();
    }
    public function request_failed(Request $request, \Throwable $exception): void
    {
        if (null !== $this->connect_timer_id) {
            Event_Loop::cancel($this->connect_timer_id);
            $this->connect_timer_id = null;
        }
        $this->handle = null;
        ($this->on_progress)();
    }
    public function request_header_end(Request $request, Stream $stream): void
    {
        ($this->on_progress)();
    }
    public function request_body_start(Request $request, Stream $stream): void
    {
        ($this->on_progress)();
    }
    public function request_body_progress(Request $request, Stream $stream): void
    {
        ($this->on_progress)();
    }
    public function response_header_end(Request $request, Stream $stream, Response $response): void
    {
        ($this->on_progress)();
    }
    public function response_body_start(Request $request, Stream $stream, Response $response): void
    {
        $this->info['starttransfer_time'] = microtime(true) - $this->info['start_time'];
        ($this->on_progress)();
    }
    public function response_body_progress(Request $request, Stream $stream, Response $response): void
    {
        ($this->on_progress)();
    }
    public function response_body_end(Request $request, Stream $stream, Response $response): void
    {
        $this->handle = null;
        ($this->on_progress)();
    }
    public function application_interceptor_start(Request $request, Application_Interceptor $interceptor): void
    {
    }
    public function application_interceptor_end(Request $request, Application_Interceptor $interceptor, Response $response): void
    {
    }
    public function network_interceptor_start(Request $request, Network_Interceptor $interceptor): void
    {
    }
    public function network_interceptor_end(Request $request, Network_Interceptor $interceptor, Response $response): void
    {
    }
    public function push(Request $request): void
    {
        ($this->on_progress)();
    }
    public function request_rejected(Request $request): void
    {
        $this->handle = null;
        ($this->on_progress)();
    }
}