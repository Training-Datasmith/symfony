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

use Amp\Byte_Stream\Resource_Stream;
use Amp\Cancellation;
use Amp\Deferred_Cancellation;
use Amp\Deferred_Future;
use Amp\Future;
use Amp\Http\Client\Connection\Connection_Limiting_Pool;
use Amp\Http\Client\Connection\Default_Connection_Factory;
use Amp\Http\Client\Intercepted_Http_Client;
use Amp\Http\Client\Interceptor\Retry_Requests;
use Amp\Http\Client\Pooled_Http_Client;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Tunnel\Http1tunnel_Connector;
use Amp\Http\Tunnel\Https1tunnel_Connector;
use Amp\Socket\Certificate;
use Amp\Socket\Client_Tls_Context;
use Amp\Socket\Connect_Context;
use Amp\Socket\Dns_Socket_Connector;
use Amp\Socket\Internet_Address;
use Amp\Socket\Socket;
use Amp\Socket\Socket_Address;
use Amp\Socket\Socket_Connector;
use Psr\Log\Logger_Interface;
/**
 * Internal representation of the Amp client's state.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class Amp_Client_State extends Client_State
{
    public array $dns_cache = [];
    public int $response_count = 0;
    public array $pushed_responses = [];
    private array $clients = [];
    private readonly \Closure $client_configurator;
    public function __construct(?callable $client_configurator, private readonly int $max_host_connections, private readonly int $max_pending_pushes, private ?Logger_Interface &$logger)
    {
        $client_configurator ??= static fn(Pooled_Http_Client $client): \Amp\Http\Client\Intercepted_Http_Client => new Intercepted_Http_Client($client, new Retry_Requests(2), []);
        $this->client_configurator = $client_configurator(...);
    }
    public function request(array $options, Request $request, Deferred_Cancellation $canceller, array &$info, \Closure $on_progress, &$handle): Response
    {
        if ($options['proxy']) {
            if ($request->has_header('proxy-authorization')) {
                $options['proxy']['auth'] = $request->get_header('proxy-authorization');
            }
            // Matching "no_proxy" should follow the behavior of curl
            $host = $request->get_uri()->get_host();
            foreach ($options['proxy']['no_proxy'] as $rule) {
                $dot_rule = '.' . ltrim((string) $rule, '.');
                if ('*' === $rule || $host === $rule || str_ends_with((string) $host, $dot_rule)) {
                    $options['proxy'] = null;
                    break;
                }
            }
        }
        if ($request->has_header('proxy-authorization')) {
            $request->remove_header('proxy-authorization');
        }
        if ($options['capture_peer_cert_chain']) {
            $info['peer_certificate_chain'] = [];
        }
        $request->add_event_listener(new Amp_Listener($info, $options['peer_fingerprint']['pin-sha256'] ?? [], $on_progress, $handle, $options['max_connect_duration'], $canceller));
        $request->set_push_handler(fn(\Amp\Http\Client\Request $request, \Amp\Future $response) => $this->handle_push($request, $response, $options));
        if (0 <= $body_size = $request->has_header('content-length') ? (int) $request->get_header('content-length') : $request->get_body()->get_content_length() ?? -1) {
            $info['upload_content_length'] = (1 + $info['upload_content_length'] ?? 1) - 1 + $body_size;
        }
        [$client, $connector] = $this->get_client($options);
        $response = $client->request($request, $canceller->get_cancellation());
        $handle = $connector->handle;
        return $response;
    }
    private function get_client(array $options): array
    {
        $options = ['bindto' => $options['bindto'] ?: '0', 'verify_peer' => $options['verify_peer'], 'capath' => $options['capath'], 'cafile' => $options['cafile'], 'local_cert' => $options['local_cert'], 'local_pk' => $options['local_pk'], 'ciphers' => $options['ciphers'], 'capture_peer_cert_chain' => $options['capture_peer_cert_chain'] || $options['peer_fingerprint'], 'proxy' => $options['proxy'], 'crypto_method' => $options['crypto_method']];
        $key = hash('xxh128', serialize($options));
        if (isset($this->clients[$key])) {
            return $this->clients[$key];
        }
        $context = new Client_Tls_Context('');
        $options['verify_peer'] || $context = $context->without_peer_verification();
        $options['cafile'] && $context = $context->with_ca_file($options['cafile']);
        $options['capath'] && $context = $context->with_ca_path($options['capath']);
        $options['local_cert'] && $context = $context->with_certificate(new Certificate($options['local_cert'], $options['local_pk']));
        $options['ciphers'] && $context = $context->with_ciphers($options['ciphers']);
        $options['capture_peer_cert_chain'] && $context = $context->with_peer_capturing();
        $options['crypto_method'] && $context = $context->with_minimum_version($options['crypto_method']);
        $connector = $handle_connector = new class implements Socket_Connector
        {
            public Dns_Socket_Connector $connector;
            public string $uri;
            /** @var resource|null */
            public $handle;
            public function connect(Socket_Address|string $uri, ?Connect_Context $context = null, ?Cancellation $cancellation = null): Socket
            {
                $socket = $this->connector->connect($this->uri ?? $uri, $context, $cancellation);
                $this->handle = $socket instanceof Resource_Stream ? $socket->get_resource() : false;
                return $socket;
            }
        };
        $connector->connector = new Dns_Socket_Connector(new Amp_Resolver($this->dns_cache));
        $context = (new Connect_Context())->with_tcp_no_delay()->with_tls_context($context);
        if ($options['bindto']) {
            if (file_exists($options['bindto'])) {
                $connector->uri = 'unix://' . $options['bindto'];
            } else {
                $context = $context->with_bind_to($options['bindto']);
            }
        }
        if ($options['proxy']) {
            $proxy_url = parse_url((string) $options['proxy']['url']);
            $proxy_socket = new Internet_Address($proxy_url['host'], $proxy_url['port']);
            $proxy_headers = $options['proxy']['auth'] ? ['Proxy-Authorization' => $options['proxy']['auth']] : [];
            if ('ssl' === $proxy_url['scheme']) {
                $connector = new Https1tunnel_Connector($proxy_socket, $context->get_tls_context(), $proxy_headers, $connector);
            } else {
                $connector = new Http1tunnel_Connector($proxy_socket, $proxy_headers, $connector);
            }
        }
        $max_host_connections = 0 < $this->max_host_connections ? $this->max_host_connections : \PHP_INT_MAX;
        $pool = new Default_Connection_Factory($connector, $context);
        $pool = Connection_Limiting_Pool::by_authority($max_host_connections, $pool);
        return $this->clients[$key] = [($this->client_configurator)(new Pooled_Http_Client($pool)), $handle_connector];
    }
    private function handle_push(Request $request, Future $response, array $options): void
    {
        $deferred = new Deferred_Future();
        $authority = $request->get_uri()->get_authority();
        if ($this->max_pending_pushes <= \count($this->pushed_responses[$authority] ?? [])) {
            $fifo_url = key($this->pushed_responses[$authority]);
            unset($this->pushed_responses[$authority][$fifo_url]);
            $this->logger?->debug(\sprintf('Evicting oldest pushed response: "%s"', $fifo_url));
        }
        $url = (string) $request->get_uri();
        $this->logger?->debug(\sprintf('Queueing pushed response: "%s"', $url));
        $this->pushed_responses[$authority][] = [$url, $deferred, $request, $response, ['proxy' => $options['proxy'], 'bindto' => $options['bindto'], 'local_cert' => $options['local_cert'], 'local_pk' => $options['local_pk']]];
        $deferred->get_future()->await();
    }
}