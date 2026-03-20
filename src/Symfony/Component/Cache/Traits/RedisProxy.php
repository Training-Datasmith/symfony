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
class Redis_Proxy extends \Redis implements Reset_Interface, Lazy_Object_Interface
{
    use Redis62proxy_Trait;
    use Redis63proxy_Trait;
    use Redis_Proxy_Trait {
        resetLazyObject as reset;
    }
    public function __construct($options = null)
    {
        $this->initialize_lazy_object()->__construct(...\func_get_args());
    }
    public function _compress($value): string
    {
        return $this->initialize_lazy_object()->_compress(...\func_get_args());
    }
    public function _pack($value): string
    {
        return $this->initialize_lazy_object()->_pack(...\func_get_args());
    }
    public function _prefix($key): string
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
    public function acl($subcmd, ...$args): mixed
    {
        return $this->initialize_lazy_object()->acl(...\func_get_args());
    }
    public function append($key, $value): \Redis|false|int
    {
        return $this->initialize_lazy_object()->append(...\func_get_args());
    }
    public function auth(
        #[\Sensitive_Parameter]
        $credentials
    ): \Redis|bool
    {
        return $this->initialize_lazy_object()->auth(...\func_get_args());
    }
    public function bg_save(): \Redis|bool
    {
        return $this->initialize_lazy_object()->bg_save(...\func_get_args());
    }
    public function bgrewriteaof(): \Redis|bool
    {
        return $this->initialize_lazy_object()->bgrewriteaof(...\func_get_args());
    }
    public function bitcount($key, $start = 0, $end = -1, $bybit = false): \Redis|false|int
    {
        return $this->initialize_lazy_object()->bitcount(...\func_get_args());
    }
    public function bitop($operation, $deskey, $srckey, ...$other_keys): \Redis|false|int
    {
        return $this->initialize_lazy_object()->bitop(...\func_get_args());
    }
    public function bitpos($key, $bit, $start = 0, $end = -1, $bybit = false): \Redis|false|int
    {
        return $this->initialize_lazy_object()->bitpos(...\func_get_args());
    }
    public function bl_pop($key_or_keys, $timeout_or_key, ...$extra_args): \Redis|array|false|null
    {
        return $this->initialize_lazy_object()->bl_pop(...\func_get_args());
    }
    public function blmove($src, $dst, $wherefrom, $whereto, $timeout): \Redis|false|string
    {
        return $this->initialize_lazy_object()->blmove(...\func_get_args());
    }
    public function blmpop($timeout, $keys, $from, $count = 1): \Redis|array|false|null
    {
        return $this->initialize_lazy_object()->blmpop(...\func_get_args());
    }
    public function br_pop($key_or_keys, $timeout_or_key, ...$extra_args): \Redis|array|false|null
    {
        return $this->initialize_lazy_object()->br_pop(...\func_get_args());
    }
    public function brpoplpush($src, $dst, $timeout): \Redis|false|string
    {
        return $this->initialize_lazy_object()->brpoplpush(...\func_get_args());
    }
    public function bz_pop_max($key, $timeout_or_key, ...$extra_args): \Redis|array|false
    {
        return $this->initialize_lazy_object()->bz_pop_max(...\func_get_args());
    }
    public function bz_pop_min($key, $timeout_or_key, ...$extra_args): \Redis|array|false
    {
        return $this->initialize_lazy_object()->bz_pop_min(...\func_get_args());
    }
    public function bzmpop($timeout, $keys, $from, $count = 1): \Redis|array|false|null
    {
        return $this->initialize_lazy_object()->bzmpop(...\func_get_args());
    }
    public function clear_last_error(): bool
    {
        return $this->initialize_lazy_object()->clear_last_error(...\func_get_args());
    }
    public function clear_transferred_bytes(): void
    {
        $this->initialize_lazy_object()->clear_transferred_bytes(...\func_get_args());
    }
    public function client($opt, ...$args): mixed
    {
        return $this->initialize_lazy_object()->client(...\func_get_args());
    }
    public function close(): bool
    {
        return $this->initialize_lazy_object()->close(...\func_get_args());
    }
    public function command($opt = null, ...$args): mixed
    {
        return $this->initialize_lazy_object()->command(...\func_get_args());
    }
    public function config($operation, $key_or_settings = null, $value = null): mixed
    {
        return $this->initialize_lazy_object()->config(...\func_get_args());
    }
    public function connect($host, $port = 6379, $timeout = 0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0, $context = null): bool
    {
        return $this->initialize_lazy_object()->connect(...\func_get_args());
    }
    public function copy($src, $dst, $options = null): \Redis|bool
    {
        return $this->initialize_lazy_object()->copy(...\func_get_args());
    }
    public function db_size(): \Redis|false|int
    {
        return $this->initialize_lazy_object()->db_size(...\func_get_args());
    }
    public function debug($key): \Redis|string
    {
        return $this->initialize_lazy_object()->debug(...\func_get_args());
    }
    public function decr($key, $by = 1): \Redis|false|int
    {
        return $this->initialize_lazy_object()->decr(...\func_get_args());
    }
    public function decr_by($key, $value): \Redis|false|int
    {
        return $this->initialize_lazy_object()->decr_by(...\func_get_args());
    }
    public function del($key, ...$other_keys): \Redis|false|int
    {
        return $this->initialize_lazy_object()->del(...\func_get_args());
    }
    public function delete($key, ...$other_keys): \Redis|false|int
    {
        return $this->initialize_lazy_object()->delete(...\func_get_args());
    }
    public function discard(): \Redis|bool
    {
        return $this->initialize_lazy_object()->discard(...\func_get_args());
    }
    public function dump($key): \Redis|false|string
    {
        return $this->initialize_lazy_object()->dump(...\func_get_args());
    }
    public function echo($str): \Redis|false|string
    {
        return $this->initialize_lazy_object()->echo(...\func_get_args());
    }
    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return $this->initialize_lazy_object()->eval(...\func_get_args());
    }
    public function eval_ro($script_sha, $args = [], $num_keys = 0): mixed
    {
        return $this->initialize_lazy_object()->eval_ro(...\func_get_args());
    }
    public function evalsha($sha1, $args = [], $num_keys = 0): mixed
    {
        return $this->initialize_lazy_object()->evalsha(...\func_get_args());
    }
    public function evalsha_ro($sha1, $args = [], $num_keys = 0): mixed
    {
        return $this->initialize_lazy_object()->evalsha_ro(...\func_get_args());
    }
    public function exec(): \Redis|array|false
    {
        return $this->initialize_lazy_object()->exec(...\func_get_args());
    }
    public function exists($key, ...$other_keys): \Redis|bool|int
    {
        return $this->initialize_lazy_object()->exists(...\func_get_args());
    }
    public function expire($key, $timeout, $mode = null): \Redis|bool
    {
        return $this->initialize_lazy_object()->expire(...\func_get_args());
    }
    public function expire_at($key, $timestamp, $mode = null): \Redis|bool
    {
        return $this->initialize_lazy_object()->expire_at(...\func_get_args());
    }
    public function expiretime($key): \Redis|false|int
    {
        return $this->initialize_lazy_object()->expiretime(...\func_get_args());
    }
    public function failover($to = null, $abort = false, $timeout = 0): \Redis|bool
    {
        return $this->initialize_lazy_object()->failover(...\func_get_args());
    }
    public function fcall($fn, $keys = [], $args = []): mixed
    {
        return $this->initialize_lazy_object()->fcall(...\func_get_args());
    }
    public function fcall_ro($fn, $keys = [], $args = []): mixed
    {
        return $this->initialize_lazy_object()->fcall_ro(...\func_get_args());
    }
    public function flush_all($sync = null): \Redis|bool
    {
        return $this->initialize_lazy_object()->flush_all(...\func_get_args());
    }
    public function flush_db($sync = null): \Redis|bool
    {
        return $this->initialize_lazy_object()->flush_db(...\func_get_args());
    }
    public function function($operation, ...$args): \Redis|array|bool|string
    {
        return $this->initialize_lazy_object()->function(...\func_get_args());
    }
    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \Redis|false|int
    {
        return $this->initialize_lazy_object()->geoadd(...\func_get_args());
    }
    public function geodist($key, $src, $dst, $unit = null): \Redis|false|float
    {
        return $this->initialize_lazy_object()->geodist(...\func_get_args());
    }
    public function geohash($key, $member, ...$other_members): \Redis|array|false
    {
        return $this->initialize_lazy_object()->geohash(...\func_get_args());
    }
    public function geopos($key, $member, ...$other_members): \Redis|array|false
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
    public function geosearch($key, $position, $shape, $unit, $options = []): array
    {
        return $this->initialize_lazy_object()->geosearch(...\func_get_args());
    }
    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \Redis|array|false|int
    {
        return $this->initialize_lazy_object()->geosearchstore(...\func_get_args());
    }
    public function get($key): mixed
    {
        return $this->initialize_lazy_object()->get(...\func_get_args());
    }
    public function get_auth(): mixed
    {
        return $this->initialize_lazy_object()->get_auth(...\func_get_args());
    }
    public function get_bit($key, $idx): \Redis|false|int
    {
        return $this->initialize_lazy_object()->get_bit(...\func_get_args());
    }
    public function get_db_num(): int
    {
        return $this->initialize_lazy_object()->get_db_num(...\func_get_args());
    }
    public function get_del($key): \Redis|bool|string
    {
        return $this->initialize_lazy_object()->get_del(...\func_get_args());
    }
    public function get_ex($key, $options = []): \Redis|bool|string
    {
        return $this->initialize_lazy_object()->get_ex(...\func_get_args());
    }
    public function get_host(): string
    {
        return $this->initialize_lazy_object()->get_host(...\func_get_args());
    }
    public function get_last_error(): ?string
    {
        return $this->initialize_lazy_object()->get_last_error(...\func_get_args());
    }
    public function get_mode(): int
    {
        return $this->initialize_lazy_object()->get_mode(...\func_get_args());
    }
    public function get_option($option): mixed
    {
        return $this->initialize_lazy_object()->get_option(...\func_get_args());
    }
    public function get_persistent_id(): ?string
    {
        return $this->initialize_lazy_object()->get_persistent_id(...\func_get_args());
    }
    public function get_port(): int
    {
        return $this->initialize_lazy_object()->get_port(...\func_get_args());
    }
    public function get_range($key, $start, $end): \Redis|false|string
    {
        return $this->initialize_lazy_object()->get_range(...\func_get_args());
    }
    public function get_read_timeout(): float
    {
        return $this->initialize_lazy_object()->get_read_timeout(...\func_get_args());
    }
    public function get_timeout(): false|float
    {
        return $this->initialize_lazy_object()->get_timeout(...\func_get_args());
    }
    public function get_transferred_bytes(): array
    {
        return $this->initialize_lazy_object()->get_transferred_bytes(...\func_get_args());
    }
    public function getset($key, $value): \Redis|false|string
    {
        return $this->initialize_lazy_object()->getset(...\func_get_args());
    }
    public function h_del($key, $field, ...$other_fields): \Redis|false|int
    {
        return $this->initialize_lazy_object()->h_del(...\func_get_args());
    }
    public function h_exists($key, $field): \Redis|bool
    {
        return $this->initialize_lazy_object()->h_exists(...\func_get_args());
    }
    public function h_get($key, $member): mixed
    {
        return $this->initialize_lazy_object()->h_get(...\func_get_args());
    }
    public function h_get_all($key): \Redis|array|false
    {
        return $this->initialize_lazy_object()->h_get_all(...\func_get_args());
    }
    public function h_incr_by($key, $field, $value): \Redis|false|int
    {
        return $this->initialize_lazy_object()->h_incr_by(...\func_get_args());
    }
    public function h_incr_by_float($key, $field, $value): \Redis|false|float
    {
        return $this->initialize_lazy_object()->h_incr_by_float(...\func_get_args());
    }
    public function h_keys($key): \Redis|array|false
    {
        return $this->initialize_lazy_object()->h_keys(...\func_get_args());
    }
    public function h_len($key): \Redis|false|int
    {
        return $this->initialize_lazy_object()->h_len(...\func_get_args());
    }
    public function h_mget($key, $fields): \Redis|array|false
    {
        return $this->initialize_lazy_object()->h_mget(...\func_get_args());
    }
    public function h_mset($key, $fieldvals): \Redis|bool
    {
        return $this->initialize_lazy_object()->h_mset(...\func_get_args());
    }
    public function h_rand_field($key, $options = null): \Redis|array|false|string
    {
        return $this->initialize_lazy_object()->h_rand_field(...\func_get_args());
    }
    public function h_set($key, ...$fields_and_vals): \Redis|false|int
    {
        return $this->initialize_lazy_object()->h_set(...\func_get_args());
    }
    public function h_set_nx($key, $field, $value): \Redis|bool
    {
        return $this->initialize_lazy_object()->h_set_nx(...\func_get_args());
    }
    public function h_str_len($key, $field): \Redis|false|int
    {
        return $this->initialize_lazy_object()->h_str_len(...\func_get_args());
    }
    public function h_vals($key): \Redis|array|false
    {
        return $this->initialize_lazy_object()->h_vals(...\func_get_args());
    }
    public function hscan($key, &$iterator, $pattern = null, $count = 0): \Redis|array|bool
    {
        return $this->initialize_lazy_object()->hscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }
    public function incr($key, $by = 1): \Redis|false|int
    {
        return $this->initialize_lazy_object()->incr(...\func_get_args());
    }
    public function incr_by($key, $value): \Redis|false|int
    {
        return $this->initialize_lazy_object()->incr_by(...\func_get_args());
    }
    public function incr_by_float($key, $value): \Redis|false|float
    {
        return $this->initialize_lazy_object()->incr_by_float(...\func_get_args());
    }
    public function info(...$sections): \Redis|array|false
    {
        return $this->initialize_lazy_object()->info(...\func_get_args());
    }
    public function is_connected(): bool
    {
        return $this->initialize_lazy_object()->is_connected(...\func_get_args());
    }
    public function keys($pattern)
    {
        return $this->initialize_lazy_object()->keys(...\func_get_args());
    }
    public function l_insert($key, $pos, $pivot, $value)
    {
        return $this->initialize_lazy_object()->l_insert(...\func_get_args());
    }
    public function l_len($key): \Redis|false|int
    {
        return $this->initialize_lazy_object()->l_len(...\func_get_args());
    }
    public function l_move($src, $dst, $wherefrom, $whereto): \Redis|false|string
    {
        return $this->initialize_lazy_object()->l_move(...\func_get_args());
    }
    public function l_pop($key, $count = 0): \Redis|array|bool|string
    {
        return $this->initialize_lazy_object()->l_pop(...\func_get_args());
    }
    public function l_pos($key, $value, $options = null): \Redis|array|bool|int|null
    {
        return $this->initialize_lazy_object()->l_pos(...\func_get_args());
    }
    public function l_push($key, ...$elements): \Redis|false|int
    {
        return $this->initialize_lazy_object()->l_push(...\func_get_args());
    }
    public function l_pushx($key, $value): \Redis|false|int
    {
        return $this->initialize_lazy_object()->l_pushx(...\func_get_args());
    }
    public function l_set($key, $index, $value): \Redis|bool
    {
        return $this->initialize_lazy_object()->l_set(...\func_get_args());
    }
    public function last_save(): int
    {
        return $this->initialize_lazy_object()->last_save(...\func_get_args());
    }
    public function lcs($key1, $key2, $options = null): \Redis|array|false|int|string
    {
        return $this->initialize_lazy_object()->lcs(...\func_get_args());
    }
    public function lindex($key, $index): mixed
    {
        return $this->initialize_lazy_object()->lindex(...\func_get_args());
    }
    public function lmpop($keys, $from, $count = 1): \Redis|array|false|null
    {
        return $this->initialize_lazy_object()->lmpop(...\func_get_args());
    }
    public function lrange($key, $start, $end): \Redis|array|false
    {
        return $this->initialize_lazy_object()->lrange(...\func_get_args());
    }
    public function lrem($key, $value, $count = 0): \Redis|false|int
    {
        return $this->initialize_lazy_object()->lrem(...\func_get_args());
    }
    public function ltrim($key, $start, $end): \Redis|bool
    {
        return $this->initialize_lazy_object()->ltrim(...\func_get_args());
    }
    public function mget($keys): \Redis|array|false
    {
        return $this->initialize_lazy_object()->mget(...\func_get_args());
    }
    public function migrate(
        $host,
        $port,
        $key,
        $dstdb,
        $timeout,
        $copy = false,
        $replace = false,
        #[\Sensitive_Parameter]
        $credentials = null
    ): \Redis|bool
    {
        return $this->initialize_lazy_object()->migrate(...\func_get_args());
    }
    public function move($key, $index): \Redis|bool
    {
        return $this->initialize_lazy_object()->move(...\func_get_args());
    }
    public function mset($key_values): \Redis|bool
    {
        return $this->initialize_lazy_object()->mset(...\func_get_args());
    }
    public function msetnx($key_values): \Redis|bool
    {
        return $this->initialize_lazy_object()->msetnx(...\func_get_args());
    }
    public function multi($value = \Redis::MULTI): \Redis|bool
    {
        return $this->initialize_lazy_object()->multi(...\func_get_args());
    }
    public function object($subcommand, $key): \Redis|false|int|string
    {
        return $this->initialize_lazy_object()->object(...\func_get_args());
    }
    public function open($host, $port = 6379, $timeout = 0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0, $context = null): bool
    {
        return $this->initialize_lazy_object()->open(...\func_get_args());
    }
    public function pconnect($host, $port = 6379, $timeout = 0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0, $context = null): bool
    {
        return $this->initialize_lazy_object()->pconnect(...\func_get_args());
    }
    public function persist($key): \Redis|bool
    {
        return $this->initialize_lazy_object()->persist(...\func_get_args());
    }
    public function pexpire($key, $timeout, $mode = null): bool
    {
        return $this->initialize_lazy_object()->pexpire(...\func_get_args());
    }
    public function pexpire_at($key, $timestamp, $mode = null): \Redis|bool
    {
        return $this->initialize_lazy_object()->pexpire_at(...\func_get_args());
    }
    public function pexpiretime($key): \Redis|false|int
    {
        return $this->initialize_lazy_object()->pexpiretime(...\func_get_args());
    }
    public function pfadd($key, $elements): \Redis|int
    {
        return $this->initialize_lazy_object()->pfadd(...\func_get_args());
    }
    public function pfcount($key_or_keys): \Redis|false|int
    {
        return $this->initialize_lazy_object()->pfcount(...\func_get_args());
    }
    public function pfmerge($dst, $srckeys): \Redis|bool
    {
        return $this->initialize_lazy_object()->pfmerge(...\func_get_args());
    }
    public function ping($message = null): \Redis|bool|string
    {
        return $this->initialize_lazy_object()->ping(...\func_get_args());
    }
    public function pipeline(): \Redis|bool
    {
        return $this->initialize_lazy_object()->pipeline(...\func_get_args());
    }
    public function popen($host, $port = 6379, $timeout = 0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0, $context = null): bool
    {
        return $this->initialize_lazy_object()->popen(...\func_get_args());
    }
    public function psetex($key, $expire, $value): \Redis|bool
    {
        return $this->initialize_lazy_object()->psetex(...\func_get_args());
    }
    public function psubscribe($patterns, $cb): bool
    {
        return $this->initialize_lazy_object()->psubscribe(...\func_get_args());
    }
    public function pttl($key): \Redis|false|int
    {
        return $this->initialize_lazy_object()->pttl(...\func_get_args());
    }
    public function publish($channel, $message): \Redis|false|int
    {
        return $this->initialize_lazy_object()->publish(...\func_get_args());
    }
    public function pubsub($command, $arg = null): mixed
    {
        return $this->initialize_lazy_object()->pubsub(...\func_get_args());
    }
    public function punsubscribe($patterns): \Redis|array|bool
    {
        return $this->initialize_lazy_object()->punsubscribe(...\func_get_args());
    }
    public function r_pop($key, $count = 0): \Redis|array|bool|string
    {
        return $this->initialize_lazy_object()->r_pop(...\func_get_args());
    }
    public function r_push($key, ...$elements): \Redis|false|int
    {
        return $this->initialize_lazy_object()->r_push(...\func_get_args());
    }
    public function r_pushx($key, $value): \Redis|false|int
    {
        return $this->initialize_lazy_object()->r_pushx(...\func_get_args());
    }
    public function random_key(): \Redis|false|string
    {
        return $this->initialize_lazy_object()->random_key(...\func_get_args());
    }
    public function rawcommand($command, ...$args): mixed
    {
        return $this->initialize_lazy_object()->rawcommand(...\func_get_args());
    }
    public function rename($old_name, $new_name): \Redis|bool
    {
        return $this->initialize_lazy_object()->rename(...\func_get_args());
    }
    public function rename_nx($key_src, $key_dst): \Redis|bool
    {
        return $this->initialize_lazy_object()->rename_nx(...\func_get_args());
    }
    public function replicaof($host = null, $port = 6379): \Redis|bool
    {
        return $this->initialize_lazy_object()->replicaof(...\func_get_args());
    }
    public function restore($key, $ttl, $value, $options = null): \Redis|bool
    {
        return $this->initialize_lazy_object()->restore(...\func_get_args());
    }
    public function role(): mixed
    {
        return $this->initialize_lazy_object()->role(...\func_get_args());
    }
    public function rpoplpush($srckey, $dstkey): \Redis|false|string
    {
        return $this->initialize_lazy_object()->rpoplpush(...\func_get_args());
    }
    public function s_add($key, $value, ...$other_values): \Redis|false|int
    {
        return $this->initialize_lazy_object()->s_add(...\func_get_args());
    }
    public function s_add_array($key, $values): int
    {
        return $this->initialize_lazy_object()->s_add_array(...\func_get_args());
    }
    public function s_diff($key, ...$other_keys): \Redis|array|false
    {
        return $this->initialize_lazy_object()->s_diff(...\func_get_args());
    }
    public function s_diff_store($dst, $key, ...$other_keys): \Redis|false|int
    {
        return $this->initialize_lazy_object()->s_diff_store(...\func_get_args());
    }
    public function s_inter($key, ...$other_keys): \Redis|array|false
    {
        return $this->initialize_lazy_object()->s_inter(...\func_get_args());
    }
    public function s_inter_store($key, ...$other_keys): \Redis|false|int
    {
        return $this->initialize_lazy_object()->s_inter_store(...\func_get_args());
    }
    public function s_members($key): \Redis|array|false
    {
        return $this->initialize_lazy_object()->s_members(...\func_get_args());
    }
    public function s_mis_member($key, $member, ...$other_members): \Redis|array|false
    {
        return $this->initialize_lazy_object()->s_mis_member(...\func_get_args());
    }
    public function s_move($src, $dst, $value): \Redis|bool
    {
        return $this->initialize_lazy_object()->s_move(...\func_get_args());
    }
    public function s_pop($key, $count = 0): \Redis|array|false|string
    {
        return $this->initialize_lazy_object()->s_pop(...\func_get_args());
    }
    public function s_rand_member($key, $count = 0): mixed
    {
        return $this->initialize_lazy_object()->s_rand_member(...\func_get_args());
    }
    public function s_union($key, ...$other_keys): \Redis|array|false
    {
        return $this->initialize_lazy_object()->s_union(...\func_get_args());
    }
    public function s_union_store($dst, $key, ...$other_keys): \Redis|false|int
    {
        return $this->initialize_lazy_object()->s_union_store(...\func_get_args());
    }
    public function save(): \Redis|bool
    {
        return $this->initialize_lazy_object()->save(...\func_get_args());
    }
    public function scan(&$iterator, $pattern = null, $count = 0, $type = null): array|false
    {
        return $this->initialize_lazy_object()->scan($iterator, ...\array_slice(\func_get_args(), 1));
    }
    public function scard($key): \Redis|false|int
    {
        return $this->initialize_lazy_object()->scard(...\func_get_args());
    }
    public function script($command, ...$args): mixed
    {
        return $this->initialize_lazy_object()->script(...\func_get_args());
    }
    public function select($db): \Redis|bool
    {
        return $this->initialize_lazy_object()->select(...\func_get_args());
    }
    public function set($key, $value, $options = null): \Redis|bool|string
    {
        return $this->initialize_lazy_object()->set(...\func_get_args());
    }
    public function set_bit($key, $idx, $value): \Redis|false|int
    {
        return $this->initialize_lazy_object()->set_bit(...\func_get_args());
    }
    public function set_option($option, $value): bool
    {
        return $this->initialize_lazy_object()->set_option(...\func_get_args());
    }
    public function set_range($key, $index, $value): \Redis|false|int
    {
        return $this->initialize_lazy_object()->set_range(...\func_get_args());
    }
    public function setex($key, $expire, $value)
    {
        return $this->initialize_lazy_object()->setex(...\func_get_args());
    }
    public function setnx($key, $value): \Redis|bool
    {
        return $this->initialize_lazy_object()->setnx(...\func_get_args());
    }
    public function sintercard($keys, $limit = -1): \Redis|false|int
    {
        return $this->initialize_lazy_object()->sintercard(...\func_get_args());
    }
    public function sismember($key, $value): \Redis|bool
    {
        return $this->initialize_lazy_object()->sismember(...\func_get_args());
    }
    public function slaveof($host = null, $port = 6379): \Redis|bool
    {
        return $this->initialize_lazy_object()->slaveof(...\func_get_args());
    }
    public function slowlog($operation, $length = 0): mixed
    {
        return $this->initialize_lazy_object()->slowlog(...\func_get_args());
    }
    public function sort($key, $options = null): mixed
    {
        return $this->initialize_lazy_object()->sort(...\func_get_args());
    }
    public function sort_asc($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return $this->initialize_lazy_object()->sort_asc(...\func_get_args());
    }
    public function sort_asc_alpha($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return $this->initialize_lazy_object()->sort_asc_alpha(...\func_get_args());
    }
    public function sort_desc($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return $this->initialize_lazy_object()->sort_desc(...\func_get_args());
    }
    public function sort_desc_alpha($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return $this->initialize_lazy_object()->sort_desc_alpha(...\func_get_args());
    }
    public function sort_ro($key, $options = null): mixed
    {
        return $this->initialize_lazy_object()->sort_ro(...\func_get_args());
    }
    public function srem($key, $value, ...$other_values): \Redis|false|int
    {
        return $this->initialize_lazy_object()->srem(...\func_get_args());
    }
    public function sscan($key, &$iterator, $pattern = null, $count = 0): array|false
    {
        return $this->initialize_lazy_object()->sscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }
    public function ssubscribe($channels, $cb): bool
    {
        return $this->initialize_lazy_object()->ssubscribe(...\func_get_args());
    }
    public function strlen($key): \Redis|false|int
    {
        return $this->initialize_lazy_object()->strlen(...\func_get_args());
    }
    public function subscribe($channels, $cb): bool
    {
        return $this->initialize_lazy_object()->subscribe(...\func_get_args());
    }
    public function sunsubscribe($channels): \Redis|array|bool
    {
        return $this->initialize_lazy_object()->sunsubscribe(...\func_get_args());
    }
    public function swapdb($src, $dst): \Redis|bool
    {
        return $this->initialize_lazy_object()->swapdb(...\func_get_args());
    }
    public function time(): \Redis|array
    {
        return $this->initialize_lazy_object()->time(...\func_get_args());
    }
    public function touch($key_or_array, ...$more_keys): \Redis|false|int
    {
        return $this->initialize_lazy_object()->touch(...\func_get_args());
    }
    public function ttl($key): \Redis|false|int
    {
        return $this->initialize_lazy_object()->ttl(...\func_get_args());
    }
    public function type($key): \Redis|false|int
    {
        return $this->initialize_lazy_object()->type(...\func_get_args());
    }
    public function unlink($key, ...$other_keys): \Redis|false|int
    {
        return $this->initialize_lazy_object()->unlink(...\func_get_args());
    }
    public function unsubscribe($channels): \Redis|array|bool
    {
        return $this->initialize_lazy_object()->unsubscribe(...\func_get_args());
    }
    public function unwatch(): \Redis|bool
    {
        return $this->initialize_lazy_object()->unwatch(...\func_get_args());
    }
    public function wait($numreplicas, $timeout): false|int
    {
        return $this->initialize_lazy_object()->wait(...\func_get_args());
    }
    public function waitaof($numlocal, $numreplicas, $timeout): \Redis|array|false
    {
        return $this->initialize_lazy_object()->waitaof(...\func_get_args());
    }
    public function watch($key, ...$other_keys): \Redis|bool
    {
        return $this->initialize_lazy_object()->watch(...\func_get_args());
    }
    public function xack($key, $group, $ids): false|int
    {
        return $this->initialize_lazy_object()->xack(...\func_get_args());
    }
    public function xadd($key, $id, $values, $maxlen = 0, $approx = false, $nomkstream = false): \Redis|false|string
    {
        return $this->initialize_lazy_object()->xadd(...\func_get_args());
    }
    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \Redis|array|bool
    {
        return $this->initialize_lazy_object()->xautoclaim(...\func_get_args());
    }
    public function xclaim($key, $group, $consumer, $min_idle, $ids, $options): \Redis|array|bool
    {
        return $this->initialize_lazy_object()->xclaim(...\func_get_args());
    }
    public function xdel($key, $ids): \Redis|false|int
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
    public function xlen($key): \Redis|false|int
    {
        return $this->initialize_lazy_object()->xlen(...\func_get_args());
    }
    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null): \Redis|array|false
    {
        return $this->initialize_lazy_object()->xpending(...\func_get_args());
    }
    public function xrange($key, $start, $end, $count = -1): \Redis|array|bool
    {
        return $this->initialize_lazy_object()->xrange(...\func_get_args());
    }
    public function xread($streams, $count = -1, $block = -1): \Redis|array|bool
    {
        return $this->initialize_lazy_object()->xread(...\func_get_args());
    }
    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \Redis|array|bool
    {
        return $this->initialize_lazy_object()->xreadgroup(...\func_get_args());
    }
    public function xrevrange($key, $end, $start, $count = -1): \Redis|array|bool
    {
        return $this->initialize_lazy_object()->xrevrange(...\func_get_args());
    }
    public function xtrim($key, $threshold, $approx = false, $minid = false, $limit = -1): \Redis|false|int
    {
        return $this->initialize_lazy_object()->xtrim(...\func_get_args());
    }
    public function z_add($key, $score_or_options, ...$more_scores_and_mems): \Redis|false|float|int
    {
        return $this->initialize_lazy_object()->z_add(...\func_get_args());
    }
    public function z_card($key): \Redis|false|int
    {
        return $this->initialize_lazy_object()->z_card(...\func_get_args());
    }
    public function z_count($key, $start, $end): \Redis|false|int
    {
        return $this->initialize_lazy_object()->z_count(...\func_get_args());
    }
    public function z_incr_by($key, $value, $member): \Redis|false|float
    {
        return $this->initialize_lazy_object()->z_incr_by(...\func_get_args());
    }
    public function z_lex_count($key, $min, $max): \Redis|false|int
    {
        return $this->initialize_lazy_object()->z_lex_count(...\func_get_args());
    }
    public function z_mscore($key, $member, ...$other_members): \Redis|array|false
    {
        return $this->initialize_lazy_object()->z_mscore(...\func_get_args());
    }
    public function z_pop_max($key, $count = null): \Redis|array|false
    {
        return $this->initialize_lazy_object()->z_pop_max(...\func_get_args());
    }
    public function z_pop_min($key, $count = null): \Redis|array|false
    {
        return $this->initialize_lazy_object()->z_pop_min(...\func_get_args());
    }
    public function z_rand_member($key, $options = null): \Redis|array|string
    {
        return $this->initialize_lazy_object()->z_rand_member(...\func_get_args());
    }
    public function z_range($key, $start, $end, $options = null): \Redis|array|false
    {
        return $this->initialize_lazy_object()->z_range(...\func_get_args());
    }
    public function z_range_by_lex($key, $min, $max, $offset = -1, $count = -1): \Redis|array|false
    {
        return $this->initialize_lazy_object()->z_range_by_lex(...\func_get_args());
    }
    public function z_range_by_score($key, $start, $end, $options = []): \Redis|array|false
    {
        return $this->initialize_lazy_object()->z_range_by_score(...\func_get_args());
    }
    public function z_rank($key, $member): \Redis|false|int
    {
        return $this->initialize_lazy_object()->z_rank(...\func_get_args());
    }
    public function z_rem($key, $member, ...$other_members): \Redis|false|int
    {
        return $this->initialize_lazy_object()->z_rem(...\func_get_args());
    }
    public function z_rem_range_by_lex($key, $min, $max): \Redis|false|int
    {
        return $this->initialize_lazy_object()->z_rem_range_by_lex(...\func_get_args());
    }
    public function z_rem_range_by_rank($key, $start, $end): \Redis|false|int
    {
        return $this->initialize_lazy_object()->z_rem_range_by_rank(...\func_get_args());
    }
    public function z_rem_range_by_score($key, $start, $end): \Redis|false|int
    {
        return $this->initialize_lazy_object()->z_rem_range_by_score(...\func_get_args());
    }
    public function z_rev_range($key, $start, $end, $scores = null): \Redis|array|false
    {
        return $this->initialize_lazy_object()->z_rev_range(...\func_get_args());
    }
    public function z_rev_range_by_lex($key, $max, $min, $offset = -1, $count = -1): \Redis|array|false
    {
        return $this->initialize_lazy_object()->z_rev_range_by_lex(...\func_get_args());
    }
    public function z_rev_range_by_score($key, $max, $min, $options = []): \Redis|array|false
    {
        return $this->initialize_lazy_object()->z_rev_range_by_score(...\func_get_args());
    }
    public function z_rev_rank($key, $member): \Redis|false|int
    {
        return $this->initialize_lazy_object()->z_rev_rank(...\func_get_args());
    }
    public function z_score($key, $member): \Redis|false|float
    {
        return $this->initialize_lazy_object()->z_score(...\func_get_args());
    }
    public function zdiff($keys, $options = null): \Redis|array|false
    {
        return $this->initialize_lazy_object()->zdiff(...\func_get_args());
    }
    public function zdiffstore($dst, $keys): \Redis|false|int
    {
        return $this->initialize_lazy_object()->zdiffstore(...\func_get_args());
    }
    public function zinter($keys, $weights = null, $options = null): \Redis|array|false
    {
        return $this->initialize_lazy_object()->zinter(...\func_get_args());
    }
    public function zintercard($keys, $limit = -1): \Redis|false|int
    {
        return $this->initialize_lazy_object()->zintercard(...\func_get_args());
    }
    public function zinterstore($dst, $keys, $weights = null, $aggregate = null): \Redis|false|int
    {
        return $this->initialize_lazy_object()->zinterstore(...\func_get_args());
    }
    public function zmpop($keys, $from, $count = 1): \Redis|array|false|null
    {
        return $this->initialize_lazy_object()->zmpop(...\func_get_args());
    }
    public function zrangestore($dstkey, $srckey, $start, $end, $options = null): \Redis|false|int
    {
        return $this->initialize_lazy_object()->zrangestore(...\func_get_args());
    }
    public function zscan($key, &$iterator, $pattern = null, $count = 0): \Redis|array|false
    {
        return $this->initialize_lazy_object()->zscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }
    public function zunion($keys, $weights = null, $options = null): \Redis|array|false
    {
        return $this->initialize_lazy_object()->zunion(...\func_get_args());
    }
    public function zunionstore($dst, $keys, $weights = null, $aggregate = null): \Redis|false|int
    {
        return $this->initialize_lazy_object()->zunionstore(...\func_get_args());
    }
}