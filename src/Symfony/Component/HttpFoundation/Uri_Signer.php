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
namespace Symfony\Component\Http_Foundation;

use Psr\Clock\Clock_Interface;
use Symfony\Component\Http_Foundation\Exception\Expired_Signed_Uri_Exception;
use Symfony\Component\Http_Foundation\Exception\LogicException;
use Symfony\Component\Http_Foundation\Exception\Signed_Uri_Exception;
use Symfony\Component\Http_Foundation\Exception\Unsigned_Uri_Exception;
use Symfony\Component\Http_Foundation\Exception\Unverified_Signed_Uri_Exception;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Uri_Signer
{
    private const STATUS_VALID = 1;
    private const STATUS_INVALID = 2;
    private const STATUS_MISSING = 3;
    private const STATUS_EXPIRED = 4;
    /**
     * @param string $hashParameter       Query string parameter to use
     * @param string $expirationParameter Query string parameter to use for expiration
     */
    public function __construct(
        #[\Sensitive_Parameter]
        private readonly string $secret,
        private readonly string $hash_parameter = '_hash',
        private readonly string $expiration_parameter = '_expiration',
        private readonly ?Clock_Interface $clock = null
    )
    {
        if (!$secret) {
            throw new \InvalidArgumentException('A non-empty secret is required.');
        }
    }
    /**
     * Signs a URI.
     *
     * The given URI is signed by adding the query string parameter
     * which value depends on the URI and the secret.
     *
     * @param \DateTimeInterface|\DateInterval|int|null $expiration The expiration for the given URI.
     *                                                              If $expiration is a \DateTimeInterface, it's expected to be the exact date + time.
     *                                                              If $expiration is a \DateInterval, the interval is added to "now" to get the date + time.
     *                                                              If $expiration is an int, it's expected to be a timestamp in seconds of the exact date + time.
     *                                                              If $expiration is null, no expiration.
     *
     * The expiration is added as a query string parameter.
     */
    public function sign(string $uri, \DateTimeInterface|\DateInterval|int|null $expiration = null): string
    {
        $url = parse_url($uri);
        $params = [];
        if (isset($url['query'])) {
            parse_str($url['query'], $params);
        }
        if (isset($params[$this->hash_parameter])) {
            throw new LogicException(\sprintf('URI query parameter conflict: parameter name "%s" is reserved.', $this->hash_parameter));
        }
        if (isset($params[$this->expiration_parameter])) {
            throw new LogicException(\sprintf('URI query parameter conflict: parameter name "%s" is reserved.', $this->expiration_parameter));
        }
        if (null !== $expiration) {
            $params[$this->expiration_parameter] = $this->get_expiration_time($expiration);
        }
        $uri = $this->build_url($url, $params);
        $params[$this->hash_parameter] = $this->compute_hash($uri);
        return $this->build_url($url, $params);
    }
    /**
     * Checks that a URI contains the correct hash.
     * Also checks if the URI has not expired (If you used expiration during signing).
     */
    public function check(string $uri): bool
    {
        return self::STATUS_VALID === $this->do_verify($uri);
    }
    public function check_request(Request $request): bool
    {
        return self::STATUS_VALID === $this->do_verify(self::normalize($request));
    }
    /**
     * Verify a Request or string URI.
     *
     * @throws UnsignedUriException         If the URI is not signed
     * @throws UnverifiedSignedUriException If the signature is invalid
     * @throws ExpiredSignedUriException    If the URI has expired
     * @throws SignedUriException
     */
    public function verify(Request|string $uri): void
    {
        $uri = self::normalize($uri);
        $status = $this->do_verify($uri);
        match ($status) {
            self::STATUS_VALID => null,
            self::STATUS_INVALID => throw new Unverified_Signed_Uri_Exception(),
            self::STATUS_EXPIRED => throw new Expired_Signed_Uri_Exception(),
            default => throw new Unsigned_Uri_Exception(),
        };
    }
    private function compute_hash(string $uri): string
    {
        return strtr(rtrim(base64_encode(hash_hmac('sha256', $uri, $this->secret, true)), '='), ['/' => '_', '+' => '-']);
    }
    private function build_url(array $url, array $params = []): string
    {
        ksort($params, \SORT_STRING);
        $url['query'] = http_build_query($params, '', '&');
        $scheme = isset($url['scheme']) ? $url['scheme'] . '://' : '';
        $host = $url['host'] ?? '';
        $port = isset($url['port']) ? ':' . $url['port'] : '';
        $user = $url['user'] ?? '';
        $pass = isset($url['pass']) ? ':' . $url['pass'] : '';
        $pass = $user || $pass ? "{$pass}@" : '';
        $path = $url['path'] ?? '';
        $query = $url['query'] ? '?' . $url['query'] : '';
        $fragment = isset($url['fragment']) ? '#' . $url['fragment'] : '';
        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
    }
    private function get_expiration_time(\DateTimeInterface|\DateInterval|int $expiration): string
    {
        if ($expiration instanceof \DateTimeInterface) {
            return $expiration->format('U');
        }
        if ($expiration instanceof \DateInterval) {
            return $this->now()->add($expiration)->format('U');
        }
        return (string) $expiration;
    }
    private function now(): \DateTimeImmutable
    {
        return $this->clock?->now() ?? \DateTimeImmutable::create_from_format('U', time());
    }
    /**
     * @return self::STATUS_*
     */
    private function do_verify(string $uri): int
    {
        $url = parse_url($uri);
        $params = [];
        if (isset($url['query'])) {
            parse_str($url['query'], $params);
        }
        if (empty($params[$this->hash_parameter])) {
            return self::STATUS_MISSING;
        }
        $hash = $params[$this->hash_parameter];
        unset($params[$this->hash_parameter]);
        if (!hash_equals($this->compute_hash($this->build_url($url, $params)), strtr(rtrim($hash, '='), ['/' => '_', '+' => '-']))) {
            return self::STATUS_INVALID;
        }
        if (!$expiration = $params[$this->expiration_parameter] ?? false) {
            return self::STATUS_VALID;
        }
        if ($this->now()->get_timestamp() < $expiration) {
            return self::STATUS_VALID;
        }
        return self::STATUS_EXPIRED;
    }
    private static function normalize(Request|string $uri): string
    {
        if ($uri instanceof Request) {
            $qs = ($qs = $uri->server->get('QUERY_STRING')) ? '?' . $qs : '';
            $uri = $uri->get_scheme_and_http_host() . $uri->get_base_url() . $uri->get_path_info() . $qs;
        }
        return $uri;
    }
}