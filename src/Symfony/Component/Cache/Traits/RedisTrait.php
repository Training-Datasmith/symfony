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

use Predis\Command\Redis\UNLINK;
use Predis\Connection\Aggregate\Cluster_Interface;
use Predis\Connection\Aggregate\Redis_Cluster;
use Predis\Connection\Aggregate\Replication_Interface;
use Predis\Connection\Cluster\Cluster_Interface as Predis2ClusterInterface;
use Predis\Connection\Cluster\Redis_Cluster as Predis2RedisCluster;
use Predis\Connection\Replication\Replication_Interface as Predis2ReplicationInterface;
use Predis\Response\Error_Interface;
use Predis\Response\Status;
use Relay\Cluster as RelayCluster;
use Relay\Relay;
use Relay\Sentinel;
use Symfony\Component\Cache\Exception\Cache_Exception;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\Default_Marshaller;
use Symfony\Component\Cache\Marshaller\Marshaller_Interface;
/**
 * @author Aurimas Niekis <aurimas@niekis.lt>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait Redis_Trait
{
    private static array $default_connection_options = ['class' => null, 'auth' => null, 'persistent' => false, 'persistent_id' => null, 'timeout' => 30, 'read_timeout' => 0, 'retry_interval' => 0, 'tcp_keepalive' => 0, 'lazy' => null, 'cluster' => false, 'cluster_command_timeout' => 0, 'cluster_relay_context' => [], 'sentinel' => null, 'dbindex' => 0, 'failover' => 'none', 'ssl' => null];
    private \Redis|Relay|Relay_Cluster|\Redis_Array|\Redis_Cluster|\Predis\Client_Interface $redis;
    private Marshaller_Interface $marshaller;
    private function init(\Redis|Relay|Relay_Cluster|\Redis_Array|\Redis_Cluster|\Predis\Client_Interface $redis, string $namespace, int $default_lifetime, ?Marshaller_Interface $marshaller): void
    {
        parent::__construct($namespace, $default_lifetime);
        if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(\sprintf('RedisAdapter namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
        }
        if ($redis instanceof \Predis\Client_Interface && $redis->get_options()->exceptions) {
            $options = clone $redis->get_options();
            \Closure::bind(function (): void {
                $this->options['exceptions'] = false;
            }, $options, $options)();
            $redis = new $redis($redis->get_connection(), $options);
        }
        $this->redis = $redis;
        $this->marshaller = $marshaller ?? new Default_Marshaller();
    }
    /**
     * Creates a Redis connection using a DSN configuration.
     *
     * Example DSN:
     *   - redis://localhost
     *   - redis://example.com:1234
     *   - redis://secret@example.com/13
     *   - redis:///var/run/redis.sock
     *   - redis://secret@/var/run/redis.sock/13
     *
     * @param array $options See self::$defaultConnectionOptions
     *
     * @throws InvalidArgumentException when the DSN is invalid
     */
    public static function create_connection(
        #[\Sensitive_Parameter]
        string $dsn,
        array $options = []
    ): \Redis|\Redis_Array|\Redis_Cluster|\Predis\Client_Interface|Relay|Relay_Cluster
    {
        $scheme = match (true) {
            str_starts_with($dsn, 'redis:') => 'redis',
            str_starts_with($dsn, 'rediss:') => 'rediss',
            str_starts_with($dsn, 'valkey:') => 'valkey',
            str_starts_with($dsn, 'valkeys:') => 'valkeys',
            default => throw new InvalidArgumentException('Invalid Redis DSN: it does not start with "redis[s]:" nor "valkey[s]:".'),
        };
        if (!\extension_loaded('redis') && !\extension_loaded('relay') && !class_exists(\Predis\Client::class)) {
            throw new Cache_Exception('Cannot find the "redis" extension nor the "relay" extension nor the "predis/predis" package.');
        }
        $auth = null;
        $params = preg_replace_callback('#^' . $scheme . ':(//)?(?:(?:(?<user>[^:@]*+):)?(?<password>[^@]*+)@)?#', static function (array $m) use (&$auth): string {
            if (isset($m['password'])) {
                if (\in_array($m['user'], ['', 'default'], true)) {
                    $auth = rawurldecode((string) $m['password']);
                } else {
                    $auth = [rawurldecode((string) $m['user']), rawurldecode((string) $m['password'])];
                }
                if ('' === $auth) {
                    $auth = null;
                }
            }
            return 'file:' . ($m[1] ?? '');
        }, $dsn);
        if (false === $params = parse_url((string) $params)) {
            throw new InvalidArgumentException('Invalid Redis DSN.');
        }
        $query = $hosts = [];
        $tls = 'rediss' === $scheme || 'valkeys' === $scheme;
        $tcp_scheme = $tls ? 'tls' : 'tcp';
        if (isset($params['query'])) {
            parse_str($params['query'], $query);
            if (isset($query['host'])) {
                if (!\is_array($hosts = $query['host'])) {
                    throw new InvalidArgumentException('Invalid Redis DSN: query parameter "host" must be an array.');
                }
                foreach ($hosts as $host => $parameters) {
                    if (\is_string($parameters)) {
                        parse_str($parameters, $parameters);
                    }
                    if (false === $i = strrpos((string) $host, ':')) {
                        $hosts[$host] = ['scheme' => $tcp_scheme, 'host' => $host, 'port' => 6379] + $parameters;
                    } elseif ($port = (int) substr((string) $host, 1 + $i)) {
                        $hosts[$host] = ['scheme' => $tcp_scheme, 'host' => substr((string) $host, 0, $i), 'port' => $port] + $parameters;
                    } else {
                        $hosts[$host] = ['scheme' => 'unix', 'path' => substr((string) $host, 0, $i)] + $parameters;
                    }
                }
                $hosts = array_values($hosts);
            }
        }
        if (isset($params['host']) || isset($params['path'])) {
            if (!isset($params['dbindex']) && isset($params['path'])) {
                if (preg_match('#/(\d+)?$#', $params['path'], $m)) {
                    $params['dbindex'] = $m[1] ?? $query['dbindex'] ?? '0';
                    $params['path'] = substr($params['path'], 0, -\strlen($m[0]));
                } elseif (isset($params['host'])) {
                    throw new InvalidArgumentException('Invalid Redis DSN: parameter "dbindex" must be a number.');
                }
            }
            if (isset($params['host'])) {
                array_unshift($hosts, ['scheme' => $tcp_scheme, 'host' => $params['host'], 'port' => $params['port'] ?? 6379]);
            } else {
                array_unshift($hosts, ['scheme' => 'unix', 'path' => $params['path']]);
            }
        }
        if (!$hosts) {
            throw new InvalidArgumentException('Invalid Redis DSN: missing host.');
        }
        if (isset($params['dbindex'], $query['dbindex']) && $params['dbindex'] !== $query['dbindex']) {
            throw new InvalidArgumentException('Invalid Redis DSN: path and query "dbindex" parameters mismatch.');
        }
        $params += $query + $options + self::$default_connection_options;
        $boolean_stream_options = ['allow_self_signed', 'capture_peer_cert', 'capture_peer_cert_chain', 'disable_compression', 'SNI_enabled', 'verify_peer', 'verify_peer_name'];
        foreach ($params['ssl'] ?? [] as $stream_option => $value) {
            if (\in_array($stream_option, $boolean_stream_options, true) && \is_string($value)) {
                $params['ssl'][$stream_option] = filter_var($value, \FILTER_VALIDATE_BOOL);
            }
        }
        $aliases = ['sentinel_master' => 'sentinel', 'redis_sentinel' => 'sentinel', 'redis_cluster' => 'cluster'];
        foreach ($aliases as $alias => $key) {
            $params[$key] = match (true) {
                \array_key_exists($key, $query) => $query[$key],
                \array_key_exists($alias, $query) => $query[$alias],
                \array_key_exists($key, $options) => $options[$key],
                \array_key_exists($alias, $options) => $options[$alias],
                default => $params[$key],
            };
        }
        if (!isset($params['sentinel'])) {
            $params['auth'] ??= $auth;
            $sentinel_auth = null;
        } elseif (!class_exists(\Predis\Client::class) && !class_exists(\Redis_Sentinel::class) && !class_exists(Sentinel::class)) {
            throw new Cache_Exception('Redis Sentinel support requires one of: "predis/predis", "ext-redis >= 6.1", "ext-relay".');
        } else {
            $sentinel_auth = $params['auth'] ?? null;
            $params['auth'] = $auth ?? $params['auth'];
        }
        foreach (['lazy', 'persistent', 'cluster'] as $option) {
            if (!\is_bool($params[$option] ?? false)) {
                $params[$option] = filter_var($params[$option], \FILTER_VALIDATE_BOOLEAN);
            }
        }
        if ($params['cluster'] && isset($params['sentinel'])) {
            throw new InvalidArgumentException('Cannot use both "cluster" and "sentinel" at the same time.');
        }
        $class = $params['class'] ?? match (true) {
            $params['cluster'] => match (true) {
                \extension_loaded('redis') => \Redis_Cluster::class,
                \extension_loaded('relay') => Relay_Cluster::class,
                default => \Predis\Client::class,
            },
            isset($params['sentinel']) => match (true) {
                \extension_loaded('redis') => \Redis::class,
                \extension_loaded('relay') => Relay::class,
                default => \Predis\Client::class,
            },
            1 < \count($hosts) && \extension_loaded('redis') => \Redis_Array::class,
            \extension_loaded('redis') => \Redis::class,
            \extension_loaded('relay') => Relay::class,
            default => \Predis\Client::class,
        };
        if (isset($params['sentinel']) && !is_a($class, \Predis\Client::class, true) && !class_exists(\Redis_Sentinel::class) && !class_exists(Sentinel::class)) {
            throw new Cache_Exception(\sprintf('Cannot use Redis Sentinel: class "%s" does not extend "Predis\Client" and neither ext-redis >= 6.1 nor ext-relay have been found.', $class));
        }
        $is_redis_ext = is_a($class, \Redis::class, true);
        $is_relay_ext = !$is_redis_ext && is_a($class, Relay::class, true);
        if ($is_redis_ext || $is_relay_ext) {
            $connect = $params['persistent'] || $params['persistent_id'] ? 'pconnect' : 'connect';
            $initializer = static function () use ($class, $is_redis_ext, $connect, $params, $sentinel_auth, $hosts, $tls): object {
                $sentinel_class = $is_redis_ext ? \Redis_Sentinel::class : Sentinel::class;
                $redis = new $class();
                $host_index = 0;
                do {
                    $host = $hosts[$host_index]['host'] ?? $hosts[$host_index]['path'];
                    $port = $hosts[$host_index]['port'] ?? 0;
                    $pass_auth = null !== $sentinel_auth && (!$is_redis_ext || \defined('Redis::OPT_NULL_MULTIBULK_AS_NULL'));
                    $address = false;
                    if (isset($hosts[$host_index]['host']) && $tls) {
                        $host = 'tls://' . $host;
                    }
                    if (!isset($params['sentinel'])) {
                        break;
                    }
                    try {
                        if ($is_redis_ext) {
                            $options = ['host' => $host, 'port' => $port, 'connectTimeout' => (float) $params['timeout'], 'persistent' => $params['persistent_id'], 'retryInterval' => (int) $params['retry_interval'], 'readTimeout' => (float) $params['read_timeout']];
                            if ($pass_auth) {
                                $options['auth'] = $sentinel_auth;
                            }
                            if (null !== $params['ssl'] && version_compare(phpversion('redis'), '6.2.0', '>=')) {
                                $options['ssl'] = $params['ssl'];
                            }
                            $sentinel = new \Redis_Sentinel($options);
                        } else {
                            $extra = $pass_auth ? [$sentinel_auth] : [];
                            $sentinel = @new $sentinel_class($host, $port, $params['timeout'], (string) $params['persistent_id'], $params['retry_interval'], $params['read_timeout'], ...$extra);
                        }
                        if ($address = @$sentinel->get_master_addr_by_name($params['sentinel'])) {
                            [$host, $port] = $address;
                        }
                    } catch (\Redis_Exception|\Relay\Exception) {
                    }
                } while (++$host_index < \count($hosts) && !$address);
                if (isset($params['sentinel']) && !$address) {
                    throw new InvalidArgumentException(\sprintf('Failed to retrieve master information from sentinel "%s".', $params['sentinel']), previous: $redis_exception ?? null);
                }
                try {
                    $extra = ['stream' => self::filter_ssl_options($params['ssl'] ?? []) ?: null];
                    if (null !== $params['auth']) {
                        $extra['auth'] = $params['auth'];
                    }
                    @$redis->{$connect}($host, $port, (float) $params['timeout'], (string) $params['persistent_id'], $params['retry_interval'], $params['read_timeout'], ...\defined('Redis::SCAN_PREFIX') || !$is_redis_ext ? [$extra] : []);
                    set_error_handler(static function ($type, $msg) use (&$error): void {
                        $error = $msg;
                    });
                    try {
                        $is_connected = $redis->is_connected();
                    } finally {
                        restore_error_handler();
                    }
                    if (!$is_connected) {
                        $error = preg_match('/^Redis::p?connect\(\): (.*)/', $error ?? $redis->get_last_error() ?? '', $error) ? \sprintf(' (%s)', $error[1]) : '';
                        throw new InvalidArgumentException('Redis connection failed: ' . $error . '.');
                    }
                    if (0 < $params['tcp_keepalive'] && (!$is_redis_ext || \defined('Redis::OPT_TCP_KEEPALIVE'))) {
                        $redis->set_option($is_redis_ext ? \Redis::OPT_TCP_KEEPALIVE : Relay::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
                    }
                    if (!$redis->select($params['dbindex'])) {
                        $e = preg_replace('/^ERR /', '', $redis->get_last_error());
                        throw new InvalidArgumentException('Redis connection failed: ' . $e . '.');
                    }
                } catch (\Redis_Exception|\Relay\Exception $e) {
                    throw new InvalidArgumentException('Redis connection failed: ' . $e->get_message());
                }
                return $redis;
            };
            if ($params['lazy']) {
                $redis = $is_redis_ext ? Redis_Proxy::create_lazy_proxy($initializer) : Relay_Proxy::create_lazy_proxy($initializer);
            } else {
                $redis = $initializer();
            }
        } elseif (is_a($class, \Redis_Array::class, true)) {
            foreach ($hosts as $i => $host) {
                $hosts[$i] = match ($host['scheme']) {
                    'tcp' => $host['host'] . ':' . $host['port'],
                    'tls' => 'tls://' . $host['host'] . ':' . $host['port'],
                    default => $host['path'],
                };
            }
            $params['lazy_connect'] = $params['lazy'] ?? true;
            $params['connect_timeout'] = $params['timeout'];
            try {
                $redis = new $class($hosts, $params);
            } catch (\Redis_Cluster_Exception $e) {
                throw new InvalidArgumentException('Redis connection failed: ' . $e->get_message());
            }
            if (0 < $params['tcp_keepalive'] && (!$is_redis_ext || \defined('Redis::OPT_TCP_KEEPALIVE'))) {
                $redis->set_option($is_redis_ext ? \Redis::OPT_TCP_KEEPALIVE : Relay::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
            }
        } elseif (is_a($class, Relay_Cluster::class, true)) {
            $initializer = static function () use ($class, $params, $hosts): \Relay\Cluster {
                foreach ($hosts as $i => $host) {
                    $hosts[$i] = match ($host['scheme']) {
                        'tcp' => $host['host'] . ':' . $host['port'],
                        'tls' => 'tls://' . $host['host'] . ':' . $host['port'],
                        default => $host['path'],
                    };
                }
                try {
                    $context = $params['cluster_relay_context'];
                    $context['stream'] = self::filter_ssl_options($params['ssl'] ?? []) ?: null;
                    foreach ($context as $name => $value) {
                        match ($name) {
                            'use-cache', 'client-tracking', 'throw-on-error', 'client-invalidations', 'reply-literal', 'persistent' => $context[$name] = filter_var($value, \FILTER_VALIDATE_BOOLEAN),
                            'max-retries', 'serializer', 'compression', 'compression-level' => $context[$name] = filter_var($value, \FILTER_VALIDATE_INT),
                            default => null,
                        };
                    }
                    $relay_cluster = new $class(name: null, seeds: $hosts, connect_timeout: $params['timeout'], command_timeout: $params['cluster_command_timeout'], persistent: $params['persistent'], auth: $params['auth'] ?? null, context: $context);
                } catch (\Relay\Exception $e) {
                    throw new InvalidArgumentException('Relay cluster connection failed: ' . $e->get_message());
                }
                if (0 < $params['tcp_keepalive']) {
                    $relay_cluster->set_option(Relay::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
                }
                if (0 < $params['read_timeout']) {
                    $relay_cluster->set_option(Relay::OPT_READ_TIMEOUT, $params['read_timeout']);
                }
                return $relay_cluster;
            };
            $redis = $params['lazy'] ? Relay_Cluster_Proxy::create_lazy_proxy($initializer) : $initializer();
        } elseif (is_a($class, \Redis_Cluster::class, true)) {
            $initializer = static function () use ($is_redis_ext, $class, $params, $hosts): \Redis_Cluster {
                foreach ($hosts as $i => $host) {
                    $hosts[$i] = match ($host['scheme']) {
                        'tcp' => $host['host'] . ':' . $host['port'],
                        'tls' => 'tls://' . $host['host'] . ':' . $host['port'],
                        default => $host['path'],
                    };
                }
                try {
                    $redis = new $class(null, $hosts, $params['timeout'], $params['read_timeout'], $params['persistent'], $params['auth'] ?? '', ...\defined('Redis::SCAN_PREFIX') ? [$params['ssl'] ?? null] : []);
                } catch (\Redis_Cluster_Exception $e) {
                    throw new InvalidArgumentException('Redis connection failed: ' . $e->get_message());
                }
                if (0 < $params['tcp_keepalive'] && (!$is_redis_ext || \defined('Redis::OPT_TCP_KEEPALIVE'))) {
                    $redis->set_option($is_redis_ext ? \Redis::OPT_TCP_KEEPALIVE : Relay::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
                }
                $redis->set_option(\Redis_Cluster::OPT_SLAVE_FAILOVER, match ($params['failover']) {
                    'error' => \Redis_Cluster::FAILOVER_ERROR,
                    'distribute' => \Redis_Cluster::FAILOVER_DISTRIBUTE,
                    'slaves' => \Redis_Cluster::FAILOVER_DISTRIBUTE_SLAVES,
                    'none' => \Redis_Cluster::FAILOVER_NONE,
                });
                return $redis;
            };
            $redis = $params['lazy'] ? Redis_Cluster_Proxy::create_lazy_proxy($initializer) : $initializer();
        } elseif (is_a($class, \Predis\Client_Interface::class, true)) {
            if ($params['cluster']) {
                $params['cluster'] = 'redis';
            } else {
                unset($params['cluster']);
            }
            if (isset($params['sentinel'])) {
                $params['replication'] = 'sentinel';
                $params['service'] = $params['sentinel'];
            }
            $params += ['parameters' => []];
            $params['parameters'] += ['persistent' => $params['persistent'], 'timeout' => $params['timeout'], 'read_write_timeout' => $params['read_timeout'], 'tcp_nodelay' => true];
            if ($params['dbindex']) {
                $params['parameters']['database'] = $params['dbindex'];
            }
            if (\is_array($params['auth'])) {
                // ACL
                $params['parameters']['username'] = $params['auth'][0];
                $params['parameters']['password'] = $params['auth'][1];
            } elseif (null !== $params['auth']) {
                $params['parameters']['password'] = $params['auth'];
            }
            if (isset($params['sentinel']) && null !== $sentinel_auth) {
                if (\is_array($sentinel_auth)) {
                    $sentinel_username = $sentinel_auth[0];
                    $sentinel_password = $sentinel_auth[1];
                } else {
                    $sentinel_username = null;
                    $sentinel_password = $sentinel_auth;
                }
                foreach ($hosts as $i => $host) {
                    $hosts[$i]['password'] ??= $sentinel_password;
                    if (null !== $sentinel_username) {
                        $hosts[$i]['username'] ??= $sentinel_username;
                    }
                }
            }
            if (isset($params['ssl'])) {
                foreach ($hosts as $i => $host) {
                    $hosts[$i]['ssl'] ??= $params['ssl'];
                }
            }
            if (1 === \count($hosts) && !isset($params['cluster']) & !isset($params['sentinel'])) {
                $hosts = $hosts[0];
            } elseif (\in_array($params['failover'], ['slaves', 'distribute'], true) && !isset($params['replication'])) {
                $params['replication'] = true;
                $hosts[0] += ['alias' => 'master'];
            }
            $params['exceptions'] = false;
            $redis = new $class($hosts, array_diff_key($params, array_diff_key(self::$default_connection_options, ['cluster' => null])));
            if (isset($params['sentinel'])) {
                $redis->get_connection()->set_sentinel_timeout($params['timeout']);
            }
        } elseif (class_exists($class, false)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a subclass of "Redis", "RedisArray", "RedisCluster", "Relay\Relay" nor "Predis\ClientInterface".', $class));
        } else {
            throw new InvalidArgumentException(\sprintf('Class "%s" does not exist.', $class));
        }
        return $redis;
    }
    protected function do_fetch(array $ids): iterable
    {
        if (!$ids) {
            return [];
        }
        $result = [];
        if ($this->redis instanceof \Predis\Client_Interface && ($this->redis->get_connection() instanceof Cluster_Interface || $this->redis->get_connection() instanceof Predis2cluster_Interface) || $this->redis instanceof Relay_Cluster) {
            $values = $this->pipeline(static function () use ($ids) {
                foreach ($ids as $id) {
                    yield 'get' => [$id];
                }
            });
        } else {
            $values = $this->redis->mget($ids);
            if (!\is_array($values) || \count($values) !== \count($ids)) {
                return [];
            }
            $values = array_combine($ids, $values);
        }
        foreach ($values as $id => $v) {
            if ($v) {
                $result[$id] = $this->marshaller->unmarshall($v);
            }
        }
        return $result;
    }
    protected function do_have(string $id): bool
    {
        return (bool) $this->redis->exists($id);
    }
    protected function do_clear(string $namespace): bool
    {
        if ($this->redis instanceof \Predis\Client_Interface) {
            $prefix = $this->redis->get_options()->prefix ? $this->redis->get_options()->prefix->get_prefix() : '';
            $prefix_len = \strlen($prefix ?? '');
        }
        $cleared = true;
        if ($this->redis instanceof Relay_Cluster) {
            $prefix = Relay::SCAN_PREFIX & $this->redis->get_option(Relay::OPT_SCAN) ? '' : $this->redis->get_option(Relay::OPT_PREFIX);
            $prefix_len = \strlen((string) $prefix);
            $pattern = $prefix . $namespace . '*';
            foreach ($this->redis->_masters() as $ip_and_port) {
                $address = implode(':', $ip_and_port);
                $cursor = null;
                do {
                    $keys = $this->redis->scan($cursor, $address, $pattern, 1000);
                    if (isset($keys[1]) && \is_array($keys[1])) {
                        $cursor = $keys[0];
                        $keys = $keys[1];
                    }
                    if ($keys) {
                        if ($prefix_len) {
                            foreach ($keys as $i => $key) {
                                $keys[$i] = substr((string) $key, $prefix_len);
                            }
                        }
                        $this->do_delete($keys);
                    }
                } while ($cursor);
            }
            return $cleared;
        }
        $hosts = $this->get_hosts();
        $host = reset($hosts);
        if ($host instanceof \Predis\Client) {
            $connection = $host->get_connection();
            if ($connection instanceof Replication_Interface) {
                $hosts = [$host->get_client_for('master')];
            } elseif ($connection instanceof Predis2replication_Interface) {
                $connection->switch_to_master();
                $hosts = [$host];
            }
        }
        foreach ($hosts as $host) {
            if (!isset($namespace[0])) {
                $cleared = $host->flush_db() && $cleared;
                continue;
            }
            $info = $host->info('Server');
            $info = !$info instanceof Error_Interface ? $info['Server'] ?? $info : ['redis_version' => '2.0'];
            if ($host instanceof Relay) {
                $prefix = Relay::SCAN_PREFIX & $host->get_option(Relay::OPT_SCAN) ? '' : $host->get_option(Relay::OPT_PREFIX);
                $prefix_len = \strlen($host->get_option(Relay::OPT_PREFIX) ?? '');
            } elseif (!$host instanceof \Predis\Client_Interface) {
                $prefix = \defined('Redis::SCAN_PREFIX') && \Redis::SCAN_PREFIX & $host->get_option(\Redis::OPT_SCAN) ? '' : $host->get_option(\Redis::OPT_PREFIX);
                $prefix_len = \strlen($host->get_option(\Redis::OPT_PREFIX) ?? '');
            }
            $pattern = $prefix . $namespace . '*';
            if (!version_compare($info['redis_version'], '2.8', '>=')) {
                // As documented in Redis documentation (http://redis.io/commands/keys) using KEYS
                // can hang your server when it is executed against large databases (millions of items).
                // Whenever you hit this scale, you should really consider upgrading to Redis 2.8 or above.
                $unlink = version_compare($info['redis_version'], '4.0', '>=') ? 'UNLINK' : 'DEL';
                $args = $this->redis instanceof \Predis\Client_Interface ? [0, $pattern] : [[$pattern], 0];
                $cleared = $host->eval("local keys=redis.call('KEYS',ARGV[1]) for i=1,#keys,5000 do redis.call('{$unlink}',unpack(keys,i,math.min(i+4999,#keys))) end return 1", $args[0], $args[1]) && $cleared;
                continue;
            }
            $cursor = null;
            do {
                $keys = $host instanceof \Predis\Client_Interface ? $host->scan($cursor ?? 0, 'MATCH', $pattern, 'COUNT', 1000) : $host->scan($cursor, $pattern, 1000);
                if (isset($keys[1]) && \is_array($keys[1])) {
                    $cursor = $keys[0];
                    $keys = $keys[1];
                }
                if ($keys) {
                    if ($prefix_len) {
                        foreach ($keys as $i => $key) {
                            $keys[$i] = substr($key, $prefix_len);
                        }
                    }
                    $this->do_delete($keys);
                }
            } while ($cursor);
        }
        return $cleared;
    }
    protected function do_delete(array $ids): bool
    {
        if (!$ids) {
            return true;
        }
        if ($this->redis instanceof \Predis\Client_Interface && ($this->redis->get_connection() instanceof Cluster_Interface || $this->redis->get_connection() instanceof Predis2cluster_Interface)) {
            static $del;
            $del ??= class_exists(UNLINK::class) ? 'unlink' : 'del';
            $this->pipeline(static function () use ($ids, $del) {
                foreach ($ids as $id) {
                    yield $del => [$id];
                }
            })->rewind();
        } else {
            static $unlink = true;
            if ($unlink) {
                try {
                    $unlink = false !== $this->redis->unlink($ids);
                } catch (\Throwable) {
                    $unlink = false;
                }
            }
            if (!$unlink) {
                $this->redis->del($ids);
            }
        }
        return true;
    }
    protected function do_save(array $values, int $lifetime): array|bool
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }
        $results = $this->pipeline(static function () use ($values, $lifetime) {
            foreach ($values as $id => $value) {
                if (0 >= $lifetime) {
                    yield 'set' => [$id, $value];
                } else {
                    yield 'setEx' => [$id, $lifetime, $value];
                }
            }
        });
        foreach ($results as $id => $result) {
            if (true !== $result && (!$result instanceof Status || Status::get('OK') !== $result)) {
                $failed[] = $id;
            }
        }
        return $failed;
    }
    private function pipeline(\Closure $generator, ?object $redis = null): \Generator
    {
        $ids = [];
        $redis ??= $this->redis;
        if ($redis instanceof \Redis_Cluster || $redis instanceof Relay_Cluster || $redis instanceof \Predis\Client_Interface && ($redis->get_connection() instanceof Redis_Cluster || $redis->get_connection() instanceof Predis2redis_Cluster)) {
            // phpredis & predis don't support pipelining with RedisCluster
            // \Relay\Cluster does not support multi with pipeline mode
            // see https://github.com/phpredis/phpredis/blob/develop/cluster.markdown#pipelining
            // see https://github.com/nrk/predis/issues/267#issuecomment-123781423
            $results = [];
            foreach ($generator() as $command => $args) {
                $results[] = $redis->{$command}(...$args);
                $ids[] = 'eval' === $command ? $redis instanceof \Predis\Client_Interface ? $args[2] : $args[1][0] : $args[0];
            }
        } elseif ($redis instanceof \Predis\Client_Interface) {
            $results = $redis->pipeline(static function ($redis) use ($generator, &$ids): void {
                foreach ($generator() as $command => $args) {
                    $redis->{$command}(...$args);
                    $ids[] = 'eval' === $command ? $args[2] : $args[0];
                }
            });
        } elseif ($redis instanceof \Redis_Array) {
            $connections = $results = [];
            foreach ($generator() as $command => $args) {
                $id = 'eval' === $command ? $args[1][0] : $args[0];
                if (!isset($connections[$h = $redis->_target($id)])) {
                    $connections[$h] = [$redis->_instance($h), -1];
                    $connections[$h][0]->multi(\Redis::PIPELINE);
                }
                $connections[$h][0]->{$command}(...$args);
                $results[] = [$h, ++$connections[$h][1]];
                $ids[] = $id;
            }
            foreach ($connections as $h => $c) {
                $connections[$h] = $c[0]->exec();
            }
            foreach ($results as $k => [$h, $c]) {
                $results[$k] = $connections[$h][$c];
            }
        } else {
            $redis->multi($redis instanceof Relay ? Relay::PIPELINE : \Redis::PIPELINE);
            foreach ($generator() as $command => $args) {
                $redis->{$command}(...$args);
                $ids[] = 'eval' === $command ? $args[1][0] : $args[0];
            }
            $results = $redis->exec();
        }
        if (!$redis instanceof \Predis\Client_Interface && 'eval' === $command && $redis->get_last_error()) {
            $e = $redis instanceof Relay ? new \Relay\Exception($redis->get_last_error()) : new \Redis_Exception($redis->get_last_error());
            $results = array_map(static fn($v) => false === $v ? $e : $v, (array) $results);
        }
        if (\is_bool($results)) {
            return;
        }
        foreach ($ids as $k => $id) {
            yield $id => $results[$k];
        }
    }
    private function get_hosts(): array
    {
        $hosts = [$this->redis];
        if ($this->redis instanceof \Predis\Client_Interface) {
            $connection = $this->redis->get_connection();
            if (($connection instanceof Cluster_Interface || $connection instanceof Predis2cluster_Interface) && $connection instanceof \Traversable) {
                $hosts = [];
                foreach ($connection as $c) {
                    $hosts[] = new \Predis\Client($c);
                }
            }
        } elseif ($this->redis instanceof \Redis_Array) {
            $hosts = [];
            foreach ($this->redis->_hosts() as $host) {
                $hosts[] = $this->redis->_instance($host);
            }
        } elseif ($this->redis instanceof \Redis_Cluster) {
            $hosts = [];
            foreach ($this->redis->_masters() as $host) {
                $hosts[] = new Redis_Cluster_Node_Proxy($host, $this->redis);
            }
        }
        return $hosts;
    }
    private static function filter_ssl_options(array $options): array
    {
        foreach ($options as $name => $value) {
            match ($name) {
                'allow_self_signed', 'capture_peer_cert', 'capture_peer_cert_chain', 'disable_compression', 'SNI_enabled', 'verify_peer', 'verify_peer_name' => $options[$name] = filter_var($value, \FILTER_VALIDATE_BOOLEAN),
                default => null,
            };
        }
        return $options;
    }
}