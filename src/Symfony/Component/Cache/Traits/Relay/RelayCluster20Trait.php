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
namespace Symfony\Component\Cache\Traits\Relay;

if (version_compare(phpversion('relay'), '0.20.0', '>=')) {
    /**
     * @internal
     */
    trait Relay_Cluster20trait
    {
        public function _digest($value): string
        {
            return $this->initialize_lazy_object()->_digest(...\func_get_args());
        }
        public function delex($key, $options = null): \Relay\Cluster|false|int
        {
            return $this->initialize_lazy_object()->delex(...\func_get_args());
        }
        public function digest($key): \Relay\Cluster|false|string|null
        {
            return $this->initialize_lazy_object()->digest(...\func_get_args());
        }
        public function wait($key_or_address, $replicas, $timeout): \Relay\Cluster|false|int
        {
            return $this->initialize_lazy_object()->digest(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait Relay_Cluster20trait
    {
    }
}