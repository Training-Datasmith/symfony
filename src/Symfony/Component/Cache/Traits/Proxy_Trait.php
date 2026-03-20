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

use Symfony\Component\Cache\Pruneable_Interface;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait Proxy_Trait
{
    private object $pool;
    public function prune(): bool
    {
        return $this->pool instanceof Pruneable_Interface && $this->pool->prune();
    }
    public function reset(): void
    {
        if ($this->pool instanceof Reset_Interface) {
            $this->pool->reset();
        }
    }
}