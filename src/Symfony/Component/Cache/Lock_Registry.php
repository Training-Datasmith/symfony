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

use Psr\Log\Logger_Interface;
use Symfony\Contracts\Cache\Cache_Interface;
use Symfony\Contracts\Cache\Item_Interface;
/**
 * LockRegistry is used internally by existing adapters to protect against cache stampede.
 *
 * It does so by wrapping the computation of items in a pool of locks.
 * Foreach each apps, there can be at most 20 concurrent processes that
 * compute items at the same time and only one per cache-key.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Lock_Registry
{
    private static array $opened_files = [];
    private static ?array $locked_files = null;
    private static \Exception $signaling_exception;
    private static \Closure $signaling_callback;
    /**
     * The number of items in this list controls the max number of concurrent processes.
     */
    private static array $files = [__DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'AbstractAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'AbstractTagAwareAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'AdapterInterface.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'ApcuAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'ArrayAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'ChainAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'CouchbaseCollectionAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'DoctrineDbalAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'FilesystemAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'FilesystemTagAwareAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'MemcachedAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'NullAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'ParameterNormalizer.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'PdoAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'PhpArrayAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'PhpFilesAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'ProxyAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'Psr16Adapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'RedisAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'RedisTagAwareAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'TagAwareAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'TagAwareAdapterInterface.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'TraceableAdapter.php', __DIR__ . \DIRECTORY_SEPARATOR . 'Adapter' . \DIRECTORY_SEPARATOR . 'TraceableTagAwareAdapter.php'];
    /**
     * Defines a set of existing files that will be used as keys to acquire locks.
     *
     * @return array The previously defined set of files
     */
    public static function set_files(array $files): array
    {
        $previous_files = self::$files;
        self::$files = $files;
        foreach (self::$opened_files as $file) {
            if ($file) {
                flock($file, \LOCK_UN);
                fclose($file);
            }
        }
        self::$opened_files = self::$locked_files = [];
        return $previous_files;
    }
    public static function compute(callable $callback, Item_Interface $item, bool &$save, Cache_Interface $pool, ?\Closure $set_metadata = null, ?Logger_Interface $logger = null, ?float $beta = null): mixed
    {
        if ('\\' === \DIRECTORY_SEPARATOR && null === self::$locked_files) {
            // disable locking on Windows by default
            self::$files = self::$locked_files = [];
        }
        $key = self::$files ? abs(crc32($item->get_key())) % \count(self::$files) : -1;
        if ($key < 0 || self::$locked_files || !$lock = self::open($key)) {
            return $callback($item, $save);
        }
        self::$signaling_exception ??= unserialize("O:9:\"Exception\":1:{s:16:\"\x00Exception\x00trace\";a:0:{}}");
        self::$signaling_callback ??= static fn() => throw self::$signaling_exception;
        while (true) {
            try {
                // race to get the lock in non-blocking mode
                $locked = flock($lock, \LOCK_EX | \LOCK_NB, $would_block);
                if ($locked || !$would_block) {
                    $logger?->info(\sprintf('Lock %s, now computing item "{key}"', $locked ? 'acquired' : 'not supported'), ['key' => $item->get_key()]);
                    self::$locked_files[$key] = true;
                    $value = $callback($item, $save);
                    if ($save) {
                        if ($set_metadata) {
                            $set_metadata($item);
                        }
                        $pool->save($item->set($value));
                        $save = false;
                    }
                    return $value;
                }
                // if we failed the race, retry locking in blocking mode to wait for the winner
                $logger?->info('Item "{key}" is locked, waiting for it to be released', ['key' => $item->get_key()]);
                flock($lock, \LOCK_SH);
                if (\INF === $beta) {
                    $logger?->info('Force-recomputing item "{key}"', ['key' => $item->get_key()]);
                    continue;
                }
            } finally {
                flock($lock, \LOCK_UN);
                unset(self::$locked_files[$key]);
            }
            try {
                $value = $pool->get($item->get_key(), self::$signaling_callback, 0);
                $logger?->info('Item "{key}" retrieved after lock was released', ['key' => $item->get_key()]);
                $save = false;
                return $value;
            } catch (\Exception $e) {
                if (self::$signaling_exception !== $e) {
                    throw $e;
                }
                $logger?->info('Item "{key}" not found while lock was released, now retrying', ['key' => $item->get_key()]);
            }
        }
        return null;
    }
    /**
     * @return resource|false
     */
    private static function open(int $key)
    {
        if (null !== $h = self::$opened_files[$key] ?? null) {
            return $h;
        }
        set_error_handler(static fn(): null => null);
        try {
            $h = fopen(self::$files[$key], 'r+');
        } finally {
            restore_error_handler();
        }
        return self::$opened_files[$key] = $h ?: @fopen(self::$files[$key], 'r');
    }
}