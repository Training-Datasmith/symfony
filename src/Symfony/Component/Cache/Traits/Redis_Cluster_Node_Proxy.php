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

/**
 * This file acts as a wrapper to the \RedisCluster implementation so it can accept the same type of calls as
 *  individual \Redis objects.
 *
 * Calls are made to individual nodes via: RedisCluster->{method}($host, ...args)'
 *  according to https://github.com/phpredis/phpredis/blob/develop/cluster.markdown#directed-node-commands
 *
 * @author Jack Thomas <jack.thomas@solidalpha.com>
 *
 * @internal
 */
class Redis_Cluster_Node_Proxy
{
    public function __construct(private readonly array $host, private readonly \Redis_Cluster $redis)
    {
    }
    public function __call(string $method, array $args)
    {
        return $this->redis->{$method}($this->host, ...$args);
    }
    public function scan(null|int|string &$i_iterator, ?string $str_pattern = null, ?int $i_count = null): bool|array
    {
        return $this->redis->scan($i_iterator, $this->host, $str_pattern, $i_count);
    }
    public function get_option(int $name): int
    {
        return $this->redis->get_option($name);
    }
}