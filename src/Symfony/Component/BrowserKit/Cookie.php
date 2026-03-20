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
namespace Symfony\Component\Browser_Kit;

use Symfony\Component\Browser_Kit\Exception\InvalidArgumentException;
use Symfony\Component\Browser_Kit\Exception\UnexpectedValueException;
/**
 * Cookie represents an HTTP cookie.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Cookie implements \Stringable
{
    /**
     * Handles dates as defined by RFC 2616 section 3.3.1, and also some other
     * non-standard, but common formats.
     */
    private const DATE_FORMATS = ['D, d M Y H:i:s T', 'D, d-M-y H:i:s T', 'D, d-M-Y H:i:s T', 'D, d-m-y H:i:s T', 'D, d-m-Y H:i:s T', 'D M j G:i:s Y', 'D M d H:i:s Y T'];
    protected string $value;
    protected ?string $expires = null;
    protected string $path;
    protected string $raw_value;
    /**
     * Sets a cookie.
     *
     * @param string          $name         The cookie name
     * @param string|null     $value        The value of the cookie
     * @param string|int|null $expires      The time the cookie expires
     * @param string|null     $path         The path on the server in which the cookie will be available on
     * @param string          $domain       The domain that the cookie is available
     * @param bool            $secure       Indicates that the cookie should only be transmitted over a secure HTTPS connection from the client
     * @param bool            $httponly     The cookie httponly flag
     * @param bool            $encodedValue Whether the value is encoded or not
     * @param string|null     $samesite     The cookie samesite attribute
     */
    public function __construct(private readonly string $name, ?string $value, string|int|null $expires = null, ?string $path = null, private readonly string $domain = '', private readonly bool $secure = false, private readonly bool $httponly = true, bool $encoded_value = false, private readonly ?string $samesite = null)
    {
        if ($encoded_value) {
            $this->raw_value = $value ?? '';
            $this->value = urldecode($this->raw_value);
        } else {
            $this->value = $value ?? '';
            $this->raw_value = rawurlencode($this->value);
        }
        $this->path = $path ?: '/';
        if (null !== $expires) {
            $timestamp_as_date_time = \DateTimeImmutable::create_from_format('U', $expires);
            if (false === $timestamp_as_date_time) {
                throw new UnexpectedValueException(\sprintf('The cookie expiration time "%s" is not valid.', $expires));
            }
            $this->expires = $timestamp_as_date_time->format('U');
        }
    }
    /**
     * Returns the HTTP representation of the Cookie.
     */
    public function __toString(): string
    {
        $cookie = \sprintf('%s=%s', $this->name, $this->raw_value);
        if (null !== $this->expires) {
            $date_time = \DateTimeImmutable::create_from_format('U', $this->expires, new \DateTimeZone('GMT'));
            $cookie .= '; expires=' . str_replace('+0000', '', $date_time->format(self::DATE_FORMATS[0]));
        }
        if ('' !== $this->domain) {
            $cookie .= '; domain=' . $this->domain;
        }
        if ($this->path) {
            $cookie .= '; path=' . $this->path;
        }
        if ($this->secure) {
            $cookie .= '; secure';
        }
        if ($this->httponly) {
            $cookie .= '; httponly';
        }
        if (null !== $this->samesite) {
            $cookie .= '; samesite=' . $this->samesite;
        }
        return $cookie;
    }
    /**
     * Creates a Cookie instance from a Set-Cookie header value.
     *
     * @throws InvalidArgumentException
     */
    public static function from_string(string $cookie, ?string $url = null): static
    {
        $parts = explode(';', $cookie);
        if (!str_contains($parts[0], '=')) {
            throw new InvalidArgumentException(\sprintf('The cookie string "%s" is not valid.', $parts[0]));
        }
        [$name, $value] = explode('=', array_shift($parts), 2);
        $values = ['name' => trim($name), 'value' => trim($value), 'expires' => null, 'path' => '/', 'domain' => '', 'secure' => false, 'httponly' => false, 'passedRawValue' => true, 'samesite' => null];
        if (null !== $url) {
            if (false === ($url_parts = parse_url($url)) || !isset($url_parts['host'])) {
                throw new InvalidArgumentException(\sprintf('The URL "%s" is not valid.', $url));
            }
            $values['domain'] = $url_parts['host'];
            $values['path'] = isset($url_parts['path']) ? substr($url_parts['path'], 0, strrpos($url_parts['path'], '/')) : '';
        }
        foreach ($parts as $part) {
            $part = trim($part);
            if ('secure' === strtolower($part)) {
                // Ignore the secure flag if the original URI is not given or is not HTTPS
                if (null === $url) {
                    continue;
                }
                if (!isset($url_parts['scheme'])) {
                    continue;
                }
                if ('https' !== $url_parts['scheme']) {
                    continue;
                }
                $values['secure'] = true;
                continue;
            }
            if ('httponly' === strtolower($part)) {
                $values['httponly'] = true;
                continue;
            }
            if (2 === \count($elements = explode('=', $part, 2))) {
                if ('expires' === strtolower($elements[0])) {
                    $elements[1] = self::parse_date($elements[1]);
                }
                $values[strtolower($elements[0])] = $elements[1];
            }
        }
        return new static($values['name'], $values['value'], $values['expires'], $values['path'], $values['domain'], $values['secure'], $values['httponly'], $values['passedRawValue'], $values['samesite']);
    }
    private static function parse_date(string $date_value): ?string
    {
        // trim single quotes around date if present
        if (($length = \strlen($date_value)) > 1 && "'" === $date_value[0] && "'" === $date_value[$length - 1]) {
            $date_value = substr($date_value, 1, -1);
        }
        foreach (self::DATE_FORMATS as $date_format) {
            if (false !== $date = \DateTimeImmutable::create_from_format($date_format, $date_value, new \DateTimeZone('GMT'))) {
                return $date->format('U');
            }
        }
        // attempt a fallback for unusual formatting
        if (false !== $date = date_create_immutable($date_value, new \DateTimeZone('GMT'))) {
            return $date->format('U');
        }
        return null;
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
    public function get_value(): string
    {
        return $this->value;
    }
    /**
     * Gets the raw value of the cookie.
     */
    public function get_raw_value(): string
    {
        return $this->raw_value;
    }
    /**
     * Gets the expires time of the cookie.
     */
    public function get_expires_time(): ?string
    {
        return $this->expires;
    }
    /**
     * Gets the path of the cookie.
     */
    public function get_path(): string
    {
        return $this->path;
    }
    /**
     * Gets the domain of the cookie.
     */
    public function get_domain(): string
    {
        return $this->domain;
    }
    /**
     * Returns the secure flag of the cookie.
     */
    public function is_secure(): bool
    {
        return $this->secure;
    }
    /**
     * Returns the httponly flag of the cookie.
     */
    public function is_http_only(): bool
    {
        return $this->httponly;
    }
    /**
     * Returns true if the cookie has expired.
     */
    public function is_expired(): bool
    {
        return null !== $this->expires && 0 != $this->expires && $this->expires <= time();
    }
    /**
     * Gets the samesite attribute of the cookie.
     */
    public function get_same_site(): ?string
    {
        return $this->samesite;
    }
}