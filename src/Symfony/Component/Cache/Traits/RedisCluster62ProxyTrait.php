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

if (version_compare(phpversion('redis'), '6.2.0', '>=')) {
    /**
     * @internal
     */
    trait Redis_Cluster62proxy_Trait
    {
        public function expiremember($key, $field, $ttl, $unit = null): \Redis|false|int
        {
            return $this->initialize_lazy_object()->expiremember(...\func_get_args());
        }
        public function expirememberat($key, $field, $timestamp): \Redis|false|int
        {
            return $this->initialize_lazy_object()->expirememberat(...\func_get_args());
        }
        public function getdel($key): mixed
        {
            return $this->initialize_lazy_object()->getdel(...\func_get_args());
        }
        public function get_with_meta($key): \Redis_Cluster|array|false
        {
            return $this->initialize_lazy_object()->get_with_meta(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait Redis_Cluster62proxy_Trait
    {
    }
}