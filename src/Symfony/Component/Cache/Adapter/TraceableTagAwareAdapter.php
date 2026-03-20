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

use Symfony\Contracts\Cache\Tag_Aware_Cache_Interface;
/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class Traceable_Tag_Aware_Adapter extends Traceable_Adapter implements Tag_Aware_Adapter_Interface, Tag_Aware_Cache_Interface
{
    public function __construct(Tag_Aware_Adapter_Interface $pool, ?\Closure $disabled = null)
    {
        parent::__construct($pool, $disabled);
    }
    public function invalidate_tags(array $tags): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->invalidate_tags($tags);
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result = $this->pool->invalidate_tags($tags);
        } finally {
            $event->end = microtime(true);
        }
    }
}