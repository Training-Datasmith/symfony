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

use Symfony\Component\Cache\Traits\Relay\Relay20Trait;
use Symfony\Component\Var_Exporter\Lazy_Object_Interface;
use Symfony\Contracts\Service\Reset_Interface;
// Help opcache.preload discover always-needed symbols
class_exists(\Symfony\Component\Var_Exporter\Internal\Hydrator::class);
class_exists(\Symfony\Component\Var_Exporter\Internal\Lazy_Object_Registry::class);
class_exists(\Symfony\Component\Var_Exporter\Internal\Lazy_Object_State::class);
/**
 * @internal
 */
class Relay_Proxy extends \Relay\Relay implements Reset_Interface, Lazy_Object_Interface
{
    use Redis_Proxy_Trait {
        resetLazyObject as reset;
    }
    use Relay20Trait;
    public function __construct(
        $host = null,
        $port = 6379,
        $connect_timeout = 0.0,
        $command_timeout = 0.0,
        #[\Sensitive_Parameter]
        $context = [],
        $database = 0
    )
    {
        $this->initialize_lazy_object()->__construct(...\func_get_args());
    }
    public function _compress($value): string
    {
        return $this->initialize_lazy_object()->_compress(...\func_get_args());
    }
    public function _get_keys()
    {
        return $this->initialize_lazy_object()->_get_keys(...\func_get_args());
    }
    public function _pack($value): string
    {
        return $this->initialize_lazy_object()->_pack(...\func_get_args());
    }
    public function _prefix($value): string
    {
        return $this->initialize_lazy_object()->_prefix(...\func_get_args());
    }
    public function _serialize($value): mixed
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
    public function acl($cmd, ...$args): mixed
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
    public function append($key, $value): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->append(...\func_get_args());
    }
    public function auth(
        #[\Sensitive_Parameter]
        $auth
    ): bool
    {
        return $this->initialize_lazy_object()->auth(...\func_get_args());
    }
    public function bgrewriteaof(): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->bgrewriteaof(...\func_get_args());
    }
    public function bgsave($arg = null): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->bgsave(...\func_get_args());
    }
    public function bitcount($key, $start = 0, $end = -1, $by_bit = false): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->bitcount(...\func_get_args());
    }
    public function bitfield($key, ...$args): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->bitfield(...\func_get_args());
    }
    public function bitop($operation, $dstkey, $srckey, ...$other_keys): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->bitop(...\func_get_args());
    }
    public function bitpos($key, $bit, $start = null, $end = null, $bybit = false): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->bitpos(...\func_get_args());
    }
    public function blmove($srckey, $dstkey, $srcpos, $dstpos, $timeout): mixed
    {
        return $this->initialize_lazy_object()->blmove(...\func_get_args());
    }
    public function blmpop($timeout, $keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return $this->initialize_lazy_object()->blmpop(...\func_get_args());
    }
    public function blpop($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return $this->initialize_lazy_object()->blpop(...\func_get_args());
    }
    public function brpop($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return $this->initialize_lazy_object()->brpop(...\func_get_args());
    }
    public function brpoplpush($source, $dest, $timeout): mixed
    {
        return $this->initialize_lazy_object()->brpoplpush(...\func_get_args());
    }
    public function bytes(): array
    {
        return $this->initialize_lazy_object()->bytes(...\func_get_args());
    }
    public function bzmpop($timeout, $keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return $this->initialize_lazy_object()->bzmpop(...\func_get_args());
    }
    public function bzpopmax($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return $this->initialize_lazy_object()->bzpopmax(...\func_get_args());
    }
    public function bzpopmin($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return $this->initialize_lazy_object()->bzpopmin(...\func_get_args());
    }
    public function clear_bytes(): void
    {
        $this->initialize_lazy_object()->clear_bytes(...\func_get_args());
    }
    public function clear_last_error(): bool
    {
        return $this->initialize_lazy_object()->clear_last_error(...\func_get_args());
    }
    public function client($operation, ...$args): mixed
    {
        return $this->initialize_lazy_object()->client(...\func_get_args());
    }
    public function close(): bool
    {
        return $this->initialize_lazy_object()->close(...\func_get_args());
    }
    public function cms_incr_by($key, $field, $value, ...$fields_and_falues): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->cms_incr_by(...\func_get_args());
    }
    public function cms_info($key): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->cms_info(...\func_get_args());
    }
    public function cms_init_by_dim($key, $width, $depth): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->cms_init_by_dim(...\func_get_args());
    }
    public function cms_init_by_prob($key, $error, $probability): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->cms_init_by_prob(...\func_get_args());
    }
    public function cms_merge($dstkey, $keys, $weights = []): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->cms_merge(...\func_get_args());
    }
    public function cms_query($key, ...$fields): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->cms_query(...\func_get_args());
    }
    public function command(...$args): \Relay\Relay|array|false|int
    {
        return $this->initialize_lazy_object()->command(...\func_get_args());
    }
    public function commandlog($subcmd, ...$args): \Relay\Relay|array|bool|int
    {
        return $this->initialize_lazy_object()->commandlog(...\func_get_args());
    }
    public function config($operation, $key = null, $value = null): \Relay\Relay|array|bool
    {
        return $this->initialize_lazy_object()->config(...\func_get_args());
    }
    public function connect(
        $host,
        $port = 6379,
        $timeout = 0.0,
        $persistent_id = null,
        $retry_interval = 0,
        $read_timeout = 0.0,
        #[\Sensitive_Parameter]
        $context = [],
        $database = 0
    ): bool
    {
        return $this->initialize_lazy_object()->connect(...\func_get_args());
    }
    public function copy($src, $dst, $options = null): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->copy(...\func_get_args());
    }
    public function dbsize(): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->dbsize(...\func_get_args());
    }
    public function decr($key, $by = 1): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->decr(...\func_get_args());
    }
    public function decrby($key, $value): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->decrby(...\func_get_args());
    }
    public function del(...$keys): \Relay\Relay|bool|int
    {
        return $this->initialize_lazy_object()->del(...\func_get_args());
    }
    public function delifeq($key, $value): \Relay\Relay|false|int
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
    public function dump($key): \Relay\Relay|false|null|string
    {
        return $this->initialize_lazy_object()->dump(...\func_get_args());
    }
    public function echo($arg): \Relay\Relay|bool|string
    {
        return $this->initialize_lazy_object()->echo(...\func_get_args());
    }
    public function endpoint_id(): false|string
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
    public function exec(): \Relay\Relay|array|bool
    {
        return $this->initialize_lazy_object()->exec(...\func_get_args());
    }
    public function exists(...$keys): \Relay\Relay|bool|int
    {
        return $this->initialize_lazy_object()->exists(...\func_get_args());
    }
    public function expire($key, $seconds, $mode = null): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->expire(...\func_get_args());
    }
    public function expireat($key, $timestamp): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->expireat(...\func_get_args());
    }
    public function expiretime($key): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->expiretime(...\func_get_args());
    }
    public function fcall($name, $keys = [], $argv = [], $handler = null): mixed
    {
        return $this->initialize_lazy_object()->fcall(...\func_get_args());
    }
    public function fcall_ro($name, $keys = [], $argv = [], $handler = null): mixed
    {
        return $this->initialize_lazy_object()->fcall_ro(...\func_get_args());
    }
    public function flushall($sync = null): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->flushall(...\func_get_args());
    }
    public function flushdb($sync = null): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->flushdb(...\func_get_args());
    }
    public function ft_aggregate($index, $query, $options = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->ft_aggregate(...\func_get_args());
    }
    public function ft_alias_add($index, $alias): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->ft_alias_add(...\func_get_args());
    }
    public function ft_alias_del($alias): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->ft_alias_del(...\func_get_args());
    }
    public function ft_alias_update($index, $alias): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->ft_alias_update(...\func_get_args());
    }
    public function ft_alter($index, $schema, $skipinitialscan = false): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->ft_alter(...\func_get_args());
    }
    public function ft_config($operation, $option, $value = null): \Relay\Relay|array|bool
    {
        return $this->initialize_lazy_object()->ft_config(...\func_get_args());
    }
    public function ft_create($index, $schema, $options = null): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->ft_create(...\func_get_args());
    }
    public function ft_cursor($operation, $index, $cursor, $options = null): \Relay\Relay|array|bool
    {
        return $this->initialize_lazy_object()->ft_cursor(...\func_get_args());
    }
    public function ft_dict_add($dict, $term, ...$other_terms): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->ft_dict_add(...\func_get_args());
    }
    public function ft_dict_del($dict, $term, ...$other_terms): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->ft_dict_del(...\func_get_args());
    }
    public function ft_dict_dump($dict): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->ft_dict_dump(...\func_get_args());
    }
    public function ft_drop_index($index, $dd = false): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->ft_drop_index(...\func_get_args());
    }
    public function ft_explain($index, $query, $dialect = 0): \Relay\Relay|false|string
    {
        return $this->initialize_lazy_object()->ft_explain(...\func_get_args());
    }
    public function ft_explain_cli($index, $query, $dialect = 0): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->ft_explain_cli(...\func_get_args());
    }
    public function ft_info($index): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->ft_info(...\func_get_args());
    }
    public function ft_profile($index, $command, $query, $limited = false): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->ft_profile(...\func_get_args());
    }
    public function ft_search($index, $query, $options = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->ft_search(...\func_get_args());
    }
    public function ft_spell_check($index, $query, $options = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->ft_spell_check(...\func_get_args());
    }
    public function ft_syn_dump($index): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->ft_syn_dump(...\func_get_args());
    }
    public function ft_syn_update($index, $synonym, $term_or_terms, $skipinitialscan = false): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->ft_syn_update(...\func_get_args());
    }
    public function ft_tag_vals($index, $tag): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->ft_tag_vals(...\func_get_args());
    }
    public function function($op, ...$args): mixed
    {
        return $this->initialize_lazy_object()->function(...\func_get_args());
    }
    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->geoadd(...\func_get_args());
    }
    public function geodist($key, $src, $dst, $unit = null): \Relay\Relay|false|float|null
    {
        return $this->initialize_lazy_object()->geodist(...\func_get_args());
    }
    public function geohash($key, $member, ...$other_members): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->geohash(...\func_get_args());
    }
    public function geopos($key, ...$members): \Relay\Relay|array|false
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
    public function geosearch($key, $position, $shape, $unit, $options = []): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->geosearch(...\func_get_args());
    }
    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \Relay\Relay|false|int
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
    public function get_bytes(): array
    {
        return $this->initialize_lazy_object()->get_bytes(...\func_get_args());
    }
    public function get_db_num(): mixed
    {
        return $this->initialize_lazy_object()->get_db_num(...\func_get_args());
    }
    public function get_host(): false|string
    {
        return $this->initialize_lazy_object()->get_host(...\func_get_args());
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
    public function get_persistent_id(): false|string
    {
        return $this->initialize_lazy_object()->get_persistent_id(...\func_get_args());
    }
    public function get_port(): false|int
    {
        return $this->initialize_lazy_object()->get_port(...\func_get_args());
    }
    public function get_read_timeout(): false|float
    {
        return $this->initialize_lazy_object()->get_read_timeout(...\func_get_args());
    }
    public function get_timeout(): false|float
    {
        return $this->initialize_lazy_object()->get_timeout(...\func_get_args());
    }
    public function get_with_meta($key): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->get_with_meta(...\func_get_args());
    }
    public function getbit($key, $pos): \Relay\Relay|false|int
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
    public function getrange($key, $start, $end): mixed
    {
        return $this->initialize_lazy_object()->getrange(...\func_get_args());
    }
    public function getset($key, $value): mixed
    {
        return $this->initialize_lazy_object()->getset(...\func_get_args());
    }
    public function hdel($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->hdel(...\func_get_args());
    }
    public function hexists($hash, $member): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->hexists(...\func_get_args());
    }
    public function hexpire($hash, $ttl, $fields, $mode = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hexpire(...\func_get_args());
    }
    public function hexpireat($hash, $ttl, $fields, $mode = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hexpireat(...\func_get_args());
    }
    public function hexpiretime($hash, $fields): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hexpiretime(...\func_get_args());
    }
    public function hget($hash, $member): mixed
    {
        return $this->initialize_lazy_object()->hget(...\func_get_args());
    }
    public function hget_with_meta($hash, $member): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->get_with_meta(...\func_get_args());
    }
    public function hgetall($hash): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hgetall(...\func_get_args());
    }
    public function hgetdel($key, $fields): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hgetdel(...\func_get_args());
    }
    public function hgetex($hash, $fields, $expiry = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hgetex(...\func_get_args());
    }
    public function hincrby($key, $mem, $value): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->hincrby(...\func_get_args());
    }
    public function hincrbyfloat($key, $mem, $value): \Relay\Relay|bool|float
    {
        return $this->initialize_lazy_object()->hincrbyfloat(...\func_get_args());
    }
    public function hkeys($hash): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hkeys(...\func_get_args());
    }
    public function hlen($key): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->hlen(...\func_get_args());
    }
    public function hmget($hash, $members): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hmget(...\func_get_args());
    }
    public function hmset($hash, $members): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->hmset(...\func_get_args());
    }
    public function hpersist($hash, $fields): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hpersist(...\func_get_args());
    }
    public function hpexpire($hash, $ttl, $fields, $mode = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hpexpire(...\func_get_args());
    }
    public function hpexpireat($hash, $ttl, $fields, $mode = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hpexpireat(...\func_get_args());
    }
    public function hpexpiretime($hash, $fields): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hpexpiretime(...\func_get_args());
    }
    public function hpttl($hash, $fields): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hpttl(...\func_get_args());
    }
    public function hrandfield($hash, $options = null): \Relay\Relay|array|false|null|string
    {
        return $this->initialize_lazy_object()->hrandfield(...\func_get_args());
    }
    public function hscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return $this->initialize_lazy_object()->hscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }
    public function hset($key, ...$keys_and_vals): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->hset(...\func_get_args());
    }
    public function hsetex($key, $fields, $expiry = null): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->hsetex(...\func_get_args());
    }
    public function hsetnx($hash, $member, $value): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->hsetnx(...\func_get_args());
    }
    public function hstrlen($hash, $member): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->hstrlen(...\func_get_args());
    }
    public function httl($hash, $fields): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->httl(...\func_get_args());
    }
    public function hvals($hash): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->hvals(...\func_get_args());
    }
    public function idle_time(): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->idle_time(...\func_get_args());
    }
    public function incr($key, $by = 1): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->incr(...\func_get_args());
    }
    public function incrby($key, $value): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->incrby(...\func_get_args());
    }
    public function incrbyfloat($key, $value): \Relay\Relay|false|float
    {
        return $this->initialize_lazy_object()->incrbyfloat(...\func_get_args());
    }
    public function info(...$sections): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->info(...\func_get_args());
    }
    public function is_connected(): bool
    {
        return $this->initialize_lazy_object()->is_connected(...\func_get_args());
    }
    public function is_tracked($key): bool
    {
        return $this->initialize_lazy_object()->is_tracked(...\func_get_args());
    }
    public function json_arr_append($key, $value_or_array, $path = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_arr_append(...\func_get_args());
    }
    public function json_arr_index($key, $path, $value, $start = 0, $stop = -1): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_arr_index(...\func_get_args());
    }
    public function json_arr_insert($key, $path, $index, $value, ...$other_values): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_arr_insert(...\func_get_args());
    }
    public function json_arr_len($key, $path = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_arr_len(...\func_get_args());
    }
    public function json_arr_pop($key, $path = null, $index = -1): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_arr_pop(...\func_get_args());
    }
    public function json_arr_trim($key, $path, $start, $stop): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_arr_trim(...\func_get_args());
    }
    public function json_clear($key, $path = null): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->json_clear(...\func_get_args());
    }
    public function json_debug($command, $key, $path = null): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->json_debug(...\func_get_args());
    }
    public function json_del($key, $path = null): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->json_del(...\func_get_args());
    }
    public function json_forget($key, $path = null): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->json_forget(...\func_get_args());
    }
    public function json_get($key, $options = [], ...$paths): mixed
    {
        return $this->initialize_lazy_object()->json_get(...\func_get_args());
    }
    public function json_merge($key, $path, $value): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->json_merge(...\func_get_args());
    }
    public function json_mget($key_or_array, $path): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_mget(...\func_get_args());
    }
    public function json_mset($key, $path, $value, ...$other_triples): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->json_mset(...\func_get_args());
    }
    public function json_num_incr_by($key, $path, $value): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_num_incr_by(...\func_get_args());
    }
    public function json_num_mult_by($key, $path, $value): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_num_mult_by(...\func_get_args());
    }
    public function json_obj_keys($key, $path = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_obj_keys(...\func_get_args());
    }
    public function json_obj_len($key, $path = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_obj_len(...\func_get_args());
    }
    public function json_resp($key, $path = null): \Relay\Relay|array|false|int|string
    {
        return $this->initialize_lazy_object()->json_resp(...\func_get_args());
    }
    public function json_set($key, $path, $value, $condition = null): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->json_set(...\func_get_args());
    }
    public function json_str_append($key, $value, $path = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_str_append(...\func_get_args());
    }
    public function json_str_len($key, $path = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_str_len(...\func_get_args());
    }
    public function json_toggle($key, $path): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_toggle(...\func_get_args());
    }
    public function json_type($key, $path = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->json_type(...\func_get_args());
    }
    public function keys($pattern): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->keys(...\func_get_args());
    }
    public function lastsave(): \Relay\Relay|false|int
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
    public function linsert($key, $op, $pivot, $element): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->linsert(...\func_get_args());
    }
    public function listen($callback): bool
    {
        return $this->initialize_lazy_object()->listen(...\func_get_args());
    }
    public function llen($key): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->llen(...\func_get_args());
    }
    public function lmove($srckey, $dstkey, $srcpos, $dstpos): mixed
    {
        return $this->initialize_lazy_object()->lmove(...\func_get_args());
    }
    public function lmpop($keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return $this->initialize_lazy_object()->lmpop(...\func_get_args());
    }
    public function lpop($key, $count = 1): mixed
    {
        return $this->initialize_lazy_object()->lpop(...\func_get_args());
    }
    public function lpos($key, $value, $options = null): \Relay\Relay|array|false|int|null
    {
        return $this->initialize_lazy_object()->lpos(...\func_get_args());
    }
    public function lpush($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->lpush(...\func_get_args());
    }
    public function lpushx($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->lpushx(...\func_get_args());
    }
    public function lrange($key, $start, $stop): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->lrange(...\func_get_args());
    }
    public function lrem($key, $mem, $count = 0): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->lrem(...\func_get_args());
    }
    public function lset($key, $index, $mem): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->lset(...\func_get_args());
    }
    public function ltrim($key, $start, $end): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->ltrim(...\func_get_args());
    }
    public function mget($keys): \Relay\Relay|array|false
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
    ): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->migrate(...\func_get_args());
    }
    public function move($key, $db): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->move(...\func_get_args());
    }
    public function mset($kvals): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->mset(...\func_get_args());
    }
    public function msetnx($kvals): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->msetnx(...\func_get_args());
    }
    public function multi($mode = 0): \Relay\Relay|bool
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
    public function option($option, $value = null): mixed
    {
        return $this->initialize_lazy_object()->option(...\func_get_args());
    }
    public function pclose(): bool
    {
        return $this->initialize_lazy_object()->pclose(...\func_get_args());
    }
    public function pconnect(
        $host,
        $port = 6379,
        $timeout = 0.0,
        $persistent_id = null,
        $retry_interval = 0,
        $read_timeout = 0.0,
        #[\Sensitive_Parameter]
        $context = [],
        $database = 0
    ): bool
    {
        return $this->initialize_lazy_object()->pconnect(...\func_get_args());
    }
    public function persist($key): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->persist(...\func_get_args());
    }
    public function pexpire($key, $milliseconds): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->pexpire(...\func_get_args());
    }
    public function pexpireat($key, $timestamp_ms): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->pexpireat(...\func_get_args());
    }
    public function pexpiretime($key): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->pexpiretime(...\func_get_args());
    }
    public function pfadd($key, $elements): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->pfadd(...\func_get_args());
    }
    public function pfcount($key_or_keys): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->pfcount(...\func_get_args());
    }
    public function pfmerge($dst, $srckeys): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->pfmerge(...\func_get_args());
    }
    public function ping($arg = null): \Relay\Relay|bool|string
    {
        return $this->initialize_lazy_object()->ping(...\func_get_args());
    }
    public function pipeline(): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->pipeline(...\func_get_args());
    }
    public function psetex($key, $milliseconds, $value): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->psetex(...\func_get_args());
    }
    public function psubscribe($patterns, $callback): bool
    {
        return $this->initialize_lazy_object()->psubscribe(...\func_get_args());
    }
    public function pttl($key): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->pttl(...\func_get_args());
    }
    public function publish($channel, $message): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->publish(...\func_get_args());
    }
    public function pubsub($operation, ...$args): mixed
    {
        return $this->initialize_lazy_object()->pubsub(...\func_get_args());
    }
    public function punsubscribe($patterns = []): bool
    {
        return $this->initialize_lazy_object()->punsubscribe(...\func_get_args());
    }
    public function randomkey(): \Relay\Relay|bool|null|string
    {
        return $this->initialize_lazy_object()->randomkey(...\func_get_args());
    }
    public function raw_command($cmd, ...$args): mixed
    {
        return $this->initialize_lazy_object()->raw_command(...\func_get_args());
    }
    public function read_timeout(): false|float
    {
        return $this->initialize_lazy_object()->read_timeout(...\func_get_args());
    }
    public function rename($key, $newkey): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->rename(...\func_get_args());
    }
    public function renamenx($key, $newkey): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->renamenx(...\func_get_args());
    }
    public function replicaof($host = null, $port = 0): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->replicaof(...\func_get_args());
    }
    public function restore($key, $ttl, $value, $options = null): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->restore(...\func_get_args());
    }
    public function role(): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->role(...\func_get_args());
    }
    public function rpop($key, $count = 1): mixed
    {
        return $this->initialize_lazy_object()->rpop(...\func_get_args());
    }
    public function rpoplpush($source, $dest): mixed
    {
        return $this->initialize_lazy_object()->rpoplpush(...\func_get_args());
    }
    public function rpush($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->rpush(...\func_get_args());
    }
    public function rpushx($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->rpushx(...\func_get_args());
    }
    public function sadd($set, $member, ...$members): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->sadd(...\func_get_args());
    }
    public function save(): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->save(...\func_get_args());
    }
    public function scan(&$iterator, $match = null, $count = 0, $type = null): array|false
    {
        return $this->initialize_lazy_object()->scan($iterator, ...\array_slice(\func_get_args(), 1));
    }
    public function scard($key): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->scard(...\func_get_args());
    }
    public function script($command, ...$args): mixed
    {
        return $this->initialize_lazy_object()->script(...\func_get_args());
    }
    public function sdiff($key, ...$other_keys): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->sdiff(...\func_get_args());
    }
    public function sdiffstore($key, ...$other_keys): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->sdiffstore(...\func_get_args());
    }
    public function select($db): \Relay\Relay|bool|string
    {
        return $this->initialize_lazy_object()->select(...\func_get_args());
    }
    public function server_name(): false|string
    {
        return $this->initialize_lazy_object()->server_name(...\func_get_args());
    }
    public function server_version(): false|string
    {
        return $this->initialize_lazy_object()->server_version(...\func_get_args());
    }
    public function set($key, $value, $options = null): mixed
    {
        return $this->initialize_lazy_object()->set(...\func_get_args());
    }
    public function set_option($option, $value): bool
    {
        return $this->initialize_lazy_object()->set_option(...\func_get_args());
    }
    public function setbit($key, $pos, $val): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->setbit(...\func_get_args());
    }
    public function setex($key, $seconds, $value): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->setex(...\func_get_args());
    }
    public function setnx($key, $value): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->setnx(...\func_get_args());
    }
    public function setrange($key, $start, $value): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->setrange(...\func_get_args());
    }
    public function sinter($key, ...$other_keys): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->sinter(...\func_get_args());
    }
    public function sintercard($keys, $limit = -1): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->sintercard(...\func_get_args());
    }
    public function sinterstore($key, ...$other_keys): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->sinterstore(...\func_get_args());
    }
    public function sismember($set, $member): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->sismember(...\func_get_args());
    }
    public function slowlog($operation, ...$extra_args): \Relay\Relay|array|bool|int
    {
        return $this->initialize_lazy_object()->slowlog(...\func_get_args());
    }
    public function smembers($set): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->smembers(...\func_get_args());
    }
    public function smismember($set, ...$members): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->smismember(...\func_get_args());
    }
    public function smove($srcset, $dstset, $member): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->smove(...\func_get_args());
    }
    public function socket_id(): false|string
    {
        return $this->initialize_lazy_object()->socket_id(...\func_get_args());
    }
    public function sort($key, $options = []): \Relay\Relay|array|false|int
    {
        return $this->initialize_lazy_object()->sort(...\func_get_args());
    }
    public function sort_ro($key, $options = []): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->sort_ro(...\func_get_args());
    }
    public function spop($set, $count = 1): mixed
    {
        return $this->initialize_lazy_object()->spop(...\func_get_args());
    }
    public function spublish($channel, $message): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->spublish(...\func_get_args());
    }
    public function srandmember($set, $count = 1): mixed
    {
        return $this->initialize_lazy_object()->srandmember(...\func_get_args());
    }
    public function srem($set, $member, ...$members): \Relay\Relay|false|int
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
    public function strlen($key): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->strlen(...\func_get_args());
    }
    public function subscribe($channels, $callback): bool
    {
        return $this->initialize_lazy_object()->subscribe(...\func_get_args());
    }
    public function sunion($key, ...$other_keys): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->sunion(...\func_get_args());
    }
    public function sunionstore($key, ...$other_keys): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->sunionstore(...\func_get_args());
    }
    public function sunsubscribe($channels = []): bool
    {
        return $this->initialize_lazy_object()->sunsubscribe(...\func_get_args());
    }
    public function swapdb($index1, $index2): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->swapdb(...\func_get_args());
    }
    public function time(): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->time(...\func_get_args());
    }
    public function timeout(): false|float
    {
        return $this->initialize_lazy_object()->timeout(...\func_get_args());
    }
    public function touch($key_or_array, ...$more_keys): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->touch(...\func_get_args());
    }
    public function ttl($key): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->ttl(...\func_get_args());
    }
    public function type($key): \Relay\Relay|bool|int|string
    {
        return $this->initialize_lazy_object()->type(...\func_get_args());
    }
    public function unlink(...$keys): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->unlink(...\func_get_args());
    }
    public function unsubscribe($channels = []): bool
    {
        return $this->initialize_lazy_object()->unsubscribe(...\func_get_args());
    }
    public function unwatch(): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->unwatch(...\func_get_args());
    }
    public function vadd($key, $values, $element, $options = null): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->vadd(...\func_get_args());
    }
    public function vcard($key): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->vcard(...\func_get_args());
    }
    public function vdim($key): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->vdim(...\func_get_args());
    }
    public function vemb($key, $element, $raw = false): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->vemb(...\func_get_args());
    }
    public function vgetattr($key, $element, $raw = false): \Relay\Relay|array|false|string
    {
        return $this->initialize_lazy_object()->vgetattr(...\func_get_args());
    }
    public function vinfo($key): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->vinfo(...\func_get_args());
    }
    public function vismember($key, $element): \Relay\Relay|bool
    {
        return $this->initialize_lazy_object()->vismember(...\func_get_args());
    }
    public function vlinks($key, $element, $withscores): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->vlinks(...\func_get_args());
    }
    public function vrandmember($key, $count = 0): \Relay\Relay|array|false|string
    {
        return $this->initialize_lazy_object()->vrandmember(...\func_get_args());
    }
    public function vrange($key, $min, $max, $count = -1): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->vrange(...\func_get_args());
    }
    public function vrem($key, $element): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->vrem(...\func_get_args());
    }
    public function vsetattr($key, $element, $attributes): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->vsetattr(...\func_get_args());
    }
    public function vsim($key, $member, $options = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->vsim(...\func_get_args());
    }
    public function wait($replicas, $timeout): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->wait(...\func_get_args());
    }
    public function waitaof($numlocal, $numremote, $timeout): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->waitaof(...\func_get_args());
    }
    public function watch($key, ...$other_keys): \Relay\Relay|bool|string
    {
        return $this->initialize_lazy_object()->watch(...\func_get_args());
    }
    public function xack($key, $group, $ids): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->xack(...\func_get_args());
    }
    public function xackdel($key, $group, $ids, $mode = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->xackdel(...\func_get_args());
    }
    public function xadd($key, $id, $values, $maxlen = 0, $approx = false, $nomkstream = false): \Relay\Relay|false|null|string
    {
        return $this->initialize_lazy_object()->xadd(...\func_get_args());
    }
    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \Relay\Relay|array|bool
    {
        return $this->initialize_lazy_object()->xautoclaim(...\func_get_args());
    }
    public function xclaim($key, $group, $consumer, $min_idle, $ids, $options): \Relay\Relay|array|bool
    {
        return $this->initialize_lazy_object()->xclaim(...\func_get_args());
    }
    public function xdel($key, $ids): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->xdel(...\func_get_args());
    }
    public function xdelex($key, $ids, $mode = null): \Relay\Relay|array|false
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
    public function xlen($key): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->xlen(...\func_get_args());
    }
    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null, $idle = 0): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->xpending(...\func_get_args());
    }
    public function xrange($key, $start, $end, $count = -1): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->xrange(...\func_get_args());
    }
    public function xread($streams, $count = -1, $block = -1): \Relay\Relay|array|bool|null
    {
        return $this->initialize_lazy_object()->xread(...\func_get_args());
    }
    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \Relay\Relay|array|bool|null
    {
        return $this->initialize_lazy_object()->xreadgroup(...\func_get_args());
    }
    public function xrevrange($key, $end, $start, $count = -1): \Relay\Relay|array|bool
    {
        return $this->initialize_lazy_object()->xrevrange(...\func_get_args());
    }
    public function xtrim($key, $threshold, $approx = false, $minid = false, $limit = -1): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->xtrim(...\func_get_args());
    }
    public function zadd($key, ...$args): mixed
    {
        return $this->initialize_lazy_object()->zadd(...\func_get_args());
    }
    public function zcard($key): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->zcard(...\func_get_args());
    }
    public function zcount($key, $min, $max): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->zcount(...\func_get_args());
    }
    public function zdiff($keys, $options = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->zdiff(...\func_get_args());
    }
    public function zdiffstore($dst, $keys): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->zdiffstore(...\func_get_args());
    }
    public function zincrby($key, $score, $mem): \Relay\Relay|false|float
    {
        return $this->initialize_lazy_object()->zincrby(...\func_get_args());
    }
    public function zinter($keys, $weights = null, $options = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->zinter(...\func_get_args());
    }
    public function zintercard($keys, $limit = -1): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->zintercard(...\func_get_args());
    }
    public function zinterstore($dst, $keys, $weights = null, $options = null): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->zinterstore(...\func_get_args());
    }
    public function zlexcount($key, $min, $max): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->zlexcount(...\func_get_args());
    }
    public function zmpop($keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return $this->initialize_lazy_object()->zmpop(...\func_get_args());
    }
    public function zmscore($key, ...$mems): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->zmscore(...\func_get_args());
    }
    public function zpopmax($key, $count = 1): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->zpopmax(...\func_get_args());
    }
    public function zpopmin($key, $count = 1): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->zpopmin(...\func_get_args());
    }
    public function zrandmember($key, $options = null): mixed
    {
        return $this->initialize_lazy_object()->zrandmember(...\func_get_args());
    }
    public function zrange($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->zrange(...\func_get_args());
    }
    public function zrangebylex($key, $min, $max, $offset = -1, $count = -1): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->zrangebylex(...\func_get_args());
    }
    public function zrangebyscore($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->zrangebyscore(...\func_get_args());
    }
    public function zrangestore($dst, $src, $start, $end, $options = null): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->zrangestore(...\func_get_args());
    }
    public function zrank($key, $rank, $withscore = false): \Relay\Relay|array|false|int|null
    {
        return $this->initialize_lazy_object()->zrank(...\func_get_args());
    }
    public function zrem($key, ...$args): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->zrem(...\func_get_args());
    }
    public function zremrangebylex($key, $min, $max): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->zremrangebylex(...\func_get_args());
    }
    public function zremrangebyrank($key, $start, $end): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->zremrangebyrank(...\func_get_args());
    }
    public function zremrangebyscore($key, $min, $max): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->zremrangebyscore(...\func_get_args());
    }
    public function zrevrange($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->zrevrange(...\func_get_args());
    }
    public function zrevrangebylex($key, $max, $min, $offset = -1, $count = -1): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->zrevrangebylex(...\func_get_args());
    }
    public function zrevrangebyscore($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->zrevrangebyscore(...\func_get_args());
    }
    public function zrevrank($key, $rank, $withscore = false): \Relay\Relay|array|false|int|null
    {
        return $this->initialize_lazy_object()->zrevrank(...\func_get_args());
    }
    public function zscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return $this->initialize_lazy_object()->zscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }
    public function zscore($key, $member): \Relay\Relay|false|float|null
    {
        return $this->initialize_lazy_object()->zscore(...\func_get_args());
    }
    public function zunion($keys, $weights = null, $options = null): \Relay\Relay|array|false
    {
        return $this->initialize_lazy_object()->zunion(...\func_get_args());
    }
    public function zunionstore($dst, $keys, $weights = null, $options = null): \Relay\Relay|false|int
    {
        return $this->initialize_lazy_object()->zunionstore(...\func_get_args());
    }
}