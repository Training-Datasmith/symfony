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
namespace Symfony\Component\Cache\Adapter;

use Psr\Log\Logger_Aware_Interface;
use Psr\Log\Logger_Interface;
use Symfony\Component\Cache\Cache_Item;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Resettable_Interface;
use Symfony\Component\Cache\Traits\Abstract_Adapter_Trait;
use Symfony\Component\Cache\Traits\Contracts_Trait;
use Symfony\Contracts\Cache\Cache_Interface;
use Symfony\Contracts\Cache\Namespaced_Pool_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class Abstract_Adapter implements Adapter_Interface, Cache_Interface, Namespaced_Pool_Interface, Logger_Aware_Interface, Resettable_Interface
{
    use Abstract_Adapter_Trait;
    use Contracts_Trait;
    /**
     * @internal
     */
    protected const NS_SEPARATOR = ':';
    private static bool $apcu_supported;
    protected function __construct(string $namespace = '', int $default_lifetime = 0)
    {
        if ('' !== $namespace) {
            if (str_contains($namespace, (string) static::NS_SEPARATOR)) {
                if (str_contains($namespace, static::NS_SEPARATOR . static::NS_SEPARATOR)) {
                    throw new InvalidArgumentException(\sprintf('Cache namespace "%s" contains empty sub-namespace.', $namespace));
                }
                Cache_Item::validate_key(str_replace(static::NS_SEPARATOR, '', $namespace));
            } else {
                Cache_Item::validate_key($namespace);
            }
            $this->namespace = $namespace . static::NS_SEPARATOR;
        }
        $this->root_namespace = $this->namespace;
        $this->default_lifetime = $default_lifetime;
        if (null !== $this->max_id_length && \strlen($namespace) > $this->max_id_length - 24) {
            throw new InvalidArgumentException(\sprintf('Namespace must be %d chars max, %d given ("%s").', $this->max_id_length - 24, \strlen($namespace), $namespace));
        }
        self::$create_cache_item ??= \Closure::bind(static function ($key, $value, $is_hit): \Symfony\Component\Cache\Cache_Item {
            $item = new Cache_Item();
            $item->key = $key;
            $item->value = $value;
            $item->is_hit = $is_hit;
            $item->unpack();
            return $item;
        }, null, Cache_Item::class);
        self::$merge_by_lifetime ??= \Closure::bind(static function ($deferred, $namespace, &$expired_ids, $get_id, $default_lifetime): array {
            $by_lifetime = [];
            $now = microtime(true);
            $expired_ids = [];
            foreach ($deferred as $key => $item) {
                $key = (string) $key;
                if (null === $item->expiry) {
                    $ttl = 0 < $default_lifetime ? $default_lifetime : 0;
                } elseif (!$item->expiry) {
                    $ttl = 0;
                } elseif (0 >= $ttl = (int) (0.1 + $item->expiry - $now)) {
                    $expired_ids[] = $get_id($key);
                    continue;
                }
                $by_lifetime[$ttl][$get_id($key)] = $item->pack();
            }
            return $by_lifetime;
        }, null, Cache_Item::class);
    }
    /**
     * Returns the best possible adapter that your runtime supports.
     *
     * Using ApcuAdapter makes system caches compatible with read-only filesystems.
     */
    public static function create_system_cache(string $namespace, int $default_lifetime, string $version, string $directory, ?Logger_Interface $logger = null): Adapter_Interface
    {
        $opcache = new Php_Files_Adapter($namespace, $default_lifetime, $directory, true);
        if (null !== $logger) {
            $opcache->set_logger($logger);
        }
        if (!self::$apcu_supported ??= Apcu_Adapter::is_supported()) {
            return $opcache;
        }
        if ('cli' === \PHP_SAPI && !filter_var(\ini_get('apc.enable_cli'), \FILTER_VALIDATE_BOOL)) {
            return $opcache;
        }
        $apcu = new Apcu_Adapter($namespace, intdiv($default_lifetime, 5), $version);
        if (null !== $logger) {
            $apcu->set_logger($logger);
        }
        return new Chain_Adapter([$apcu, $opcache]);
    }
    public static function create_connection(
        #[\Sensitive_Parameter]
        string $dsn,
        array $options = []
    ): mixed
    {
        if (str_starts_with($dsn, 'redis:') || str_starts_with($dsn, 'rediss:') || str_starts_with($dsn, 'valkey:') || str_starts_with($dsn, 'valkeys:')) {
            return Redis_Adapter::create_connection($dsn, $options);
        }
        if (str_starts_with($dsn, 'memcached:')) {
            return Memcached_Adapter::create_connection($dsn, $options);
        }
        if (str_starts_with($dsn, 'couchbase:')) {
            return Couchbase_Collection_Adapter::create_connection($dsn, $options);
        }
        if (preg_match('/^(mysql|oci|pgsql|sqlsrv|sqlite):/', $dsn)) {
            return Pdo_Adapter::create_connection($dsn, $options);
        }
        throw new InvalidArgumentException('Unsupported DSN: it does not start with "redis[s]:", "valkey[s]:", "memcached:", "couchbase:", "mysql:", "oci:", "pgsql:", "sqlsrv:" nor "sqlite:".');
    }
    public function commit(): bool
    {
        $ok = true;
        $by_lifetime = (self::$merge_by_lifetime)($this->deferred, $this->namespace, $expired_ids, $this->get_id(...), $this->default_lifetime);
        $retry = $this->deferred = [];
        if ($expired_ids) {
            try {
                $this->do_delete($expired_ids);
            } catch (\Exception $e) {
                $ok = false;
                Cache_Item::log($this->logger, 'Failed to delete expired items: ' . $e->get_message(), ['exception' => $e, 'cache-adapter' => get_debug_type($this)]);
            }
        }
        foreach ($by_lifetime as $lifetime => $values) {
            try {
                $e = $this->do_save($values, $lifetime);
            } catch (\Exception $e) {
            }
            if (true === $e) {
                continue;
            }
            if ([] === $e) {
                continue;
            }
            if (\is_array($e) || 1 === \count($values)) {
                foreach (\is_array($e) ? $e : array_keys($values) as $id) {
                    $ok = false;
                    $v = $values[$id];
                    $type = get_debug_type($v);
                    $message = \sprintf('Failed to save key "{key}" of type %s%s', $type, $e instanceof \Exception ? ': ' . $e->get_message() : '.');
                    Cache_Item::log($this->logger, $message, ['key' => substr((string) $id, \strlen($this->root_namespace)), 'exception' => $e instanceof \Exception ? $e : null, 'cache-adapter' => get_debug_type($this)]);
                }
            } else {
                foreach ($values as $id => $v) {
                    $retry[$lifetime][] = $id;
                }
            }
        }
        // When bulk-save failed, retry each item individually
        foreach ($retry as $lifetime => $ids) {
            foreach ($ids as $id) {
                try {
                    $v = $by_lifetime[$lifetime][$id];
                    $e = $this->do_save([$id => $v], $lifetime);
                } catch (\Exception $e) {
                }
                if (true === $e) {
                    continue;
                }
                if ([] === $e) {
                    continue;
                }
                $ok = false;
                $type = get_debug_type($v);
                $message = \sprintf('Failed to save key "{key}" of type %s%s', $type, $e instanceof \Exception ? ': ' . $e->get_message() : '.');
                Cache_Item::log($this->logger, $message, ['key' => substr((string) $id, \strlen($this->root_namespace)), 'exception' => $e instanceof \Exception ? $e : null, 'cache-adapter' => get_debug_type($this)]);
            }
        }
        return $ok;
    }
}