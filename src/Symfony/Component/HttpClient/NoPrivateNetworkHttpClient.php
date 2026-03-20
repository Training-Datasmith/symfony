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

use Symfony\Component\Http_Client\Exception\Transport_Exception;
use Symfony\Component\Http_Client\Response\Async_Context;
use Symfony\Component\Http_Client\Response\Async_Response;
use Symfony\Component\Http_Foundation\Ip_Utils;
use Symfony\Contracts\Http_Client\Chunk_Interface;
use Symfony\Contracts\Http_Client\Http_Client_Interface;
use Symfony\Contracts\Http_Client\Response_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Decorator that blocks requests to private networks by default.
 *
 * @author Hallison Boaventura <hallisonboaventura@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class No_Private_Network_Http_Client implements Http_Client_Interface, Reset_Interface
{
    use Async_Decorator_Trait;
    use Http_Client_Trait;
    private array $default_options = self::OPTIONS_DEFAULTS;
    private Http_Client_Interface $client;
    private ?array $subnets;
    private int $ip_flags;
    private \ArrayObject $dns_cache;
    /**
     * @param string|array|null $subnets String or array of subnets using CIDR notation that should be considered private.
     *                                   If null is passed, the standard private subnets will be used.
     */
    public function __construct(Http_Client_Interface $client, string|array|null $subnets = null)
    {
        if (!class_exists(Ip_Utils::class)) {
            throw new \LogicException(\sprintf('You cannot use "%s" if the HttpFoundation component is not installed. Try running "composer require symfony/http-foundation".', self::class));
        }
        if (null === $subnets) {
            $ip_flags = \FILTER_FLAG_IPV4 | \FILTER_FLAG_IPV6;
        } else {
            $ip_flags = 0;
            foreach ((array) $subnets as $subnet) {
                $ip_flags |= str_contains((string) $subnet, ':') ? \FILTER_FLAG_IPV6 : \FILTER_FLAG_IPV4;
            }
        }
        if (!\defined('STREAM_PF_INET6')) {
            $ip_flags &= ~\FILTER_FLAG_IPV6;
        }
        $this->client = $client;
        $this->subnets = null !== $subnets ? (array) $subnets : null;
        $this->ip_flags = $ip_flags;
        $this->dns_cache = new \ArrayObject();
    }
    public function request(string $method, string $url, array $options = []): Response_Interface
    {
        [$url, $options] = self::prepare_request($method, $url, $options, $this->default_options, true);
        $redirect_headers = parse_url((string) $url['authority']);
        $host = $redirect_headers['host'];
        $url = implode('', $url);
        $dns_cache = $this->dns_cache;
        $ip = self::dns_resolve($dns_cache, $host, $this->ip_flags, $options);
        self::ip_check($ip, $this->subnets, $this->ip_flags, $host, $url);
        $on_progress = $options['on_progress'] ?? null;
        $subnets = $this->subnets;
        $ip_flags = $this->ip_flags;
        $options['on_progress'] = static function (int $dl_now, int $dl_size, array $info) use ($on_progress, $subnets, $ip_flags): void {
            static $last_primary_ip = '';
            if (!\in_array($info['primary_ip'] ?? '', ['', $last_primary_ip], true)) {
                self::ip_check($info['primary_ip'], $subnets, $ip_flags, null, $info['url']);
                $last_primary_ip = $info['primary_ip'];
            }
            null !== $on_progress && $on_progress($dl_now, $dl_size, $info);
        };
        if (0 >= $max_redirects = $options['max_redirects']) {
            return new Async_Response($this->client, $method, $url, $options);
        }
        $options['max_redirects'] = 0;
        $redirect_headers['with_auth'] = $redirect_headers['no_auth'] = $options['headers'];
        if (isset($options['normalized_headers']['host']) || isset($options['normalized_headers']['authorization']) || isset($options['normalized_headers']['cookie'])) {
            $redirect_headers['no_auth'] = array_filter($redirect_headers['no_auth'], static fn($h): bool => 0 !== stripos((string) $h, 'Host:') && 0 !== stripos((string) $h, 'Authorization:') && 0 !== stripos((string) $h, 'Cookie:'));
        }
        return new Async_Response($this->client, $method, $url, $options, static function (Chunk_Interface $chunk, Async_Context $context) use (&$method, &$options, $max_redirects, &$redirect_headers, $subnets, $ip_flags, $dns_cache): \Generator {
            if (null !== $chunk->get_error() || $chunk->is_timeout() || !$chunk->is_first()) {
                yield $chunk;
                return;
            }
            $status_code = $context->get_status_code();
            if ($status_code < 300 || 400 <= $status_code || null === $url = $context->get_info('redirect_url')) {
                $context->passthru();
                yield $chunk;
                return;
            }
            $host = parse_url((string) $url, \PHP_URL_HOST);
            $ip = self::dns_resolve($dns_cache, $host, $ip_flags, $options);
            self::ip_check($ip, $subnets, $ip_flags, $host, $url);
            // Do like curl and browsers: turn POST to GET on 301, 302 and 303
            if (303 === $status_code || 'POST' === $method && \in_array($status_code, [301, 302], true)) {
                $method = 'HEAD' === $method ? 'HEAD' : 'GET';
                unset($options['body'], $options['json']);
                if (isset($options['normalized_headers']['content-length']) || isset($options['normalized_headers']['content-type']) || isset($options['normalized_headers']['transfer-encoding'])) {
                    $filter_content_headers = static fn($h): bool => 0 !== stripos($h, 'Content-Length:') && 0 !== stripos($h, 'Content-Type:') && 0 !== stripos($h, 'Transfer-Encoding:');
                    $options['headers'] = array_filter($options['headers'], $filter_content_headers);
                    $redirect_headers['no_auth'] = array_filter($redirect_headers['no_auth'], $filter_content_headers);
                    $redirect_headers['with_auth'] = array_filter($redirect_headers['with_auth'], $filter_content_headers);
                }
            }
            // Authorization and Cookie headers MUST NOT follow except for the initial host name
            $port = parse_url((string) $url, \PHP_URL_PORT);
            $options['headers'] = $redirect_headers['host'] === $host && ($redirect_headers['port'] ?? null) === $port ? $redirect_headers['with_auth'] : $redirect_headers['no_auth'];
            static $redirect_count = 0;
            $context->set_info('redirect_count', ++$redirect_count);
            $context->replace_request($method, $url, $options);
            if ($redirect_count >= $max_redirects) {
                $context->passthru();
            }
        });
    }
    public function with_options(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->with_options($options);
        $clone->default_options = self::merge_default_options($options, $this->default_options);
        return $clone;
    }
    public function reset(): void
    {
        $this->dns_cache->exchange_array([]);
        if ($this->client instanceof Reset_Interface) {
            $this->client->reset();
        }
    }
    private static function dns_resolve(\ArrayObject $dns_cache, string $host, int $ip_flags, array &$options): string
    {
        if ($ip = filter_var(trim($host, '[]'), \FILTER_VALIDATE_IP) ?: $options['resolve'][$host] ?? false) {
            return $ip;
        }
        if ($dns_cache->offsetExists($host)) {
            return $dns_cache[$host];
        }
        if (\FILTER_FLAG_IPV4 & $ip_flags && $ip = gethostbynamel($host)) {
            return $options['resolve'][$host] = $dns_cache[$host] = $ip[0];
        }
        if (!(\FILTER_FLAG_IPV6 & $ip_flags)) {
            return $host;
        }
        if ($ip = dns_get_record($host, \DNS_AAAA)) {
            $ip = $ip[0]['ipv6'];
        } elseif (\extension_loaded('sockets')) {
            if (!$info = socket_addrinfo_lookup($host, 0, ['ai_socktype' => \SOCK_STREAM, 'ai_family' => \AF_INET6])) {
                return $host;
            }
            $ip = socket_addrinfo_explain($info[0])['ai_addr']['sin6_addr'];
        } elseif ('localhost' === $host || 'localhost.' === $host) {
            $ip = '::1';
        } else {
            return $host;
        }
        return $options['resolve'][$host] = $dns_cache[$host] = $ip;
    }
    private static function ip_check(string $ip, ?array $subnets, int $ip_flags, ?string $host, string $url): void
    {
        if (null === $subnets) {
            // Quick check, but not reliable enough, see https://github.com/php/php-src/issues/16944
            $ip_flags |= \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE;
        }
        if (false !== filter_var($ip, \FILTER_VALIDATE_IP, $ip_flags) && !Ip_Utils::check_ip($ip, $subnets ?? Ip_Utils::PRIVATE_SUBNETS)) {
            return;
        }
        if (null !== $host) {
            $type = 'Host';
        } else {
            $host = $ip;
            $type = 'IP';
        }
        throw new Transport_Exception($type . \sprintf(' "%s" is blocked for "%s".', $host, $url));
    }
}