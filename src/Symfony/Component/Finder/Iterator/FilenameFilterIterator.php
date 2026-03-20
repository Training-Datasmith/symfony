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

use Symfony\Component\Finder\Glob;
/**
 * FilenameFilterIterator filters files by patterns (a regexp, a glob, or a string).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @extends MultiplePcreFilterIterator<string, \SplFileInfo>
 */
class Filename_Filter_Iterator extends Multiple_Pcre_Filter_Iterator
{
    /**
     * Filters the iterator values.
     */
    public function accept(): bool
    {
        return $this->is_accepted($this->current()->get_filename());
    }
    /**
     * Converts glob to regexp.
     *
     * PCRE patterns are left unchanged.
     * Glob strings are transformed with Glob::toRegex().
     *
     * @param string $str Pattern: glob or regexp
     */
    protected function to_regex(string $str): string
    {
        return $this->is_regex($str) ? $str : Glob::to_regex($str);
    }
}