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

use Symfony\Component\Var_Exporter\Lazy_Object_Interface;
use Symfony\Contracts\Service\Reset_Interface;
// Help opcache.preload discover always-needed symbols
class_exists(\Symfony\Component\Var_Exporter\Internal\Hydrator::class);
class_exists(\Symfony\Component\Var_Exporter\Internal\Lazy_Object_Registry::class);
class_exists(\Symfony\Component\Var_Exporter\Internal\Lazy_Object_State::class);
/**
 * @internal
 */
class Redis_Cluster_Proxy extends \Redis_Cluster implements Reset_Interface, Lazy_Object_Interface
{
    use Redis_Cluster62proxy_Trait;
    use Redis_Cluster63proxy_Trait;
    use Redis_Proxy_Trait {
        resetLazyObject as reset;
    }
    public function __construct(
        $name,
        $seeds = null,
        $timeout = 0,
        $read_timeout = 0,
        $persistent = false,
        #[\Sensitive_Parameter]
        $auth = null,
        $context = null
    )
    {
        $this->initialize_lazy_object()->__construct(...\func_get_args());
    }
    public function _compress($value): string
    {
        return $this->initialize_lazy_object()->_compress(...\func_get_args());
    }
    public function _masters(): array
    {
        return $this->initialize_lazy_object()->_masters(...\func_get_args());
    }
    public function _pack($value): string
    {
        return $this->initialize_lazy_object()->_pack(...\func_get_args());
    }
    public function _prefix($key): bool|string
    {
        return $this->initialize_lazy_object()->_prefix(...\func_get_args());
    }
    public function _redir(): ?string
    {
        return $this->initialize_lazy_object()->_redir(...\func_get_args());
    }
    public function _serialize($value): bool|string
    {
        return $this->initialize_lazy_object()->_serialize(...\func_get_args());
    }
    public function _uncompress($value): string
    {
        return $this->initialize_lazy_object()->_uncompress(...\func_get_args());
    }
    public function _unpack($value): mixed
    {
        return $this->initialize_lazy_object()->_unpack(...\func_get_args());
    }
    public function _unserialize($value): mixed
    {
        return $this->initialize_lazy_object()->_unserialize(...\func_get_args());
    }
    public function acl($key_or_address, $subcmd, ...$args): mixed
    {
        return $this->initialize_lazy_object()->acl(...\func_get_args());
    }
    public function append($key, $value): \Redis_Cluster|bool|int
    {
        return $this->initialize_lazy_object()->append(...\func_get_args());
    }
    public function bgrewriteaof($key_or_address): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->bgrewriteaof(...\func_get_args());
    }
    public function bgsave($key_or_address): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->bgsave(...\func_get_args());
    }
    public function bitcount($key, $start = 0, $end = -1, $bybit = false): \Redis_Cluster|bool|int
    {
        return $this->initialize_lazy_object()->bitcount(...\func_get_args());
    }
    public function bitop($operation, $deskey, $srckey, ...$otherkeys): \Redis_Cluster|bool|int
    {
        return $this->initialize_lazy_object()->bitop(...\func_get_args());
    }
    public function bitpos($key, $bit, $start = 0, $end = -1, $bybit = false): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->bitpos(...\func_get_args());
    }
    public function blmove($src, $dst, $wherefrom, $whereto, $timeout): \Redis|false|string
    {
        return $this->initialize_lazy_object()->blmove(...\func_get_args());
    }
    public function blmpop($timeout, $keys, $from, $count = 1): \Redis_Cluster|array|false|null
    {
        return $this->initialize_lazy_object()->blmpop(...\func_get_args());
    }
    public function blpop($key, $timeout_or_key, ...$extra_args): \Redis_Cluster|array|false|null
    {
        return $this->initialize_lazy_object()->blpop(...\func_get_args());
    }
    public function brpop($key, $timeout_or_key, ...$extra_args): \Redis_Cluster|array|false|null
    {
        return $this->initialize_lazy_object()->brpop(...\func_get_args());
    }
    public function brpoplpush($srckey, $deskey, $timeout): mixed
    {
        return $this->initialize_lazy_object()->brpoplpush(...\func_get_args());
    }
    public function bzmpop($timeout, $keys, $from, $count = 1): \Redis_Cluster|array|false|null
    {
        return $this->initialize_lazy_object()->bzmpop(...\func_get_args());
    }
    public function bzpopmax($key, $timeout_or_key, ...$extra_args): array
    {
        return $this->initialize_lazy_object()->bzpopmax(...\func_get_args());
    }
    public function bzpopmin($key, $timeout_or_key, ...$extra_args): array
    {
        return $this->initialize_lazy_object()->bzpopmin(...\func_get_args());
    }
    public function clearlasterror(): bool
    {
        return $this->initialize_lazy_object()->clearlasterror(...\func_get_args());
    }
    public function cleartransferredbytes(): void
    {
        $this->initialize_lazy_object()->cleartransferredbytes(...\func_get_args());
    }
    public function client($key_or_address, $subcommand, $arg = null): array|bool|string
    {
        return $this->initialize_lazy_object()->client(...\func_get_args());
    }
    public function close(): bool
    {
        return $this->initialize_lazy_object()->close(...\func_get_args());
    }
    public function cluster($key_or_address, $command, ...$extra_args): mixed
    {
        return $this->initialize_lazy_object()->cluster(...\func_get_args());
    }
    public function command(...$extra_args): mixed
    {
        return $this->initialize_lazy_object()->command(...\func_get_args());
    }
    public function config($key_or_address, $subcommand, ...$extra_args): mixed
    {
        return $this->initialize_lazy_object()->config(...\func_get_args());
    }
    public function copy($src, $dst, $options = null): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->copy(...\func_get_args());
    }
    public function dbsize($key_or_address): \Redis_Cluster|int
    {
        return $this->initialize_lazy_object()->dbsize(...\func_get_args());
    }
    public function decr($key, $by = 1): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->decr(...\func_get_args());
    }
    public function decrby($key, $value): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->decrby(...\func_get_args());
    }
    public function decrbyfloat($key, $value): float
    {
        return $this->initialize_lazy_object()->decrbyfloat(...\func_get_args());
    }
    public function del($key, ...$other_keys): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->del(...\func_get_args());
    }
    public function discard(): bool
    {
        return $this->initialize_lazy_object()->discard(...\func_get_args());
    }
    public function dump($key): \Redis_Cluster|false|string
    {
        return $this->initialize_lazy_object()->dump(...\func_get_args());
    }
    public function echo($key_or_address, $msg): \Redis_Cluster|false|string
    {
        return $this->initialize_lazy_object()->echo(...\func_get_args());
    }
    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return $this->initialize_lazy_object()->eval(...\func_get_args());
    }
    public function eval_ro($script, $args = [], $num_keys = 0): mixed
    {
        return $this->initialize_lazy_object()->eval_ro(...\func_get_args());
    }
    public function evalsha($script_sha, $args = [], $num_keys = 0): mixed
    {
        return $this->initialize_lazy_object()->evalsha(...\func_get_args());
    }
    public function evalsha_ro($script_sha, $args = [], $num_keys = 0): mixed
    {
        return $this->initialize_lazy_object()->evalsha_ro(...\func_get_args());
    }
    public function exec(): array|false
    {
        return $this->initialize_lazy_object()->exec(...\func_get_args());
    }
    public function exists($key, ...$other_keys): \Redis_Cluster|bool|int
    {
        return $this->initialize_lazy_object()->exists(...\func_get_args());
    }
    public function expire($key, $timeout, $mode = null): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->expire(...\func_get_args());
    }
    public function expireat($key, $timestamp, $mode = null): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->expireat(...\func_get_args());
    }
    public function expiretime($key): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->expiretime(...\func_get_args());
    }
    public function flushall($key_or_address, $async = false): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->flushall(...\func_get_args());
    }
    public function flushdb($key_or_address, $async = false): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->flushdb(...\func_get_args());
    }
    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->geoadd(...\func_get_args());
    }
    public function geodist($key, $src, $dest, $unit = null): \Redis_Cluster|false|float
    {
        return $this->initialize_lazy_object()->geodist(...\func_get_args());
    }
    public function geohash($key, $member, ...$other_members): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->geohash(...\func_get_args());
    }
    public function geopos($key, $member, ...$other_members): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->geopos(...\func_get_args());
    }
    public function georadius($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return $this->initialize_lazy_object()->georadius(...\func_get_args());
    }
    public function georadius_ro($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return $this->initialize_lazy_object()->georadius_ro(...\func_get_args());
    }
    public function georadiusbymember($key, $member, $radius, $unit, $options = []): mixed
    {
        return $this->initialize_lazy_object()->georadiusbymember(...\func_get_args());
    }
    public function georadiusbymember_ro($key, $member, $radius, $unit, $options = []): mixed
    {
        return $this->initialize_lazy_object()->georadiusbymember_ro(...\func_get_args());
    }
    public function geosearch($key, $position, $shape, $unit, $options = []): \Redis_Cluster|array
    {
        return $this->initialize_lazy_object()->geosearch(...\func_get_args());
    }
    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \Redis_Cluster|array|false|int
    {
        return $this->initialize_lazy_object()->geosearchstore(...\func_get_args());
    }
    public function get($key): mixed
    {
        return $this->initialize_lazy_object()->get(...\func_get_args());
    }
    public function getbit($key, $value): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->getbit(...\func_get_args());
    }
    public function getex($key, $options = []): \Redis_Cluster|false|string
    {
        return $this->initialize_lazy_object()->getex(...\func_get_args());
    }
    public function getlasterror(): ?string
    {
        return $this->initialize_lazy_object()->getlasterror(...\func_get_args());
    }
    public function getmode(): int
    {
        return $this->initialize_lazy_object()->getmode(...\func_get_args());
    }
    public function getoption($option): mixed
    {
        return $this->initialize_lazy_object()->getoption(...\func_get_args());
    }
    public function getrange($key, $start, $end): \Redis_Cluster|false|string
    {
        return $this->initialize_lazy_object()->getrange(...\func_get_args());
    }
    public function getset($key, $value): \Redis_Cluster|bool|string
    {
        return $this->initialize_lazy_object()->getset(...\func_get_args());
    }
    public function gettransferredbytes(): array|false
    {
        return $this->initialize_lazy_object()->gettransferredbytes(...\func_get_args());
    }
    public function hdel($key, $member, ...$other_members): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->hdel(...\func_get_args());
    }
    public function hexists($key, $member): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->hexists(...\func_get_args());
    }
    public function hget($key, $member): mixed
    {
        return $this->initialize_lazy_object()->hget(...\func_get_args());
    }
    public function hgetall($key): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->hgetall(...\func_get_args());
    }
    public function hincrby($key, $member, $value): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->hincrby(...\func_get_args());
    }
    public function hincrbyfloat($key, $member, $value): \Redis_Cluster|false|float
    {
        return $this->initialize_lazy_object()->hincrbyfloat(...\func_get_args());
    }
    public function hkeys($key): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->hkeys(...\func_get_args());
    }
    public function hlen($key): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->hlen(...\func_get_args());
    }
    public function hmget($key, $keys): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->hmget(...\func_get_args());
    }
    public function hmset($key, $key_values): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->hmset(...\func_get_args());
    }
    public function hrandfield($key, $options = null): \Redis_Cluster|array|string
    {
        return $this->initialize_lazy_object()->hrandfield(...\func_get_args());
    }
    public function hscan($key, &$iterator, $pattern = null, $count = 0): array|bool
    {
        return $this->initialize_lazy_object()->hscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }
    public function hset($key, $member, $value): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->hset(...\func_get_args());
    }
    public function hsetnx($key, $member, $value): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->hsetnx(...\func_get_args());
    }
    public function hstrlen($key, $field): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->hstrlen(...\func_get_args());
    }
    public function hvals($key): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->hvals(...\func_get_args());
    }
    public function incr($key, $by = 1): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->incr(...\func_get_args());
    }
    public function incrby($key, $value): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->incrby(...\func_get_args());
    }
    public function incrbyfloat($key, $value): \Redis_Cluster|false|float
    {
        return $this->initialize_lazy_object()->incrbyfloat(...\func_get_args());
    }
    public function info($key_or_address, ...$sections): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->info(...\func_get_args());
    }
    public function keys($pattern): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->keys(...\func_get_args());
    }
    public function lastsave($key_or_address): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->lastsave(...\func_get_args());
    }
    public function lcs($key1, $key2, $options = null): \Redis_Cluster|array|false|int|string
    {
        return $this->initialize_lazy_object()->lcs(...\func_get_args());
    }
    public function lget($key, $index): \Redis_Cluster|bool|string
    {
        return $this->initialize_lazy_object()->lget(...\func_get_args());
    }
    public function lindex($key, $index): mixed
    {
        return $this->initialize_lazy_object()->lindex(...\func_get_args());
    }
    public function linsert($key, $pos, $pivot, $value): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->linsert(...\func_get_args());
    }
    public function llen($key): \Redis_Cluster|bool|int
    {
        return $this->initialize_lazy_object()->llen(...\func_get_args());
    }
    public function lmove($src, $dst, $wherefrom, $whereto): \Redis|false|string
    {
        return $this->initialize_lazy_object()->lmove(...\func_get_args());
    }
    public function lmpop($keys, $from, $count = 1): \Redis_Cluster|array|false|null
    {
        return $this->initialize_lazy_object()->lmpop(...\func_get_args());
    }
    public function lpop($key, $count = 0): \Redis_Cluster|array|bool|string
    {
        return $this->initialize_lazy_object()->lpop(...\func_get_args());
    }
    public function lpos($key, $value, $options = null): \Redis|array|bool|int|null
    {
        return $this->initialize_lazy_object()->lpos(...\func_get_args());
    }
    public function lpush($key, $value, ...$other_values): \Redis_Cluster|bool|int
    {
        return $this->initialize_lazy_object()->lpush(...\func_get_args());
    }
    public function lpushx($key, $value): \Redis_Cluster|bool|int
    {
        return $this->initialize_lazy_object()->lpushx(...\func_get_args());
    }
    public function lrange($key, $start, $end): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->lrange(...\func_get_args());
    }
    public function lrem($key, $value, $count = 0): \Redis_Cluster|bool|int
    {
        return $this->initialize_lazy_object()->lrem(...\func_get_args());
    }
    public function lset($key, $index, $value): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->lset(...\func_get_args());
    }
    public function ltrim($key, $start, $end): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->ltrim(...\func_get_args());
    }
    public function mget($keys): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->mget(...\func_get_args());
    }
    public function mset($key_values): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->mset(...\func_get_args());
    }
    public function msetnx($key_values): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->msetnx(...\func_get_args());
    }
    public function multi($value = \Redis::MULTI): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->multi(...\func_get_args());
    }
    public function object($subcommand, $key): \Redis_Cluster|false|int|string
    {
        return $this->initialize_lazy_object()->object(...\func_get_args());
    }
    public function persist($key): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->persist(...\func_get_args());
    }
    public function pexpire($key, $timeout, $mode = null): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->pexpire(...\func_get_args());
    }
    public function pexpireat($key, $timestamp, $mode = null): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->pexpireat(...\func_get_args());
    }
    public function pexpiretime($key): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->pexpiretime(...\func_get_args());
    }
    public function pfadd($key, $elements): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->pfadd(...\func_get_args());
    }
    public function pfcount($key): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->pfcount(...\func_get_args());
    }
    public function pfmerge($key, $keys): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->pfmerge(...\func_get_args());
    }
    public function ping($key_or_address, $message = null): mixed
    {
        return $this->initialize_lazy_object()->ping(...\func_get_args());
    }
    public function psetex($key, $timeout, $value): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->psetex(...\func_get_args());
    }
    public function psubscribe($patterns, $callback): void
    {
        $this->initialize_lazy_object()->psubscribe(...\func_get_args());
    }
    public function pttl($key): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->pttl(...\func_get_args());
    }
    public function publish($channel, $message): \Redis_Cluster|bool|int
    {
        return $this->initialize_lazy_object()->publish(...\func_get_args());
    }
    public function pubsub($key_or_address, ...$values): mixed
    {
        return $this->initialize_lazy_object()->pubsub(...\func_get_args());
    }
    public function punsubscribe($pattern, ...$other_patterns): array|bool
    {
        return $this->initialize_lazy_object()->punsubscribe(...\func_get_args());
    }
    public function randomkey($key_or_address): \Redis_Cluster|bool|string
    {
        return $this->initialize_lazy_object()->randomkey(...\func_get_args());
    }
    public function rawcommand($key_or_address, $command, ...$args): mixed
    {
        return $this->initialize_lazy_object()->rawcommand(...\func_get_args());
    }
    public function rename($key_src, $key_dst): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->rename(...\func_get_args());
    }
    public function renamenx($key, $newkey): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->renamenx(...\func_get_args());
    }
    public function restore($key, $timeout, $value, $options = null): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->restore(...\func_get_args());
    }
    public function role($key_or_address): mixed
    {
        return $this->initialize_lazy_object()->role(...\func_get_args());
    }
    public function rpop($key, $count = 0): \Redis_Cluster|array|bool|string
    {
        return $this->initialize_lazy_object()->rpop(...\func_get_args());
    }
    public function rpoplpush($src, $dst): \Redis_Cluster|bool|string
    {
        return $this->initialize_lazy_object()->rpoplpush(...\func_get_args());
    }
    public function rpush($key, ...$elements): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->rpush(...\func_get_args());
    }
    public function rpushx($key, $value): \Redis_Cluster|bool|int
    {
        return $this->initialize_lazy_object()->rpushx(...\func_get_args());
    }
    public function sadd($key, $value, ...$other_values): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->sadd(...\func_get_args());
    }
    public function saddarray($key, $values): \Redis_Cluster|bool|int
    {
        return $this->initialize_lazy_object()->saddarray(...\func_get_args());
    }
    public function save($key_or_address): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->save(...\func_get_args());
    }
    public function scan(&$iterator, $key_or_address, $pattern = null, $count = 0): array|bool
    {
        return $this->initialize_lazy_object()->scan($iterator, ...\array_slice(\func_get_args(), 1));
    }
    public function scard($key): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->scard(...\func_get_args());
    }
    public function script($key_or_address, ...$args): mixed
    {
        return $this->initialize_lazy_object()->script(...\func_get_args());
    }
    public function sdiff($key, ...$other_keys): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->sdiff(...\func_get_args());
    }
    public function sdiffstore($dst, $key, ...$other_keys): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->sdiffstore(...\func_get_args());
    }
    public function set($key, $value, $options = null): \Redis_Cluster|bool|string
    {
        return $this->initialize_lazy_object()->set(...\func_get_args());
    }
    public function setbit($key, $offset, $onoff): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->setbit(...\func_get_args());
    }
    public function setex($key, $expire, $value): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->setex(...\func_get_args());
    }
    public function setnx($key, $value): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->setnx(...\func_get_args());
    }
    public function setoption($option, $value): bool
    {
        return $this->initialize_lazy_object()->setoption(...\func_get_args());
    }
    public function setrange($key, $offset, $value): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->setrange(...\func_get_args());
    }
    public function sinter($key, ...$other_keys): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->sinter(...\func_get_args());
    }
    public function sintercard($keys, $limit = -1): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->sintercard(...\func_get_args());
    }
    public function sinterstore($key, ...$other_keys): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->sinterstore(...\func_get_args());
    }
    public function sismember($key, $value): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->sismember(...\func_get_args());
    }
    public function slowlog($key_or_address, ...$args): mixed
    {
        return $this->initialize_lazy_object()->slowlog(...\func_get_args());
    }
    public function smembers($key): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->smembers(...\func_get_args());
    }
    public function smismember($key, $member, ...$other_members): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->smismember(...\func_get_args());
    }
    public function smove($src, $dst, $member): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->smove(...\func_get_args());
    }
    public function sort($key, $options = null): \Redis_Cluster|array|bool|int|string
    {
        return $this->initialize_lazy_object()->sort(...\func_get_args());
    }
    public function sort_ro($key, $options = null): \Redis_Cluster|array|bool|int|string
    {
        return $this->initialize_lazy_object()->sort_ro(...\func_get_args());
    }
    public function spop($key, $count = 0): \Redis_Cluster|array|false|string
    {
        return $this->initialize_lazy_object()->spop(...\func_get_args());
    }
    public function srandmember($key, $count = 0): \Redis_Cluster|array|false|string
    {
        return $this->initialize_lazy_object()->srandmember(...\func_get_args());
    }
    public function srem($key, $value, ...$other_values): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->srem(...\func_get_args());
    }
    public function sscan($key, &$iterator, $pattern = null, $count = 0): array|false
    {
        return $this->initialize_lazy_object()->sscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }
    public function strlen($key): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->strlen(...\func_get_args());
    }
    public function subscribe($channels, $cb): void
    {
        $this->initialize_lazy_object()->subscribe(...\func_get_args());
    }
    public function sunion($key, ...$other_keys): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->sunion(...\func_get_args());
    }
    public function sunionstore($dst, $key, ...$other_keys): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->sunionstore(...\func_get_args());
    }
    public function time($key_or_address): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->time(...\func_get_args());
    }
    public function touch($key, ...$other_keys): \Redis_Cluster|bool|int
    {
        return $this->initialize_lazy_object()->touch(...\func_get_args());
    }
    public function ttl($key): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->ttl(...\func_get_args());
    }
    public function type($key): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->type(...\func_get_args());
    }
    public function unlink($key, ...$other_keys): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->unlink(...\func_get_args());
    }
    public function unsubscribe($channels): array|bool
    {
        return $this->initialize_lazy_object()->unsubscribe(...\func_get_args());
    }
    public function unwatch(): bool
    {
        return $this->initialize_lazy_object()->unwatch(...\func_get_args());
    }
    public function waitaof($key_or_address, $numlocal, $numreplicas, $timeout): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->waitaof(...\func_get_args());
    }
    public function watch($key, ...$other_keys): \Redis_Cluster|bool
    {
        return $this->initialize_lazy_object()->watch(...\func_get_args());
    }
    public function xack($key, $group, $ids): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->xack(...\func_get_args());
    }
    public function xadd($key, $id, $values, $maxlen = 0, $approx = false): \Redis_Cluster|false|string
    {
        return $this->initialize_lazy_object()->xadd(...\func_get_args());
    }
    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->xautoclaim(...\func_get_args());
    }
    public function xclaim($key, $group, $consumer, $min_iddle, $ids, $options): \Redis_Cluster|array|false|string
    {
        return $this->initialize_lazy_object()->xclaim(...\func_get_args());
    }
    public function xdel($key, $ids): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->xdel(...\func_get_args());
    }
    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return $this->initialize_lazy_object()->xgroup(...\func_get_args());
    }
    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return $this->initialize_lazy_object()->xinfo(...\func_get_args());
    }
    public function xlen($key): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->xlen(...\func_get_args());
    }
    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->xpending(...\func_get_args());
    }
    public function xrange($key, $start, $end, $count = -1): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->xrange(...\func_get_args());
    }
    public function xread($streams, $count = -1, $block = -1): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->xread(...\func_get_args());
    }
    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->xreadgroup(...\func_get_args());
    }
    public function xrevrange($key, $start, $end, $count = -1): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->xrevrange(...\func_get_args());
    }
    public function xtrim($key, $maxlen, $approx = false, $minid = false, $limit = -1): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->xtrim(...\func_get_args());
    }
    public function zadd($key, $score_or_options, ...$more_scores_and_mems): \Redis_Cluster|false|float|int
    {
        return $this->initialize_lazy_object()->zadd(...\func_get_args());
    }
    public function zcard($key): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zcard(...\func_get_args());
    }
    public function zcount($key, $start, $end): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zcount(...\func_get_args());
    }
    public function zdiff($keys, $options = null): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->zdiff(...\func_get_args());
    }
    public function zdiffstore($dst, $keys): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zdiffstore(...\func_get_args());
    }
    public function zincrby($key, $value, $member): \Redis_Cluster|false|float
    {
        return $this->initialize_lazy_object()->zincrby(...\func_get_args());
    }
    public function zinter($keys, $weights = null, $options = null): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->zinter(...\func_get_args());
    }
    public function zintercard($keys, $limit = -1): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zintercard(...\func_get_args());
    }
    public function zinterstore($dst, $keys, $weights = null, $aggregate = null): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zinterstore(...\func_get_args());
    }
    public function zlexcount($key, $min, $max): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zlexcount(...\func_get_args());
    }
    public function zmpop($keys, $from, $count = 1): \Redis_Cluster|array|false|null
    {
        return $this->initialize_lazy_object()->zmpop(...\func_get_args());
    }
    public function zmscore($key, $member, ...$other_members): \Redis|array|false
    {
        return $this->initialize_lazy_object()->zmscore(...\func_get_args());
    }
    public function zpopmax($key, $value = null): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->zpopmax(...\func_get_args());
    }
    public function zpopmin($key, $value = null): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->zpopmin(...\func_get_args());
    }
    public function zrandmember($key, $options = null): \Redis_Cluster|array|string
    {
        return $this->initialize_lazy_object()->zrandmember(...\func_get_args());
    }
    public function zrange($key, $start, $end, $options = null): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->zrange(...\func_get_args());
    }
    public function zrangebylex($key, $min, $max, $offset = -1, $count = -1): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->zrangebylex(...\func_get_args());
    }
    public function zrangebyscore($key, $start, $end, $options = []): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->zrangebyscore(...\func_get_args());
    }
    public function zrangestore($dstkey, $srckey, $start, $end, $options = null): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zrangestore(...\func_get_args());
    }
    public function zrank($key, $member): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zrank(...\func_get_args());
    }
    public function zrem($key, $value, ...$other_values): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zrem(...\func_get_args());
    }
    public function zremrangebylex($key, $min, $max): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zremrangebylex(...\func_get_args());
    }
    public function zremrangebyrank($key, $min, $max): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zremrangebyrank(...\func_get_args());
    }
    public function zremrangebyscore($key, $min, $max): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zremrangebyscore(...\func_get_args());
    }
    public function zrevrange($key, $min, $max, $options = null): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->zrevrange(...\func_get_args());
    }
    public function zrevrangebylex($key, $min, $max, $options = null): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->zrevrangebylex(...\func_get_args());
    }
    public function zrevrangebyscore($key, $min, $max, $options = null): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->zrevrangebyscore(...\func_get_args());
    }
    public function zrevrank($key, $member): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zrevrank(...\func_get_args());
    }
    public function zscan($key, &$iterator, $pattern = null, $count = 0): \Redis_Cluster|array|bool
    {
        return $this->initialize_lazy_object()->zscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }
    public function zscore($key, $member): \Redis_Cluster|false|float
    {
        return $this->initialize_lazy_object()->zscore(...\func_get_args());
    }
    public function zunion($keys, $weights = null, $options = null): \Redis_Cluster|array|false
    {
        return $this->initialize_lazy_object()->zunion(...\func_get_args());
    }
    public function zunionstore($dst, $keys, $weights = null, $aggregate = null): \Redis_Cluster|false|int
    {
        return $this->initialize_lazy_object()->zunionstore(...\func_get_args());
    }
}