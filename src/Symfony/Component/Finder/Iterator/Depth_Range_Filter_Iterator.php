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
namespace Symfony\Component\Finder\Iterator;

/**
 * DepthRangeFilterIterator limits the directory depth.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @template-covariant TKey
 * @template-covariant TValue
 *
 * @extends \FilterIterator<TKey, TValue>
 */
class Depth_Range_Filter_Iterator extends \Filter_Iterator
{
    /**
     * @param \RecursiveIteratorIterator<\RecursiveIterator<TKey, TValue>> $iterator The Iterator to filter
     * @param int                                                          $minDepth The min depth
     * @param int                                                          $maxDepth The max depth
     */
    public function __construct(\Recursive_Iterator_Iterator $iterator, private readonly int $min_depth = 0, int $max_depth = \PHP_INT_MAX)
    {
        $iterator->set_max_depth(\PHP_INT_MAX === $max_depth ? -1 : $max_depth);
        parent::__construct($iterator);
    }
    /**
     * Filters the iterator values.
     */
    public function accept(): bool
    {
        return $this->get_inner_iterator()->get_depth() >= $this->min_depth;
    }
}