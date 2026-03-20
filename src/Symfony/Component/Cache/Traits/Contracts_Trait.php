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
namespace Symfony\Component\Cache\Traits;

use Psr\Log\Logger_Interface;
use Symfony\Component\Cache\Adapter\Adapter_Interface;
use Symfony\Component\Cache\Cache_Item;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Lock_Registry;
use Symfony\Contracts\Cache\Cache_Interface;
use Symfony\Contracts\Cache\Cache_Trait;
use Symfony\Contracts\Cache\Item_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait Contracts_Trait
{
    use Cache_Trait {
        doGet as private contractsGet;
    }
    private \Closure $callback_wrapper;
    private array $computing = [];
    /**
     * Wraps the callback passed to ->get() in a callable.
     *
     * @return callable the previous callback wrapper
     */
    public function set_callback_wrapper(?callable $callback_wrapper): callable
    {
        if (!isset($this->callback_wrapper)) {
            $this->callback_wrapper = Lock_Registry::compute(...);
            if (\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
                $this->set_callback_wrapper(null);
            }
        }
        if (null !== $callback_wrapper && !$callback_wrapper instanceof \Closure) {
            $callback_wrapper = $callback_wrapper(...);
        }
        $previous_wrapper = $this->callback_wrapper;
        $this->callback_wrapper = $callback_wrapper ?? static fn(callable $callback, Item_Interface $item, bool &$save, Cache_Interface $pool, \Closure $set_metadata, ?Logger_Interface $logger, ?float $beta = null) => $callback($item, $save);
        return $previous_wrapper;
    }
    private function do_get(Adapter_Interface $pool, string $key, callable $callback, ?float $beta, ?array &$metadata = null): mixed
    {
        if (0 > $beta ??= 1.0) {
            throw new InvalidArgumentException(\sprintf('Argument "$beta" provided to "%s::get()" must be a positive number, %f given.', static::class, $beta));
        }
        static $set_metadata;
        $set_metadata ??= \Closure::bind(static function (Cache_Item $item, float $start_time, ?array &$metadata): void {
            if ($item->expiry > $end_time = microtime(true)) {
                $item->new_metadata[Cache_Item::METADATA_EXPIRY] = $metadata[Cache_Item::METADATA_EXPIRY] = $item->expiry;
                $item->new_metadata[Cache_Item::METADATA_CTIME] = $metadata[Cache_Item::METADATA_CTIME] = (int) ceil(1000 * ($end_time - $start_time));
            } else {
                unset($metadata[Cache_Item::METADATA_EXPIRY], $metadata[Cache_Item::METADATA_CTIME], $metadata[Cache_Item::METADATA_TAGS]);
            }
        }, null, Cache_Item::class);
        $this->callback_wrapper ??= Lock_Registry::compute(...);
        return $this->contracts_get($pool, $key, function (Cache_Item $item, bool &$save) use ($pool, $callback, $set_metadata, &$metadata, $key, $beta) {
            // don't wrap nor save recursive calls
            if (isset($this->computing[$key])) {
                $value = $callback($item, $save);
                $save = false;
                return $value;
            }
            $this->computing[$key] = $key;
            $start_time = microtime(true);
            if (!isset($this->callback_wrapper)) {
                $this->set_callback_wrapper($this->set_callback_wrapper(null));
            }
            try {
                $value = ($this->callback_wrapper)($callback, $item, $save, $pool, static function (Cache_Item $item) use ($set_metadata, $start_time, &$metadata): void {
                    $set_metadata($item, $start_time, $metadata);
                }, $this->logger ?? null, $beta);
                $set_metadata($item, $start_time, $metadata);
                return $value;
            } finally {
                unset($this->computing[$key]);
            }
        }, $beta, $metadata, $this->logger ?? null);
    }
}