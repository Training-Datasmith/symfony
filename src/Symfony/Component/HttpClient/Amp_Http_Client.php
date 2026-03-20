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
namespace Symfony\Component\Http_Client;

use Amp\Cancelled_Exception;
use Amp\Http\Client\Delegate_Http_Client;
use Amp\Http\Client\Intercepted_Http_Client;
use Amp\Http\Client\Pooled_Http_Client;
use Amp\Http\Client\Request;
use Amp\Http\Tunnel\Http1tunnel_Connector;
use Psr\Log\Logger_Aware_Interface;
use Psr\Log\Logger_Aware_Trait;
use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Component\Http_Client\Internal\Amp_Client_State;
use Symfony\Component\Http_Client\Response\Amp_Response;
use Symfony\Component\Http_Client\Response\Response_Stream;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Http_Client\Response_Stream_Interface;
use Symfony\Contracts\Service\Reset_Interface;
if (!interface_exists(Delegate_Http_Client::class)) {
    throw new \LogicException('You cannot use "Symfony\Component\HttpClient\AmpHttpClient" as the "amphp/http-client" package is not installed. Try running "composer require amphp/http-client:^5".');
}
/**
 * A portable implementation of the HttpClientInterface contracts based on Amp's HTTP client.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Amp_Http_Client implements Http_Client_Interface, Logger_Aware_Interface, Reset_Interface
{
    use Http_Client_Trait;
    use Logger_Aware_Trait;
    public const OPTIONS_DEFAULTS = Http_Client_Interface::OPTIONS_DEFAULTS + ['crypto_method' => \Stream_crypto_method_tl_Sv1_2_client];
    private array $default_options = self::OPTIONS_DEFAULTS;
    private static array $empty_defaults = self::OPTIONS_DEFAULTS;
    private Amp_Client_State $multi;
    /**
     * @param array         $defaultOptions     Default requests' options
     * @param callable|null $clientConfigurator A callable that builds a {@see DelegateHttpClient} from a {@see PooledHttpClient};
     *                                          passing null builds an {@see InterceptedHttpClient} with 2 retries on failures
     * @param int           $maxHostConnections The maximum number of connections to a single host
     * @param int           $maxPendingPushes   The maximum number of pushed responses to accept in the queue
     *
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public function __construct(array $default_options = [], ?callable $client_configurator = null, int $max_host_connections = 6, int $max_pending_pushes = 50)
    {
        $this->default_options['buffer'] ??= self::should_buffer(...);
        if ($default_options) {
            [, $this->default_options] = self::prepare_request(null, null, $default_options, $this->default_options);
        }
        $this->multi = new Amp_Client_State($client_configurator, $max_host_connections, $max_pending_pushes, $this->logger);
    }
    /**
     * @see HttpClientInterface::OPTIONS_DEFAULTS for available options
     */
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        [$url, $options] = self::prepare_request($method, $url, $options, $this->default_options);
        $options['proxy'] = self::get_proxy($options['proxy'], $url, $options['no_proxy']);
        if (null !== $options['proxy'] && !class_exists(Http1tunnel_Connector::class)) {
            throw new \LogicException('You cannot use the "proxy" option as the "amphp/http-tunnel" package is not installed. Try running "composer require amphp/http-tunnel".');
        }
        if ($options['bindto']) {
            if (str_starts_with((string) $options['bindto'], 'if!')) {
                throw new Transport_Exception(self::class . ' cannot bind to network interfaces, use e.g. CurlHttpClient instead.');
            }
            if (str_starts_with((string) $options['bindto'], 'host!')) {
                $options['bindto'] = substr((string) $options['bindto'], 5);
            }
        }
        if (('' !== $options['body'] || 'POST' === $method || isset($options['normalized_headers']['content-length'])) && !isset($options['normalized_headers']['content-type'])) {
            $options['headers'][] = 'Content-Type: application/x-www-form-urlencoded';
        }
        if (!isset($options['normalized_headers']['user-agent'])) {
            $options['headers'][] = 'User-Agent: Symfony HttpClient (Amp)';
        }
        if (0 < $options['max_duration']) {
            $options['timeout'] = min($options['max_duration'], $options['timeout']);
        }
        if ($options['resolve']) {
            $this->multi->dns_cache = $options['resolve'] + $this->multi->dns_cache;
        }
        if ($options['peer_fingerprint'] && !isset($options['peer_fingerprint']['pin-sha256'])) {
            throw new Transport_Exception(self::class . ' supports only "pin-sha256" fingerprints.');
        }
        $request = new Request(implode('', $url), $method);
        $request->set_body_size_limit(0);
        if ($options['http_version']) {
            $request->set_protocol_versions(match ((float) $options['http_version']) {
                1.0 => ['1.0'],
                1.1 => ['1.1', '1.0'],
                default => ['2', '1.1', '1.0'],
            });
        }
        foreach ($options['headers'] as $v) {
            $h = explode(': ', (string) $v, 2);
            $request->add_header($h[0], $h[1]);
        }
        $request->set_tcp_connect_timeout($options['timeout']);
        $request->set_tls_handshake_timeout($options['timeout']);
        $request->set_transfer_timeout($options['max_duration']);
        $request->set_inactivity_timeout(0);
        if ('' !== $request->get_uri()->get_user_info() && !$request->has_header('authorization')) {
            $auth = explode(':', (string) $request->get_uri()->get_user_info(), 2);
            $auth = array_map(rawurldecode(...), $auth) + [1 => ''];
            $request->set_header('Authorization', 'Basic ' . base64_encode(implode(':', $auth)));
        }
        return new Amp_Response($this->multi, $request, $options, $this->logger);
    }
    public function stream(Response_Interface|iterable $responses, ?float $timeout = null): Response_Stream_Interface
    {
        if ($responses instanceof Amp_Response) {
            $responses = [$responses];
        }
        return new Response_Stream(Amp_Response::stream($responses, $timeout));
    }
    public function reset(): void
    {
        $this->multi->dns_cache = [];
        foreach ($this->multi->pushed_responses as $pushed_responses) {
            foreach ($pushed_responses as [$pushed_url, $push_deferred]) {
                $push_deferred->error(new Cancelled_Exception());
                $this->logger?->debug(\sprintf('Unused pushed response: "%s"', $pushed_url));
            }
        }
        $this->multi->pushed_responses = [];
    }
}