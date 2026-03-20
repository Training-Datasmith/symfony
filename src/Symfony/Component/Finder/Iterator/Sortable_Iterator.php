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
 * SortableIterator applies a sort on a given Iterator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @implements \IteratorAggregate<string, \SplFileInfo>
 */
class Sortable_Iterator implements \IteratorAggregate
{
    public const SORT_BY_NONE = 0;
    public const SORT_BY_NAME = 1;
    public const SORT_BY_TYPE = 2;
    public const SORT_BY_ACCESSED_TIME = 3;
    public const SORT_BY_CHANGED_TIME = 4;
    public const SORT_BY_MODIFIED_TIME = 5;
    public const SORT_BY_NAME_NATURAL = 6;
    public const SORT_BY_NAME_CASE_INSENSITIVE = 7;
    public const SORT_BY_NAME_NATURAL_CASE_INSENSITIVE = 8;
    public const SORT_BY_EXTENSION = 9;
    public const SORT_BY_SIZE = 10;
    private \Closure|int $sort;
    /**
     * @param \Traversable<string, \SplFileInfo> $iterator
     * @param int|callable                       $sort     The sort type (SORT_BY_NAME, SORT_BY_TYPE, or a PHP callback)
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(private readonly \Traversable $iterator, int|callable $sort, bool $reverse_order = false)
    {
        $order = $reverse_order ? -1 : 1;
        if (self::SORT_BY_NAME === $sort) {
            $this->sort = static fn(\Spl_File_Info $a, \Spl_File_Info $b): int => $order * strcmp($a->get_real_path() ?: $a->get_pathname(), $b->get_real_path() ?: $b->get_pathname());
        } elseif (self::SORT_BY_NAME_NATURAL === $sort) {
            $this->sort = static fn(\Spl_File_Info $a, \Spl_File_Info $b): int => $order * strnatcmp($a->get_real_path() ?: $a->get_pathname(), $b->get_real_path() ?: $b->get_pathname());
        } elseif (self::SORT_BY_NAME_CASE_INSENSITIVE === $sort) {
            $this->sort = static fn(\Spl_File_Info $a, \Spl_File_Info $b): int => $order * strcasecmp($a->get_real_path() ?: $a->get_pathname(), $b->get_real_path() ?: $b->get_pathname());
        } elseif (self::SORT_BY_NAME_NATURAL_CASE_INSENSITIVE === $sort) {
            $this->sort = static fn(\Spl_File_Info $a, \Spl_File_Info $b): int => $order * strnatcasecmp($a->get_real_path() ?: $a->get_pathname(), $b->get_real_path() ?: $b->get_pathname());
        } elseif (self::SORT_BY_TYPE === $sort) {
            $this->sort = static function (\Spl_File_Info $a, \Spl_File_Info $b) use ($order): int {
                if ($a->is_dir() && $b->is_file()) {
                    return -$order;
                }
                if ($a->is_file() && $b->is_dir()) {
                    return $order;
                }
                return $order * strcmp($a->get_real_path() ?: $a->get_pathname(), $b->get_real_path() ?: $b->get_pathname());
            };
        } elseif (self::SORT_BY_ACCESSED_TIME === $sort) {
            $this->sort = static fn(\Spl_File_Info $a, \Spl_File_Info $b): int|float => $order * ($a->get_a_time() - $b->get_a_time());
        } elseif (self::SORT_BY_CHANGED_TIME === $sort) {
            $this->sort = static fn(\Spl_File_Info $a, \Spl_File_Info $b): int|float => $order * ($a->get_c_time() - $b->get_c_time());
        } elseif (self::SORT_BY_MODIFIED_TIME === $sort) {
            $this->sort = static fn(\Spl_File_Info $a, \Spl_File_Info $b): int|float => $order * ($a->get_m_time() - $b->get_m_time());
        } elseif (self::SORT_BY_EXTENSION === $sort) {
            $this->sort = static fn(\Spl_File_Info $a, \Spl_File_Info $b): int => $order * strnatcmp($a->get_extension(), $b->get_extension());
        } elseif (self::SORT_BY_SIZE === $sort) {
            $this->sort = static fn(\Spl_File_Info $a, \Spl_File_Info $b): int|float => $order * ($a->get_size() - $b->get_size());
        } elseif (self::SORT_BY_NONE === $sort) {
            $this->sort = $order;
        } elseif (\is_callable($sort)) {
            $this->sort = $reverse_order ? static fn(\Spl_File_Info $a, \Spl_File_Info $b): int|float => -$sort($a, $b) : $sort(...);
        } else {
            throw new \InvalidArgumentException('The SortableIterator takes a PHP callable or a valid built-in sort algorithm as an argument.');
        }
    }
    public function getIterator(): \Traversable
    {
        if (1 === $this->sort) {
            yield from $this->iterator;
            return;
        }
        $keys = $values = [];
        foreach ($this->iterator as $key => $value) {
            $keys[] = $key;
            $values[] = $value;
        }
        if (-1 === $this->sort) {
            for ($i = \count($values) - 1; $i >= 0; --$i) {
                yield $keys[$i] => $values[$i];
            }
            return;
        }
        uasort($values, $this->sort);
        foreach ($values as $i => $v) {
            yield $keys[$i] => $v;
        }
    }
}