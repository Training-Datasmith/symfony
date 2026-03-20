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

use Symfony\Component\Cache\Traits\Relay\Relay_Cluster20trait;
use Symfony\Component\Var_Exporter\Lazy_Object_Interface;
use Symfony\Contracts\Service\Reset_Interface;
// Help opcache.preload discover always-needed symbols
class_exists(\Symfony\Component\Var_Exporter\Internal\Hydrator::class);
class_exists(\Symfony\Component\Var_Exporter\Internal\Lazy_Object_Registry::class);
class_exists(\Symfony\Component\Var_Exporter\Internal\Lazy_Object_State::class);
/**
 * @internal
 */
class Relay_Cluster_Proxy extends \Relay\Cluster implements Reset_Interface, Lazy_Object_Interface
{
    use Redis_Proxy_Trait {
        resetLazyObject as reset;
    }
    use Relay_Cluster20trait;
    public function __construct(
        $name,
        $seeds = null,
        $connect_timeout = 0,
        $command_timeout = 0,
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
    public function _get_keys(): array|false
    {
        return $this->initialize_lazy_object()->_get_keys(...\func_get_args());
    }
    public function _masters(): array
    {
        return $this->initialize_lazy_object()->_masters(...\func_get_args());
    }
    public function _pack($value): string
    {
        return $this->initialize_lazy_object()->_pack(...\func_get_args());
    }
    public function _prefix($value): string
    {
        return $this->initialize_lazy_object()->_prefix(...\func_get_args());
    }
    public function _serialize($value): string
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
    public function acl($key_or_address, $operation, ...$args): mixed
    {
        return $this->initialize_lazy_object()->acl(...\func_get_args());
    }
    public function add_allow_patterns(...$pattern): int
    {
        return $this->initialize_lazy_object()->add_allow_patterns(...\func_get_args());
    }
    public function add_ignore_patterns(...$pattern): int
    {
        return $this->initialize_lazy_object()->add_ignore_patterns(...\func_get_args());
    }
    public function append($key, $value): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->append(...\func_get_args());
    }
    public function bgrewriteaof($key_or_address): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->bgrewriteaof(...\func_get_args());
    }
    public function bgsave($key_or_address, $schedule = false): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->bgsave(...\func_get_args());
    }
    public function bitcount($key, $start = 0, $end = -1, $by_bit = false): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->bitcount(...\func_get_args());
    }
    public function bitop($operation, $dstkey, $srckey, ...$other_keys): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->bitop(...\func_get_args());
    }
    public function bitpos($key, $bit, $start = null, $end = null, $by_bit = false): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->bitpos(...\func_get_args());
    }
    public function blmove($srckey, $dstkey, $srcpos, $dstpos, $timeout): \Relay\Cluster|false|string|null
    {
        return $this->initialize_lazy_object()->blmove(...\func_get_args());
    }
    public function blmpop($timeout, $keys, $from, $count = 1): mixed
    {
        return $this->initialize_lazy_object()->blmpop(...\func_get_args());
    }
    public function blpop($key, $timeout_or_key, ...$extra_args): \Relay\Cluster|array|false|null
    {
        return $this->initialize_lazy_object()->blpop(...\func_get_args());
    }
    public function brpop($key, $timeout_or_key, ...$extra_args): \Relay\Cluster|array|false|null
    {
        return $this->initialize_lazy_object()->brpop(...\func_get_args());
    }
    public function brpoplpush($srckey, $dstkey, $timeout): mixed
    {
        return $this->initialize_lazy_object()->brpoplpush(...\func_get_args());
    }
    public function bzmpop($timeout, $keys, $from, $count = 1): \Relay\Cluster|array|false|null
    {
        return $this->initialize_lazy_object()->bzmpop(...\func_get_args());
    }
    public function bzpopmax($key, $timeout_or_key, ...$extra_args): \Relay\Cluster|array|false|null
    {
        return $this->initialize_lazy_object()->bzpopmax(...\func_get_args());
    }
    public function bzpopmin($key, $timeout_or_key, ...$extra_args): \Relay\Cluster|array|false|null
    {
        return $this->initialize_lazy_object()->bzpopmin(...\func_get_args());
    }
    public function clear_last_error(): bool
    {
        return $this->initialize_lazy_object()->clear_last_error(...\func_get_args());
    }
    public function clear_transferred_bytes(): bool
    {
        return $this->initialize_lazy_object()->clear_transferred_bytes(...\func_get_args());
    }
    public function client($key_or_address, $operation, ...$args): mixed
    {
        return $this->initialize_lazy_object()->client(...\func_get_args());
    }
    public function close(): bool
    {
        return $this->initialize_lazy_object()->close(...\func_get_args());
    }
    public function cluster($key_or_address, $operation, ...$args): mixed
    {
        return $this->initialize_lazy_object()->cluster(...\func_get_args());
    }
    public function command(...$args): \Relay\Cluster|array|false|int
    {
        return $this->initialize_lazy_object()->command(...\func_get_args());
    }
    public function config($key_or_address, $operation, ...$args): mixed
    {
        return $this->initialize_lazy_object()->config(...\func_get_args());
    }
    public function copy($srckey, $dstkey, $options = null): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->copy(...\func_get_args());
    }
    public function dbsize($key_or_address): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->dbsize(...\func_get_args());
    }
    public function decr($key, $by = 1): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->decr(...\func_get_args());
    }
    public function decrby($key, $value): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->decrby(...\func_get_args());
    }
    public function del(...$keys): \Relay\Cluster|bool|int
    {
        return $this->initialize_lazy_object()->del(...\func_get_args());
    }
    public function delifeq($key, $value): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->delifeq(...\func_get_args());
    }
    public function discard(): bool
    {
        return $this->initialize_lazy_object()->discard(...\func_get_args());
    }
    public function dispatch_events(): false|int
    {
        return $this->initialize_lazy_object()->dispatch_events(...\func_get_args());
    }
    public function dump($key): \Relay\Cluster|false|string
    {
        return $this->initialize_lazy_object()->dump(...\func_get_args());
    }
    public function echo($key_or_address, $message): \Relay\Cluster|false|string
    {
        return $this->initialize_lazy_object()->echo(...\func_get_args());
    }
    public function endpoint_id(): array|false
    {
        return $this->initialize_lazy_object()->endpoint_id(...\func_get_args());
    }
    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return $this->initialize_lazy_object()->eval(...\func_get_args());
    }
    public function eval_ro($script, $args = [], $num_keys = 0): mixed
    {
        return $this->initialize_lazy_object()->eval_ro(...\func_get_args());
    }
    public function evalsha($sha, $args = [], $num_keys = 0): mixed
    {
        return $this->initialize_lazy_object()->evalsha(...\func_get_args());
    }
    public function evalsha_ro($sha, $args = [], $num_keys = 0): mixed
    {
        return $this->initialize_lazy_object()->evalsha_ro(...\func_get_args());
    }
    public function exec(): array|false
    {
        return $this->initialize_lazy_object()->exec(...\func_get_args());
    }
    public function exists(...$keys): \Relay\Cluster|bool|int
    {
        return $this->initialize_lazy_object()->exists(...\func_get_args());
    }
    public function expire($key, $seconds, $mode = null): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->expire(...\func_get_args());
    }
    public function expireat($key, $timestamp): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->expireat(...\func_get_args());
    }
    public function expiretime($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->expiretime(...\func_get_args());
    }
    public function flush_slot_cache(): bool
    {
        return $this->initialize_lazy_object()->flush_slot_cache(...\func_get_args());
    }
    public function flushall($key_or_address, $sync = null): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->flushall(...\func_get_args());
    }
    public function flushdb($key_or_address, $sync = null): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->flushdb(...\func_get_args());
    }
    public function fullscan($match = null, $count = 0, $type = null): \Generator|false
    {
        return $this->initialize_lazy_object()->fullscan(...\func_get_args());
    }
    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->geoadd(...\func_get_args());
    }
    public function geodist($key, $src, $dst, $unit = null): \Relay\Cluster|false|float
    {
        return $this->initialize_lazy_object()->geodist(...\func_get_args());
    }
    public function geohash($key, $member, ...$other_members): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->geohash(...\func_get_args());
    }
    public function geopos($key, ...$members): \Relay\Cluster|array|false
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
    public function geosearch($key, $position, $shape, $unit, $options = []): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->geosearch(...\func_get_args());
    }
    public function geosearchstore($dstkey, $srckey, $position, $shape, $unit, $options = []): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->geosearchstore(...\func_get_args());
    }
    public function get($key): mixed
    {
        return $this->initialize_lazy_object()->get(...\func_get_args());
    }
    public function get_last_error(): ?string
    {
        return $this->initialize_lazy_object()->get_last_error(...\func_get_args());
    }
    public function get_mode($masked = false): int
    {
        return $this->initialize_lazy_object()->get_mode(...\func_get_args());
    }
    public function get_option($option): mixed
    {
        return $this->initialize_lazy_object()->get_option(...\func_get_args());
    }
    public function get_transferred_bytes(): array|false
    {
        return $this->initialize_lazy_object()->get_transferred_bytes(...\func_get_args());
    }
    public function get_with_meta($key): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->get_with_meta(...\func_get_args());
    }
    public function getbit($key, $pos): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->getbit(...\func_get_args());
    }
    public function getdel($key): mixed
    {
        return $this->initialize_lazy_object()->getdel(...\func_get_args());
    }
    public function getex($key, $options = null): mixed
    {
        return $this->initialize_lazy_object()->getex(...\func_get_args());
    }
    public function getrange($key, $start, $end): \Relay\Cluster|false|string
    {
        return $this->initialize_lazy_object()->getrange(...\func_get_args());
    }
    public function getset($key, $value): mixed
    {
        return $this->initialize_lazy_object()->getset(...\func_get_args());
    }
    public function hdel($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->hdel(...\func_get_args());
    }
    public function hexists($key, $member): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->hexists(...\func_get_args());
    }
    public function hexpire($hash, $ttl, $fields, $mode = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hexpire(...\func_get_args());
    }
    public function hexpireat($hash, $ttl, $fields, $mode = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hexpireat(...\func_get_args());
    }
    public function hexpiretime($hash, $fields): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hexpiretime(...\func_get_args());
    }
    public function hget($key, $member): mixed
    {
        return $this->initialize_lazy_object()->hget(...\func_get_args());
    }
    public function hget_with_meta($hash, $member): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->get_with_meta(...\func_get_args());
    }
    public function hgetall($key): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hgetall(...\func_get_args());
    }
    public function hgetdel($key, $fields): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hgetdel(...\func_get_args());
    }
    public function hgetex($hash, $fields, $expiry = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hgetex(...\func_get_args());
    }
    public function hincrby($key, $member, $value): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->hincrby(...\func_get_args());
    }
    public function hincrbyfloat($key, $member, $value): \Relay\Cluster|bool|float
    {
        return $this->initialize_lazy_object()->hincrbyfloat(...\func_get_args());
    }
    public function hkeys($key): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hkeys(...\func_get_args());
    }
    public function hlen($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->hlen(...\func_get_args());
    }
    public function hmget($key, $members): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hmget(...\func_get_args());
    }
    public function hmset($key, $members): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->hmset(...\func_get_args());
    }
    public function hpersist($hash, $fields): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hpersist(...\func_get_args());
    }
    public function hpexpire($hash, $ttl, $fields, $mode = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hpexpire(...\func_get_args());
    }
    public function hpexpireat($hash, $ttl, $fields, $mode = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hpexpireat(...\func_get_args());
    }
    public function hpexpiretime($hash, $fields): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hpexpiretime(...\func_get_args());
    }
    public function hpttl($hash, $fields): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hpttl(...\func_get_args());
    }
    public function hrandfield($key, $options = null): \Relay\Cluster|array|false|string
    {
        return $this->initialize_lazy_object()->hrandfield(...\func_get_args());
    }
    public function hscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return $this->initialize_lazy_object()->hscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }
    public function hset($key, ...$keys_and_vals): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->hset(...\func_get_args());
    }
    public function hsetex($key, $fields, $expiry = null): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->hsetex(...\func_get_args());
    }
    public function hsetnx($key, $member, $value): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->hsetnx(...\func_get_args());
    }
    public function hstrlen($key, $member): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->hstrlen(...\func_get_args());
    }
    public function httl($hash, $fields): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->httl(...\func_get_args());
    }
    public function hvals($key): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->hvals(...\func_get_args());
    }
    public function idle_time(): int
    {
        return $this->initialize_lazy_object()->idle_time(...\func_get_args());
    }
    public function incr($key, $by = 1): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->incr(...\func_get_args());
    }
    public function incrby($key, $value): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->incrby(...\func_get_args());
    }
    public function incrbyfloat($key, $value): \Relay\Cluster|false|float
    {
        return $this->initialize_lazy_object()->incrbyfloat(...\func_get_args());
    }
    public function info($key_or_address, ...$sections): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->info(...\func_get_args());
    }
    public function keys($pattern): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->keys(...\func_get_args());
    }
    public function lastsave($key_or_address): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->lastsave(...\func_get_args());
    }
    public function lcs($key1, $key2, $options = null): mixed
    {
        return $this->initialize_lazy_object()->lcs(...\func_get_args());
    }
    public function lindex($key, $index): mixed
    {
        return $this->initialize_lazy_object()->lindex(...\func_get_args());
    }
    public function linsert($key, $op, $pivot, $element): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->linsert(...\func_get_args());
    }
    public function listen($callback): bool
    {
        return $this->initialize_lazy_object()->listen(...\func_get_args());
    }
    public function llen($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->llen(...\func_get_args());
    }
    public function lmove($srckey, $dstkey, $srcpos, $dstpos): \Relay\Cluster|false|string|null
    {
        return $this->initialize_lazy_object()->lmove(...\func_get_args());
    }
    public function lmpop($keys, $from, $count = 1): mixed
    {
        return $this->initialize_lazy_object()->lmpop(...\func_get_args());
    }
    public function lpop($key, $count = 1): mixed
    {
        return $this->initialize_lazy_object()->lpop(...\func_get_args());
    }
    public function lpos($key, $value, $options = null): mixed
    {
        return $this->initialize_lazy_object()->lpos(...\func_get_args());
    }
    public function lpush($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->lpush(...\func_get_args());
    }
    public function lpushx($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->lpushx(...\func_get_args());
    }
    public function lrange($key, $start, $stop): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->lrange(...\func_get_args());
    }
    public function lrem($key, $member, $count = 0): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->lrem(...\func_get_args());
    }
    public function lset($key, $index, $member): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->lset(...\func_get_args());
    }
    public function ltrim($key, $start, $end): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->ltrim(...\func_get_args());
    }
    public function mget($keys): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->mget(...\func_get_args());
    }
    public function mset($kvals): \Relay\Cluster|array|bool
    {
        return $this->initialize_lazy_object()->mset(...\func_get_args());
    }
    public function msetnx($kvals): \Relay\Cluster|array|bool
    {
        return $this->initialize_lazy_object()->msetnx(...\func_get_args());
    }
    public function multi($mode = \Relay\Relay::MULTI): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->multi(...\func_get_args());
    }
    public function object($op, $key): mixed
    {
        return $this->initialize_lazy_object()->object(...\func_get_args());
    }
    public function on_flushed($callback): bool
    {
        return $this->initialize_lazy_object()->on_flushed(...\func_get_args());
    }
    public function on_invalidated($callback, $pattern = null): bool
    {
        return $this->initialize_lazy_object()->on_invalidated(...\func_get_args());
    }
    public function persist($key): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->persist(...\func_get_args());
    }
    public function pexpire($key, $milliseconds): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->pexpire(...\func_get_args());
    }
    public function pexpireat($key, $timestamp_ms): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->pexpireat(...\func_get_args());
    }
    public function pexpiretime($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->pexpiretime(...\func_get_args());
    }
    public function pfadd($key, $elements): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->pfadd(...\func_get_args());
    }
    public function pfcount($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->pfcount(...\func_get_args());
    }
    public function pfmerge($dstkey, $srckeys): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->pfmerge(...\func_get_args());
    }
    public function ping($key_or_address, $message = null): \Relay\Cluster|bool|string
    {
        return $this->initialize_lazy_object()->ping(...\func_get_args());
    }
    public function psetex($key, $milliseconds, $value): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->psetex(...\func_get_args());
    }
    public function psubscribe($patterns, $callback): bool
    {
        return $this->initialize_lazy_object()->psubscribe(...\func_get_args());
    }
    public function pttl($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->pttl(...\func_get_args());
    }
    public function publish($channel, $message): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->publish(...\func_get_args());
    }
    public function pubsub($key_or_address, $operation, ...$args): mixed
    {
        return $this->initialize_lazy_object()->pubsub(...\func_get_args());
    }
    public function punsubscribe($patterns = []): bool
    {
        return $this->initialize_lazy_object()->punsubscribe(...\func_get_args());
    }
    public function randomkey($key_or_address): \Relay\Cluster|bool|string
    {
        return $this->initialize_lazy_object()->randomkey(...\func_get_args());
    }
    public function raw_command($key_or_address, $cmd, ...$args): mixed
    {
        return $this->initialize_lazy_object()->raw_command(...\func_get_args());
    }
    public function rename($key, $newkey): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->rename(...\func_get_args());
    }
    public function renamenx($key, $newkey): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->renamenx(...\func_get_args());
    }
    public function restore($key, $ttl, $value, $options = null): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->restore(...\func_get_args());
    }
    public function role($key_or_address): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->role(...\func_get_args());
    }
    public function rpop($key, $count = 1): mixed
    {
        return $this->initialize_lazy_object()->rpop(...\func_get_args());
    }
    public function rpoplpush($srckey, $dstkey): mixed
    {
        return $this->initialize_lazy_object()->rpoplpush(...\func_get_args());
    }
    public function rpush($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->rpush(...\func_get_args());
    }
    public function rpushx($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->rpushx(...\func_get_args());
    }
    public function sadd($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->sadd(...\func_get_args());
    }
    public function save($key_or_address): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->save(...\func_get_args());
    }
    public function scan(&$iterator, $key_or_address, $match = null, $count = 0, $type = null): array|false
    {
        return $this->initialize_lazy_object()->scan($iterator, ...\array_slice(\func_get_args(), 1));
    }
    public function scard($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->scard(...\func_get_args());
    }
    public function script($key_or_address, $operation, ...$args): mixed
    {
        return $this->initialize_lazy_object()->script(...\func_get_args());
    }
    public function sdiff($key, ...$other_keys): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->sdiff(...\func_get_args());
    }
    public function sdiffstore($key, ...$other_keys): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->sdiffstore(...\func_get_args());
    }
    public function set($key, $value, $options = null): \Relay\Cluster|bool|string
    {
        return $this->initialize_lazy_object()->set(...\func_get_args());
    }
    public function set_option($option, $value): bool
    {
        return $this->initialize_lazy_object()->set_option(...\func_get_args());
    }
    public function setbit($key, $pos, $value): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->setbit(...\func_get_args());
    }
    public function setex($key, $seconds, $value): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->setex(...\func_get_args());
    }
    public function setnx($key, $value): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->setnx(...\func_get_args());
    }
    public function setrange($key, $start, $value): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->setrange(...\func_get_args());
    }
    public function sinter($key, ...$other_keys): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->sinter(...\func_get_args());
    }
    public function sintercard($keys, $limit = -1): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->sintercard(...\func_get_args());
    }
    public function sinterstore($key, ...$other_keys): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->sinterstore(...\func_get_args());
    }
    public function sismember($key, $member): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->sismember(...\func_get_args());
    }
    public function slowlog($key_or_address, $operation, ...$args): \Relay\Cluster|array|bool|int
    {
        return $this->initialize_lazy_object()->slowlog(...\func_get_args());
    }
    public function smembers($key): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->smembers(...\func_get_args());
    }
    public function smismember($key, ...$members): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->smismember(...\func_get_args());
    }
    public function smove($srckey, $dstkey, $member): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->smove(...\func_get_args());
    }
    public function sort($key, $options = []): \Relay\Cluster|array|false|int
    {
        return $this->initialize_lazy_object()->sort(...\func_get_args());
    }
    public function sort_ro($key, $options = []): \Relay\Cluster|array|false|int
    {
        return $this->initialize_lazy_object()->sort_ro(...\func_get_args());
    }
    public function spop($key, $count = 1): mixed
    {
        return $this->initialize_lazy_object()->spop(...\func_get_args());
    }
    public function srandmember($key, $count = 1): mixed
    {
        return $this->initialize_lazy_object()->srandmember(...\func_get_args());
    }
    public function srem($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->srem(...\func_get_args());
    }
    public function sscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return $this->initialize_lazy_object()->sscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }
    public function ssubscribe($channels, $callback): bool
    {
        return $this->initialize_lazy_object()->ssubscribe(...\func_get_args());
    }
    public function strlen($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->strlen(...\func_get_args());
    }
    public function subscribe($channels, $callback): bool
    {
        return $this->initialize_lazy_object()->subscribe(...\func_get_args());
    }
    public function sunion($key, ...$other_keys): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->sunion(...\func_get_args());
    }
    public function sunionstore($key, ...$other_keys): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->sunionstore(...\func_get_args());
    }
    public function sunsubscribe($channels = []): bool
    {
        return $this->initialize_lazy_object()->sunsubscribe(...\func_get_args());
    }
    public function time($key_or_address): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->time(...\func_get_args());
    }
    public function touch($key_or_array, ...$more_keys): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->touch(...\func_get_args());
    }
    public function ttl($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->ttl(...\func_get_args());
    }
    public function type($key): \Relay\Cluster|bool|int|string
    {
        return $this->initialize_lazy_object()->type(...\func_get_args());
    }
    public function unlink(...$keys): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->unlink(...\func_get_args());
    }
    public function unsubscribe($channels = []): bool
    {
        return $this->initialize_lazy_object()->unsubscribe(...\func_get_args());
    }
    public function unwatch(): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->unwatch(...\func_get_args());
    }
    public function vadd($key, $values, $element, $options = null): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->vadd(...\func_get_args());
    }
    public function vcard($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->vcard(...\func_get_args());
    }
    public function vdim($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->vdim(...\func_get_args());
    }
    public function vemb($key, $element, $raw = false): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->vemb(...\func_get_args());
    }
    public function vgetattr($key, $element, $raw = false): \Relay\Cluster|array|false|string
    {
        return $this->initialize_lazy_object()->vgetattr(...\func_get_args());
    }
    public function vinfo($key): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->vinfo(...\func_get_args());
    }
    public function vismember($key, $element): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->vismember(...\func_get_args());
    }
    public function vlinks($key, $element, $withscores): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->vlinks(...\func_get_args());
    }
    public function vrandmember($key, $count = 0): \Relay\Cluster|array|false|string
    {
        return $this->initialize_lazy_object()->vrandmember(...\func_get_args());
    }
    public function vrange($key, $end, $start, $count = -1): \Relay\Cluster|array|bool
    {
        return $this->initialize_lazy_object()->vrange(...\func_get_args());
    }
    public function vrem($key, $element): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->vrem(...\func_get_args());
    }
    public function vsetattr($key, $element, $attributes): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->vsetattr(...\func_get_args());
    }
    public function vsim($key, $member, $options = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->vsim(...\func_get_args());
    }
    public function watch($key, ...$other_keys): \Relay\Cluster|bool
    {
        return $this->initialize_lazy_object()->watch(...\func_get_args());
    }
    public function waitaof($key_or_address, $numlocal, $numremote, $timeout): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->waitaof(...\func_get_args());
    }
    public function xack($key, $group, $ids): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->xack(...\func_get_args());
    }
    public function xackdel($key, $group, $ids, $mode = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->xackdel(...\func_get_args());
    }
    public function xadd($key, $id, $values, $maxlen = 0, $approx = false, $nomkstream = false): \Relay\Cluster|false|string
    {
        return $this->initialize_lazy_object()->xadd(...\func_get_args());
    }
    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \Relay\Cluster|array|bool
    {
        return $this->initialize_lazy_object()->xautoclaim(...\func_get_args());
    }
    public function xclaim($key, $group, $consumer, $min_idle, $ids, $options): \Relay\Cluster|array|bool
    {
        return $this->initialize_lazy_object()->xclaim(...\func_get_args());
    }
    public function xdel($key, $ids): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->xdel(...\func_get_args());
    }
    public function xdelex($key, $ids, $mode = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->xdelex(...\func_get_args());
    }
    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return $this->initialize_lazy_object()->xgroup(...\func_get_args());
    }
    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return $this->initialize_lazy_object()->xinfo(...\func_get_args());
    }
    public function xlen($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->xlen(...\func_get_args());
    }
    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null, $idle = 0): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->xpending(...\func_get_args());
    }
    public function xrange($key, $start, $end, $count = -1): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->xrange(...\func_get_args());
    }
    public function xread($streams, $count = -1, $block = -1): \Relay\Cluster|array|bool|null
    {
        return $this->initialize_lazy_object()->xread(...\func_get_args());
    }
    public function xreadgroup($key, $consumer, $streams, $count = 1, $block = 1): \Relay\Cluster|array|bool|null
    {
        return $this->initialize_lazy_object()->xreadgroup(...\func_get_args());
    }
    public function xrevrange($key, $end, $start, $count = -1): \Relay\Cluster|array|bool
    {
        return $this->initialize_lazy_object()->xrevrange(...\func_get_args());
    }
    public function xtrim($key, $threshold, $approx = false, $minid = false, $limit = -1): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->xtrim(...\func_get_args());
    }
    public function zadd($key, ...$args): mixed
    {
        return $this->initialize_lazy_object()->zadd(...\func_get_args());
    }
    public function zcard($key): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->zcard(...\func_get_args());
    }
    public function zcount($key, $min, $max): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->zcount(...\func_get_args());
    }
    public function zdiff($keys, $options = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->zdiff(...\func_get_args());
    }
    public function zdiffstore($dstkey, $keys): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->zdiffstore(...\func_get_args());
    }
    public function zincrby($key, $score, $member): \Relay\Cluster|false|float
    {
        return $this->initialize_lazy_object()->zincrby(...\func_get_args());
    }
    public function zinter($keys, $weights = null, $options = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->zinter(...\func_get_args());
    }
    public function zintercard($keys, $limit = -1): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->zintercard(...\func_get_args());
    }
    public function zinterstore($dstkey, $keys, $weights = null, $options = null): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->zinterstore(...\func_get_args());
    }
    public function zlexcount($key, $min, $max): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->zlexcount(...\func_get_args());
    }
    public function zmpop($keys, $from, $count = 1): \Relay\Cluster|array|false|null
    {
        return $this->initialize_lazy_object()->zmpop(...\func_get_args());
    }
    public function zmscore($key, ...$members): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->zmscore(...\func_get_args());
    }
    public function zpopmax($key, $count = 1): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->zpopmax(...\func_get_args());
    }
    public function zpopmin($key, $count = 1): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->zpopmin(...\func_get_args());
    }
    public function zrandmember($key, $options = null): mixed
    {
        return $this->initialize_lazy_object()->zrandmember(...\func_get_args());
    }
    public function zrange($key, $start, $end, $options = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->zrange(...\func_get_args());
    }
    public function zrangebylex($key, $min, $max, $offset = -1, $count = -1): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->zrangebylex(...\func_get_args());
    }
    public function zrangebyscore($key, $start, $end, $options = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->zrangebyscore(...\func_get_args());
    }
    public function zrangestore($dstkey, $srckey, $start, $end, $options = null): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->zrangestore(...\func_get_args());
    }
    public function zrank($key, $rank, $withscore = false): \Relay\Cluster|array|false|int
    {
        return $this->initialize_lazy_object()->zrank(...\func_get_args());
    }
    public function zrem($key, ...$args): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->zrem(...\func_get_args());
    }
    public function zremrangebylex($key, $min, $max): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->zremrangebylex(...\func_get_args());
    }
    public function zremrangebyrank($key, $start, $end): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->zremrangebyrank(...\func_get_args());
    }
    public function zremrangebyscore($key, $min, $max): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->zremrangebyscore(...\func_get_args());
    }
    public function zrevrange($key, $start, $end, $options = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->zrevrange(...\func_get_args());
    }
    public function zrevrangebylex($key, $max, $min, $offset = -1, $count = -1): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->zrevrangebylex(...\func_get_args());
    }
    public function zrevrangebyscore($key, $start, $end, $options = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->zrevrangebyscore(...\func_get_args());
    }
    public function zrevrank($key, $rank, $withscore = false): \Relay\Cluster|array|false|int
    {
        return $this->initialize_lazy_object()->zrevrank(...\func_get_args());
    }
    public function zscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return $this->initialize_lazy_object()->zscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }
    public function zscore($key, $member): \Relay\Cluster|false|float
    {
        return $this->initialize_lazy_object()->zscore(...\func_get_args());
    }
    public function zunion($keys, $weights = null, $options = null): \Relay\Cluster|array|false
    {
        return $this->initialize_lazy_object()->zunion(...\func_get_args());
    }
    public function zunionstore($dstkey, $keys, $weights = null, $options = null): \Relay\Cluster|false|int
    {
        return $this->initialize_lazy_object()->zunionstore(...\func_get_args());
    }
}