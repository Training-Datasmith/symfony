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
namespace Symfony\Component\Cache\Messenger;

use Symfony\Component\Cache\Adapter\Adapter_Interface;
use Symfony\Component\Cache\Cache_Item;
use Symfony\Component\Dependency_Injection\Reverse_Container;
/**
 * Conveys a cached value that needs to be computed.
 */
final readonly class Early_Expiration_Message
{
    public static function create(Reverse_Container $reverse_container, callable $callback, Cache_Item $item, Adapter_Interface $pool): ?self
    {
        try {
            $item = clone $item;
            $item->set(null);
        } catch (\Exception) {
            return null;
        }
        $pool = $reverse_container->get_id($pool);
        if ($callback instanceof \Closure && !($r = new \ReflectionFunction($callback))->is_anonymous()) {
            $callback = [$r->get_closure_this() ?? $r->get_closure_called_class()?->name, $r->name];
            $callback[0] ?: $callback = $r->name;
        }
        if (\is_object($callback)) {
            if (null === $id = $reverse_container->get_id($callback)) {
                return null;
            }
            $callback = '@' . $id;
        } elseif (!\is_array($callback)) {
            $callback = (string) $callback;
        } elseif (!\is_object($callback[0])) {
            $callback = [(string) $callback[0], (string) $callback[1]];
        } else {
            if (null === $id = $reverse_container->get_id($callback[0])) {
                return null;
            }
            $callback = ['@' . $id, (string) $callback[1]];
        }
        return new self($item, $pool, $callback);
    }
    public function get_item(): Cache_Item
    {
        return $this->item;
    }
    public function get_pool(): string
    {
        return $this->pool;
    }
    /**
     * @return string|string[]
     */
    public function get_callback(): string|array
    {
        return $this->callback;
    }
    public function find_pool(Reverse_Container $reverse_container): Adapter_Interface
    {
        return $reverse_container->get_service($this->pool);
    }
    public function find_callback(Reverse_Container $reverse_container): callable
    {
        if (\is_string($callback = $this->callback)) {
            return '@' === $callback[0] ? $reverse_container->get_service(substr($callback, 1)) : $callback;
        }
        if ('@' === $callback[0][0]) {
            $callback[0] = $reverse_container->get_service(substr((string) $callback[0], 1));
        }
        return $callback;
    }
    private function __construct(private Cache_Item $item, private string $pool, private string|array $callback)
    {
    }
}