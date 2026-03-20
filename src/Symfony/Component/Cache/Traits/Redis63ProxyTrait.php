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

if (version_compare(phpversion('redis'), '6.3.0', '>=')) {
    /**
     * @internal
     */
    trait Redis63proxy_Trait
    {
        public function delifeq($key, $value): \Redis|int|false
        {
            return $this->initialize_lazy_object()->delifeq(...\func_get_args());
        }
        public function hexpire($key, $ttl, $fields, $mode = null): \Redis|array|false
        {
            return $this->initialize_lazy_object()->hexpire(...\func_get_args());
        }
        public function hexpireat($key, $time, $fields, $mode = null): \Redis|array|false
        {
            return $this->initialize_lazy_object()->hexpireat(...\func_get_args());
        }
        public function hexpiretime($key, $fields): \Redis|array|false
        {
            return $this->initialize_lazy_object()->hexpiretime(...\func_get_args());
        }
        public function hgetdel($key, $fields): \Redis|array|false
        {
            return $this->initialize_lazy_object()->hgetdel(...\func_get_args());
        }
        public function hgetex($key, $fields, $expiry = null): \Redis|array|false
        {
            return $this->initialize_lazy_object()->hgetex(...\func_get_args());
        }
        public function h_get_with_meta($key, $member): mixed
        {
            return $this->initialize_lazy_object()->h_get_with_meta(...\func_get_args());
        }
        public function hpersist($key, $fields): \Redis|array|false
        {
            return $this->initialize_lazy_object()->hpersist(...\func_get_args());
        }
        public function hpexpire($key, $ttl, $fields, $mode = null): \Redis|array|false
        {
            return $this->initialize_lazy_object()->hpexpire(...\func_get_args());
        }
        public function hpexpireat($key, $mstime, $fields, $mode = null): \Redis|array|false
        {
            return $this->initialize_lazy_object()->hpexpireat(...\func_get_args());
        }
        public function hpexpiretime($key, $fields): \Redis|array|false
        {
            return $this->initialize_lazy_object()->hpexpiretime(...\func_get_args());
        }
        public function hpttl($key, $fields): \Redis|array|false
        {
            return $this->initialize_lazy_object()->hpttl(...\func_get_args());
        }
        public function hsetex($key, $fields, $expiry = null): \Redis|int|false
        {
            return $this->initialize_lazy_object()->hsetex(...\func_get_args());
        }
        public function httl($key, $fields): \Redis|array|false
        {
            return $this->initialize_lazy_object()->httl(...\func_get_args());
        }
        public function vadd($key, $values, $element, $options = null): \Redis|int|false
        {
            return $this->initialize_lazy_object()->vadd(...\func_get_args());
        }
        public function vcard($key): \Redis|int|false
        {
            return $this->initialize_lazy_object()->vcard(...\func_get_args());
        }
        public function vdim($key): \Redis|int|false
        {
            return $this->initialize_lazy_object()->vdim(...\func_get_args());
        }
        public function vemb($key, $member, $raw = false): \Redis|array|false
        {
            return $this->initialize_lazy_object()->vemb(...\func_get_args());
        }
        public function vgetattr($key, $member, $decode = true): \Redis|array|string|false
        {
            return $this->initialize_lazy_object()->vgetattr(...\func_get_args());
        }
        public function vinfo($key): \Redis|array|false
        {
            return $this->initialize_lazy_object()->vinfo(...\func_get_args());
        }
        public function vismember($key, $member): \Redis|bool
        {
            return $this->initialize_lazy_object()->vismember(...\func_get_args());
        }
        public function vlinks($key, $member, $withscores = false): \Redis|array|false
        {
            return $this->initialize_lazy_object()->vlinks(...\func_get_args());
        }
        public function vrandmember($key, $count = 0): \Redis|array|string|false
        {
            return $this->initialize_lazy_object()->vrandmember(...\func_get_args());
        }
        public function vrange($key, $min, $max, $count = -1): \Redis|array|false
        {
            return $this->initialize_lazy_object()->vrange(...\func_get_args());
        }
        public function vrem($key, $member): \Redis|int|false
        {
            return $this->initialize_lazy_object()->vrem(...\func_get_args());
        }
        public function vsetattr($key, $member, $attributes): \Redis|int|false
        {
            return $this->initialize_lazy_object()->vsetattr(...\func_get_args());
        }
        public function vsim($key, $member, $options = null): \Redis|array|false
        {
            return $this->initialize_lazy_object()->vsim(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait Redis63proxy_Trait
    {
    }
}