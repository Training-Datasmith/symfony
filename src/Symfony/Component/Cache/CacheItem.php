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
 * @author Nicolas Grekas <p@tchwork.com>
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
    public function get_key(): string
    {
        return $this->key;
    }
    public function get(): mixed
    {
        return $this->value;
    }
    public function is_hit(): bool
    {
        return $this->is_hit;
    }
    /**
     * @return $this
     */
    public function set($value): static
    {
        $this->value = $value;
        return $this;
    }
    /**
     * @return $this
     */
    public function expires_at(?\DateTimeInterface $expiration): static
    {
        $this->expiry = null !== $expiration ? (float) $expiration->format('U.u') : null;
        return $this;
    }
    /**
     * @return $this
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
     * @param mixed $key The key to validate
     *
     * @throws InvalidArgumentException When $key is not valid
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