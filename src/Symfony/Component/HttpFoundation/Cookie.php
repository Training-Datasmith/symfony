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

/**
 * Represents an HTTP Set-Cookie header value.
 *
 * Instances are immutable after construction; use the with*() methods to
 * create modified copies (fluent builder pattern).
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @since 2.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6265 HTTP State Management Mechanism
 * @see https://datatracker.ietf.org/doc/html/draft-west-cookie-incrementalism SameSite cookies
 */
class Cookie implements \Stringable
{
    public const SAMESITE_NONE = 'none';
    public const SAMESITE_LAX = 'lax';
    public const SAMESITE_STRICT = 'strict';
    protected int $expire;
    protected string $path;
    private ?string $same_site = null;
    private bool $secure_default = false;
    private const RESERVED_CHARS_LIST = "=,; \t\r\n\v\f";
    private const RESERVED_CHARS_FROM = ['=', ',', ';', ' ', "\t", "\r", "\n", "\v", "\f"];
    private const RESERVED_CHARS_TO = ['%3D', '%2C', '%3B', '%20', '%09', '%0D', '%0A', '%0B', '%0C'];
    /**
     * Creates a Cookie instance from a raw Set-Cookie header string.
     *
     * @param string $cookie The raw Set-Cookie header value (e.g. "name=value; Path=/; HttpOnly")
     * @param bool   $decode Whether to URL-decode the cookie name and value (default: false)
     *
     * @return static A new Cookie instance parsed from the header string
     *
     * @since 2.2
     */
    public static function from_string(string $cookie, bool $decode = false): static
    {
        $data = ['expires' => 0, 'path' => '/', 'domain' => null, 'secure' => false, 'httponly' => false, 'raw' => !$decode, 'samesite' => null, 'partitioned' => false];
        $parts = Header_Utils::split($cookie, ';=');
        $part = array_shift($parts);
        $name = $decode ? urldecode((string) $part[0]) : $part[0];
        $value = isset($part[1]) ? $decode ? urldecode($part[1]) : $part[1] : null;
        $data = Header_Utils::combine($parts) + $data;
        $data['expires'] = self::expires_timestamp($data['expires']);
        if (isset($data['max-age']) && ($data['max-age'] > 0 || $data['expires'] > time())) {
            $data['expires'] = time() + (int) $data['max-age'];
        }
        return new static($name, $value, $data['expires'], $data['path'], $data['domain'], $data['secure'], $data['httponly'], $data['raw'], $data['samesite'], $data['partitioned']);
    }
    /**
     * @see self::__construct
     *
     * @param self::SAMESITE_*|''|null $sameSite
     */
    public static function create(string $name, ?string $value = null, int|string|\DateTimeInterface $expire = 0, ?string $path = '/', ?string $domain = null, ?bool $secure = null, bool $http_only = true, bool $raw = false, ?string $same_site = self::SAMESITE_LAX, bool $partitioned = false): self
    {
        return new self($name, $value, $expire, $path, $domain, $secure, $http_only, $raw, $same_site, $partitioned);
    }
    /**
     * @param string                        $name     The name of the cookie
     * @param string|null                   $value    The value of the cookie
     * @param int|string|\DateTimeInterface $expire   The time the cookie expires
     * @param string|null                   $path     The path on the server in which the cookie will be available on
     * @param string|null                   $domain   The domain that the cookie is available to
     * @param bool|null                     $secure   Whether the client should send back the cookie only over HTTPS or null to auto-enable this when the request is already using HTTPS
     * @param bool                          $httpOnly Whether the cookie will be made accessible only through the HTTP protocol
     * @param bool                          $raw      Whether the cookie value should be sent with no url encoding
     * @param self::SAMESITE_*|''|null      $sameSite Whether the cookie will be available for cross-site requests
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(protected string $name, protected ?string $value = null, int|string|\DateTimeInterface $expire = 0, ?string $path = '/', protected ?string $domain = null, protected ?bool $secure = null, protected bool $http_only = true, private bool $raw = false, ?string $same_site = self::SAMESITE_LAX, private bool $partitioned = false)
    {
        // from PHP source code
        if ($raw && false !== strpbrk($name, self::RESERVED_CHARS_LIST)) {
            throw new \InvalidArgumentException(\sprintf('The cookie name "%s" contains invalid characters.', $name));
        }
        if (!$name) {
            throw new \InvalidArgumentException('The cookie name cannot be empty.');
        }
        $this->expire = self::expires_timestamp($expire);
        $this->path = $path ?: '/';
        $this->same_site = $this->with_same_site($same_site)->same_site;
    }
    /**
     * Creates a cookie copy with a new value.
     */
    public function with_value(?string $value): static
    {
        $cookie = clone $this;
        $cookie->value = $value;
        return $cookie;
    }
    /**
     * Creates a cookie copy with a new domain that the cookie is available to.
     */
    public function with_domain(?string $domain): static
    {
        $cookie = clone $this;
        $cookie->domain = $domain;
        return $cookie;
    }
    /**
     * Creates a cookie copy with a new time the cookie expires.
     */
    public function with_expires(int|string|\DateTimeInterface $expire = 0): static
    {
        $cookie = clone $this;
        $cookie->expire = self::expires_timestamp($expire);
        return $cookie;
    }
    /**
     * Converts expires formats to a unix timestamp.
     */
    private static function expires_timestamp(int|string|\DateTimeInterface $expire = 0): int
    {
        // convert expiration time to a Unix timestamp
        if ($expire instanceof \DateTimeInterface) {
            $expire = $expire->format('U');
        } elseif (!is_numeric($expire)) {
            $expire = strtotime($expire);
            if (false === $expire) {
                throw new \InvalidArgumentException('The cookie expiration time is not valid.');
            }
        }
        return 0 < $expire ? (int) $expire : 0;
    }
    /**
     * Creates a cookie copy with a new path on the server in which the cookie will be available on.
     */
    public function with_path(string $path): static
    {
        $cookie = clone $this;
        $cookie->path = '' === $path ? '/' : $path;
        return $cookie;
    }
    /**
     * Creates a cookie copy that only be transmitted over a secure HTTPS connection from the client.
     */
    public function with_secure(bool $secure = true): static
    {
        $cookie = clone $this;
        $cookie->secure = $secure;
        return $cookie;
    }
    /**
     * Creates a cookie copy that be accessible only through the HTTP protocol.
     */
    public function with_http_only(bool $http_only = true): static
    {
        $cookie = clone $this;
        $cookie->http_only = $http_only;
        return $cookie;
    }
    /**
     * Creates a cookie copy that uses no url encoding.
     */
    public function with_raw(bool $raw = true): static
    {
        if ($raw && false !== strpbrk($this->name, self::RESERVED_CHARS_LIST)) {
            throw new \InvalidArgumentException(\sprintf('The cookie name "%s" contains invalid characters.', $this->name));
        }
        $cookie = clone $this;
        $cookie->raw = $raw;
        return $cookie;
    }
    /**
     * Creates a cookie copy with SameSite attribute.
     *
     * @param self::SAMESITE_*|''|null $sameSite
     */
    public function with_same_site(?string $same_site): static
    {
        if ('' === $same_site) {
            $same_site = null;
        } elseif (null !== $same_site) {
            $same_site = strtolower($same_site);
        }
        if (!\in_array($same_site, [self::SAMESITE_LAX, self::SAMESITE_STRICT, self::SAMESITE_NONE, null], true)) {
            throw new \InvalidArgumentException('The "sameSite" parameter value is not valid.');
        }
        $cookie = clone $this;
        $cookie->same_site = $same_site;
        return $cookie;
    }
    /**
     * Creates a cookie copy that is tied to the top-level site in cross-site context.
     */
    public function with_partitioned(bool $partitioned = true): static
    {
        $cookie = clone $this;
        $cookie->partitioned = $partitioned;
        return $cookie;
    }
    /**
     * Returns the cookie as a string.
     */
    public function __toString(): string
    {
        if ($this->is_raw()) {
            $str = $this->get_name();
        } else {
            $str = str_replace(self::RESERVED_CHARS_FROM, self::RESERVED_CHARS_TO, $this->get_name());
        }
        $str .= '=';
        if ('' === (string) $this->get_value()) {
            $str .= 'deleted; expires=' . gmdate('D, d M Y H:i:s T', time() - 31536001) . '; Max-Age=0';
        } else {
            $str .= $this->is_raw() ? $this->get_value() : rawurlencode((string) $this->get_value());
            if (0 !== $this->get_expires_time()) {
                $str .= '; expires=' . gmdate('D, d M Y H:i:s T', $this->get_expires_time()) . '; Max-Age=' . $this->get_max_age();
            }
        }
        if ($this->get_path()) {
            $str .= '; path=' . $this->get_path();
        }
        if ($this->get_domain()) {
            $str .= '; domain=' . $this->get_domain();
        }
        if ($this->is_secure()) {
            $str .= '; secure';
        }
        if ($this->is_http_only()) {
            $str .= '; httponly';
        }
        if (null !== $this->get_same_site()) {
            $str .= '; samesite=' . $this->get_same_site();
        }
        if ($this->is_partitioned()) {
            $str .= '; partitioned';
        }
        return $str;
    }
    /**
     * Gets the name of the cookie.
     */
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * Gets the value of the cookie.
     */
    public function get_value(): ?string
    {
        return $this->value;
    }
    /**
     * Gets the domain that the cookie is available to.
     */
    public function get_domain(): ?string
    {
        return $this->domain;
    }
    /**
     * Gets the time the cookie expires.
     */
    public function get_expires_time(): int
    {
        return $this->expire;
    }
    /**
     * Gets the max-age attribute.
     */
    public function get_max_age(): int
    {
        $max_age = $this->expire - time();
        return max(0, $max_age);
    }
    /**
     * Gets the path on the server in which the cookie will be available on.
     */
    public function get_path(): string
    {
        return $this->path;
    }
    /**
     * Checks whether the cookie should only be transmitted over a secure HTTPS connection from the client.
     */
    public function is_secure(): bool
    {
        return $this->secure ?? $this->secure_default;
    }
    /**
     * Checks whether the cookie will be made accessible only through the HTTP protocol.
     */
    public function is_http_only(): bool
    {
        return $this->http_only;
    }
    /**
     * Whether this cookie is about to be cleared.
     */
    public function is_cleared(): bool
    {
        return 0 !== $this->expire && $this->expire < time();
    }
    /**
     * Checks if the cookie value should be sent with no url encoding.
     */
    public function is_raw(): bool
    {
        return $this->raw;
    }
    /**
     * Checks whether the cookie should be tied to the top-level site in cross-site context.
     */
    public function is_partitioned(): bool
    {
        return $this->partitioned;
    }
    /**
     * @return self::SAMESITE_*|null
     */
    public function get_same_site(): ?string
    {
        return $this->same_site;
    }
    /**
     * Sets the default value of the "secure" flag used when the secure property is null.
     *
     * This is called automatically by Response::prepare() when the request is over HTTPS,
     * ensuring all cookies are automatically secured without requiring explicit configuration.
     *
     * @param bool $default The default secure value; true means cookies are sent over HTTPS only
     *
     * @since 3.1
     */
    public function set_secure_default(bool $default): void
    {
        $this->secure_default = $default;
    }
}