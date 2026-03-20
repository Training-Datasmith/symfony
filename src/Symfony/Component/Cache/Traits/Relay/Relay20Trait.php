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
    trait Relay20Trait
    {
        public function _digest($value): string
        {
            return $this->initialize_lazy_object()->_digest(...\func_get_args());
        }
        public function delex($key, $options = null): \Relay\Relay|false|int
        {
            return $this->initialize_lazy_object()->delex(...\func_get_args());
        }
        public function digest($key): \Relay\Relay|false|string|null
        {
            return $this->initialize_lazy_object()->digest(...\func_get_args());
        }
        public function msetex($kvals, $ttl = null): \Relay\Relay|false|int
        {
            return $this->initialize_lazy_object()->msetx(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait Relay20Trait
    {
    }
}