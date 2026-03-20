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

use Symfony\Component\Finder\Comparator\Date_Comparator;
/**
 * DateRangeFilterIterator filters out files that are not in the given date range (last modified dates).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @extends \FilterIterator<string, \SplFileInfo>
 */
class Date_Range_Filter_Iterator extends \Filter_Iterator
{
    /**
     * @param \Iterator<string, \SplFileInfo> $iterator
     * @param DateComparator[]                $comparators
     */
    public function __construct(\Iterator $iterator, private readonly array $comparators)
    {
        parent::__construct($iterator);
    }
    /**
     * Filters the iterator values.
     */
    public function accept(): bool
    {
        $fileinfo = $this->current();
        if (!file_exists($fileinfo->get_pathname())) {
            return false;
        }
        $filedate = $fileinfo->get_m_time();
        foreach ($this->comparators as $compare) {
            if (!$compare->test($filedate)) {
                return false;
            }
        }
        return true;
    }
}