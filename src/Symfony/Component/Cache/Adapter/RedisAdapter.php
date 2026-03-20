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
namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Marshaller\Marshaller_Interface;
use Symfony\Component\Cache\Traits\Redis_Trait;
class Redis_Adapter extends Abstract_Adapter
{
    use Redis_Trait;
    public function __construct(\Redis|\Redis_Array|\Redis_Cluster|\Predis\Client_Interface|\Relay\Relay|\Relay\Cluster $redis, string $namespace = '', int $default_lifetime = 0, ?Marshaller_Interface $marshaller = null)
    {
        $this->init($redis, $namespace, $default_lifetime, $marshaller);
    }
}