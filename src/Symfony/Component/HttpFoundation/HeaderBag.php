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
 * HeaderBag is a container for HTTP headers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @implements \IteratorAggregate<string, list<string|null>>
 */
class Header_Bag implements \IteratorAggregate, \Countable, \Stringable
{
    protected const UPPER = '_ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    protected const LOWER = '-abcdefghijklmnopqrstuvwxyz';
    /**
     * @var array<string, list<string|null>>
     */
    protected array $headers = [];
    protected array $cache_control = [];
    public function __construct(array $headers = [])
    {
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
    }
    /**
     * Returns the headers as a string.
     */
    public function __toString(): string
    {
        if (!$headers = $this->all()) {
            return '';
        }
        ksort($headers);
        $max = max(array_map(strlen(...), array_keys($headers))) + 1;
        $content = '';
        foreach ($headers as $name => $values) {
            $name = ucwords($name, '-');
            foreach ($values as $value) {
                $content .= \sprintf("%-{$max}s %s\r\n", $name . ':', $value);
            }
        }
        return $content;
    }
    /**
     * Returns the headers.
     *
     * @param string|null $key The name of the headers to return or null to get them all
     *
     * @return ($key is null ? array<string, list<string|null>> : list<string|null>)
     */
    public function all(?string $key = null): array
    {
        if (null !== $key) {
            return $this->headers[strtr($key, self::UPPER, self::LOWER)] ?? [];
        }
        return $this->headers;
    }
    /**
     * Returns the parameter keys.
     *
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }
    /**
     * Replaces the current HTTP headers by a new set.
     */
    public function replace(array $headers = []): void
    {
        $this->headers = [];
        $this->add($headers);
    }
    /**
     * Adds new headers the current HTTP headers set.
     */
    public function add(array $headers): void
    {
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
    }
    /**
     * Returns the first header by name or the default one.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $headers = $this->all($key);
        if (!$headers) {
            return $default;
        }
        if (null === $headers[0]) {
            return null;
        }
        return $headers[0];
    }
    /**
     * Sets a header by name.
     *
     * @param string|string[]|null $values  The value or an array of values
     * @param bool                 $replace Whether to replace the actual value or not (true by default)
     */
    public function set(string $key, string|array|null $values, bool $replace = true): void
    {
        $key = strtr($key, self::UPPER, self::LOWER);
        if (\is_array($values)) {
            $values = array_values($values);
            if (true === $replace || !isset($this->headers[$key])) {
                $this->headers[$key] = $values;
            } else {
                $this->headers[$key] = array_merge($this->headers[$key], $values);
            }
        } else if (true === $replace || !isset($this->headers[$key])) {
            $this->headers[$key] = [$values];
        } else {
            $this->headers[$key][] = $values;
        }
        if ('cache-control' === $key) {
            $this->cache_control = $this->parse_cache_control(implode(', ', $this->headers[$key]));
        }
    }
    /**
     * Returns true if the HTTP header is defined.
     */
    public function has(string $key): bool
    {
        return \array_key_exists(strtr($key, self::UPPER, self::LOWER), $this->all());
    }
    /**
     * Returns true if the given HTTP header contains the given value.
     */
    public function contains(string $key, string $value): bool
    {
        return \in_array($value, $this->all($key), true);
    }
    /**
     * Removes a header.
     */
    public function remove(string $key): void
    {
        $key = strtr($key, self::UPPER, self::LOWER);
        unset($this->headers[$key]);
        if ('cache-control' === $key) {
            $this->cache_control = [];
        }
    }
    /**
     * Returns the HTTP header value converted to a date.
     *
     * @throws \RuntimeException When the HTTP header is not parseable
     */
    public function get_date(string $key, ?\DateTimeInterface $default = null): ?\DateTimeImmutable
    {
        if (null === $value = $this->get($key)) {
            return null !== $default ? \DateTimeImmutable::create_from_interface($default) : null;
        }
        if (false === $date = \DateTimeImmutable::create_from_format(\DATE_RFC2822, $value)) {
            throw new \RuntimeException(\sprintf('The "%s" HTTP header is not parseable (%s).', $key, $value));
        }
        return $date;
    }
    /**
     * Adds a custom Cache-Control directive.
     */
    public function add_cache_control_directive(string $key, bool|string $value = true): void
    {
        $this->cache_control[$key] = $value;
        $this->set('Cache-Control', $this->get_cache_control_header());
    }
    /**
     * Returns true if the Cache-Control directive is defined.
     */
    public function has_cache_control_directive(string $key): bool
    {
        return \array_key_exists($key, $this->cache_control);
    }
    /**
     * Returns a Cache-Control directive value by name.
     */
    public function get_cache_control_directive(string $key): bool|string|null
    {
        return $this->cache_control[$key] ?? null;
    }
    /**
     * Removes a Cache-Control directive.
     */
    public function remove_cache_control_directive(string $key): void
    {
        unset($this->cache_control[$key]);
        $this->set('Cache-Control', $this->get_cache_control_header());
    }
    /**
     * Returns an iterator for headers.
     *
     * @return \ArrayIterator<string, list<string|null>>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->headers);
    }
    /**
     * Returns the number of headers.
     */
    public function count(): int
    {
        return \count($this->headers);
    }
    protected function get_cache_control_header(): string
    {
        ksort($this->cache_control);
        return Header_Utils::to_string($this->cache_control, ',');
    }
    /**
     * Parses a Cache-Control HTTP header.
     */
    protected function parse_cache_control(string $header): array
    {
        $parts = Header_Utils::split($header, ',=');
        return Header_Utils::combine($parts);
    }
}