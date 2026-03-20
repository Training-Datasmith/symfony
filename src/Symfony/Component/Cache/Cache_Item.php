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
namespace Symfony\Component\Cache;

use Psr\Cache\Cache_Item_Interface;
use Psr\Log\Logger_Interface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Exception\LogicException;
use Symfony\Contracts\Cache\Item_Interface;
/**
 * PSR-6 / Symfony Cache item implementation.
 *
 * Represents a single cached value together with its metadata (TTL, tags, etc.).
 * Items are created exclusively by cache pool adapters — do not instantiate directly.
 *
 * The tagging API (tag()) is only available when the item comes from a
 * tag-aware pool (e.g. TagAwareAdapter). Calling tag() on a non-taggable item
 * throws a LogicException.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @since 3.1
 *
 * @see https://www.php-fig.org/psr/psr-6/ PSR-6 Cache Interface
 */
final class Cache_Item implements Item_Interface
{
    private const METADATA_EXPIRY_OFFSET = 1527506807;
    private const VALUE_WRAPPER = "\xa9";
    protected string $key;
    protected mixed $value = null;
    protected bool $is_hit = false;
    protected float|int|null $expiry = null;
    protected array $metadata = [];
    protected array $new_metadata = [];
    protected ?Cache_Item_Interface $inner_item = null;
    protected ?string $pool_hash = null;
    protected bool $is_taggable = false;
    /**
     * Returns the key for the current cache item.
     *
     * @return string The cache item's unique key within its pool
     *
     * @since 3.1
     */
    public function get_key(): string
    {
        return $this->key;
    }

    /**
     * Retrieves the value of the item from the cache.
     *
     * Returns null both when the item is a miss AND when null is the cached value.
     * Always check is_hit() to distinguish between a cache miss and a stored null.
     *
     * @return mixed The value stored in the cache, or null on a cache miss
     *
     * @since 3.1
     */
    public function get(): mixed
    {
        return $this->value;
    }

    /**
     * Confirms if the cache item lookup resulted in a cache hit.
     *
     * @return bool True if the request resulted in a cache hit; false on miss or expired
     *
     * @since 3.1
     */
    public function is_hit(): bool
    {
        return $this->is_hit;
    }

    /**
     * Sets the value represented by this cache item.
     *
     * The value must be serializable. Note that not all serializable values are
     * supported by all adapters (e.g. resources cannot be serialized).
     *
     * @param mixed $value The serializable value to be stored
     *
     * @return $this
     *
     * @since 3.1
     */
    public function set($value): static
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Sets the expiration time for this cache item to an absolute DateTime.
     *
     * @param \DateTimeInterface|null $expiration The absolute expiration date; null means the item never expires
     *                                             (or uses the pool's default TTL)
     *
     * @return $this
     *
     * @since 3.1
     */
    public function expires_at(?\DateTimeInterface $expiration): static
    {
        $this->expiry = null !== $expiration ? (float) $expiration->format('U.u') : null;
        return $this;
    }
    /**
     * Sets the expiration time for this cache item as a relative TTL.
     *
     * @param int|\DateInterval|null $time The TTL in seconds, a DateInterval, or null to use the pool's default TTL;
     *                                     passing 0 or a negative integer causes the item to expire immediately
     *
     * @return $this
     *
     * @throws \Symfony\Component\Cache\Exception\InvalidArgumentException When $time is not an int, DateInterval, or null
     *
     * @since 3.1
     */
    public function expires_after(mixed $time): static
    {
        if (null === $time) {
            $this->expiry = null;
        } elseif ($time instanceof \DateInterval) {
            $this->expiry = microtime(true) + \DateTimeImmutable::create_from_format('U', 0)->add($time)->format('U.u');
        } elseif (\is_int($time)) {
            $this->expiry = $time + microtime(true);
        } else {
            throw new InvalidArgumentException(\sprintf('Expiration date must be an integer, a DateInterval or null, "%s" given.', get_debug_type($time)));
        }
        return $this;
    }
    public function tag(mixed $tags): static
    {
        if (!$this->is_taggable) {
            throw new LogicException(\sprintf('Cache item "%s" comes from a non tag-aware pool: you cannot tag it.', $this->key));
        }
        if (!\is_array($tags) && !$tags instanceof \Traversable) {
            // don't use is_iterable(), it's slow
            $tags = [$tags];
        }
        foreach ($tags as $tag) {
            if (!\is_string($tag) && !$tag instanceof \Stringable) {
                throw new InvalidArgumentException(\sprintf('Cache tag must be string or object that implements __toString(), "%s" given.', get_debug_type($tag)));
            }
            $tag = (string) $tag;
            if (isset($this->new_metadata[self::METADATA_TAGS][$tag])) {
                continue;
            }
            if ('' === $tag) {
                throw new InvalidArgumentException('Cache tag length must be greater than zero.');
            }
            if (false !== strpbrk($tag, self::RESERVED_CHARACTERS)) {
                throw new InvalidArgumentException(\sprintf('Cache tag "%s" contains reserved characters "%s".', $tag, self::RESERVED_CHARACTERS));
            }
            $this->new_metadata[self::METADATA_TAGS][$tag] = $tag;
        }
        return $this;
    }
    public function get_metadata(): array
    {
        return $this->metadata;
    }
    /**
     * Validates a cache key according to PSR-6.
     *
     * A valid key is a non-empty string not containing any of the reserved characters
     * defined in self::RESERVED_CHARACTERS ({, }, (, ), /, \, @, :).
     *
     * @param mixed $key The key to validate; must be a non-empty string
     *
     * @return string The validated key (unchanged if valid)
     *
     * @throws InvalidArgumentException When $key is not a string, is empty, or contains reserved characters
     *
     * @since 3.1
     */
    public static function validate_key($key): string
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(\sprintf('Cache key must be string, "%s" given.', get_debug_type($key)));
        }
        if ('' === $key) {
            throw new InvalidArgumentException('Cache key length must be greater than zero.');
        }
        if (false !== strpbrk($key, self::RESERVED_CHARACTERS)) {
            throw new InvalidArgumentException(\sprintf('Cache key "%s" contains reserved characters "%s".', $key, self::RESERVED_CHARACTERS));
        }
        return $key;
    }
    /**
     * Internal logging helper.
     *
     * @internal
     */
    public static function log(?Logger_Interface $logger, string $message, array $context = []): void
    {
        if ($logger) {
            $logger->warning($message, $context);
        } else {
            $replace = [];
            foreach ($context as $k => $v) {
                if (\is_scalar($v)) {
                    $replace['{' . $k . '}'] = $v;
                }
            }
            @trigger_error(strtr($message, $replace), \E_USER_WARNING);
        }
    }
}