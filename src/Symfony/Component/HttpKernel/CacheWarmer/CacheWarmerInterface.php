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
namespace Symfony\Component\Http_Kernel\Cache_Warmer;

/**
 * Interface for classes able to warm up the cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Cache_Warmer_Interface extends Warmable_Interface
{
    /**
     * Checks whether this warmer is optional or not.
     *
     * Optional warmers can be ignored on certain conditions.
     *
     * A warmer should return true if the cache can be
     * generated incrementally and on-demand.
     */
    public function is_optional(): bool;
}