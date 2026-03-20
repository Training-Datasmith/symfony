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
namespace Symfony\Component\Cache\Exception;

use Psr\Cache\Cache_Exception as Psr6CacheInterface;
use Psr\Simple_Cache\Cache_Exception as SimpleCacheInterface;
if (interface_exists(Simple_Cache_Interface::class)) {
    class Cache_Exception extends \Exception implements Psr6cache_Interface, Simple_Cache_Interface
    {
    }
} else {
    class Cache_Exception extends \Exception implements Psr6cache_Interface
    {
    }
}