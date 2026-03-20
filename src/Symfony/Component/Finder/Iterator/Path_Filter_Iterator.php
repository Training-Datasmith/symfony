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

use Symfony\Component\Finder\Spl_File_Info;
/**
 * PathFilterIterator filters files by path patterns (e.g. some/special/dir).
 *
 * @author Fabien Potencier  <fabien@symfony.com>
 * @author Włodzimierz Gajda <gajdaw@gajdaw.pl>
 *
 * @extends MultiplePcreFilterIterator<string, SplFileInfo>
 */
class Path_Filter_Iterator extends Multiple_Pcre_Filter_Iterator
{
    /**
     * Filters the iterator values.
     */
    public function accept(): bool
    {
        $filename = $this->current()->get_relative_pathname();
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $filename = str_replace('\\', '/', $filename);
        }
        return $this->is_accepted($filename);
    }
    /**
     * Converts strings to regexp.
     *
     * PCRE patterns are left unchanged.
     *
     * Default conversion:
     *     'lorem/ipsum/dolor' ==>  'lorem\/ipsum\/dolor/'
     *
     * Use only / as directory separator (on Windows also).
     *
     * @param string $str Pattern: regexp or dirname
     */
    protected function to_regex(string $str): string
    {
        return $this->is_regex($str) ? $str : '/' . preg_quote($str, '/') . '/';
    }
}