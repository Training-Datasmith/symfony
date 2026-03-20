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

use Psr\Simple_Cache\Cache_Interface;
use Symfony\Component\Cache\Pruneable_Interface;
use Symfony\Component\Cache\Resettable_Interface;
use Symfony\Component\Cache\Traits\Proxy_Trait;
/**
 * Turns a PSR-16 cache into a PSR-6 one.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Psr16Adapter extends Abstract_Adapter implements Pruneable_Interface, Resettable_Interface
{
    use Proxy_Trait;
    /**
     * @internal
     */
    protected const NS_SEPARATOR = '_';
    private object $miss;
    public function __construct(Cache_Interface $pool, string $namespace = '', int $default_lifetime = 0)
    {
        parent::__construct($namespace, $default_lifetime);
        $this->pool = $pool;
        $this->miss = new \stdClass();
    }
    protected function do_fetch(array $ids): iterable
    {
        foreach ($this->pool->get_multiple($ids, $this->miss) as $key => $value) {
            if ($this->miss !== $value) {
                yield $key => $value;
            }
        }
    }
    protected function do_have(string $id): bool
    {
        return $this->pool->has($id);
    }
    protected function do_clear(string $namespace): bool
    {
        return $this->pool->clear();
    }
    protected function do_delete(array $ids): bool
    {
        return $this->pool->delete_multiple($ids);
    }
    protected function do_save(array $values, int $lifetime): array|bool
    {
        return $this->pool->set_multiple($values, 0 === $lifetime ? null : $lifetime);
    }
}