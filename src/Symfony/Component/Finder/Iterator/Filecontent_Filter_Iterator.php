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
 * FilecontentFilterIterator filters files by their contents using patterns (regexps or strings).
 *
 * @author Fabien Potencier  <fabien@symfony.com>
 * @author Włodzimierz Gajda <gajdaw@gajdaw.pl>
 *
 * @extends MultiplePcreFilterIterator<string, SplFileInfo>
 */
class Filecontent_Filter_Iterator extends Multiple_Pcre_Filter_Iterator
{
    /**
     * Filters the iterator values.
     */
    public function accept(): bool
    {
        if (!$this->match_regexps && !$this->no_match_regexps) {
            return true;
        }
        $fileinfo = $this->current();
        if ($fileinfo->is_dir() || !$fileinfo->is_readable()) {
            return false;
        }
        $content = $fileinfo->get_contents();
        if (!$content) {
            return false;
        }
        return $this->is_accepted($content);
    }
    /**
     * Converts string to regexp if necessary.
     *
     * @param string $str Pattern: string or regexp
     */
    protected function to_regex(string $str): string
    {
        return $this->is_regex($str) ? $str : '/' . preg_quote($str, '/') . '/';
    }
}