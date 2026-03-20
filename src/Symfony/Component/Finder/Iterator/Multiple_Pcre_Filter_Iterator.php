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
 * MultiplePcreFilterIterator filters files using patterns (regexps, globs or strings).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @template-covariant TKey
 * @template-covariant TValue
 *
 * @extends \FilterIterator<TKey, TValue>
 */
abstract class Multiple_Pcre_Filter_Iterator extends \Filter_Iterator
{
    protected array $match_regexps = [];
    protected array $no_match_regexps = [];
    /**
     * @param \Iterator<TKey, TValue> $iterator        The Iterator to filter
     * @param string[]                $matchPatterns   An array of patterns that need to match
     * @param string[]                $noMatchPatterns An array of patterns that need to not match
     */
    public function __construct(\Iterator $iterator, array $match_patterns, array $no_match_patterns)
    {
        foreach ($match_patterns as $pattern) {
            $this->match_regexps[] = $this->to_regex($pattern);
        }
        foreach ($no_match_patterns as $pattern) {
            $this->no_match_regexps[] = $this->to_regex($pattern);
        }
        parent::__construct($iterator);
    }
    /**
     * Checks whether the string is accepted by the regex filters.
     *
     * If there is no regexps defined in the class, this method will accept the string.
     * Such case can be handled by child classes before calling the method if they want to
     * apply a different behavior.
     */
    protected function is_accepted(string $string): bool
    {
        // should at least not match one rule to exclude
        foreach ($this->no_match_regexps as $regex) {
            if (preg_match($regex, $string)) {
                return false;
            }
        }
        // should at least match one rule
        if ($this->match_regexps) {
            foreach ($this->match_regexps as $regex) {
                if (preg_match($regex, $string)) {
                    return true;
                }
            }
            return false;
        }
        // If there is no match rules, the file is accepted
        return true;
    }
    /**
     * Checks whether the string is a regex.
     */
    protected function is_regex(string $str): bool
    {
        $available_modifiers = 'imsxuADUn';
        if (preg_match('/^(.{3,}?)[' . $available_modifiers . ']*$/', $str, $m)) {
            $start = substr($m[1], 0, 1);
            $end = substr($m[1], -1);
            if ($start === $end) {
                return !preg_match('/[*?[:alnum:] \\\\]/', $start);
            }
            foreach ([['{', '}'], ['(', ')'], ['[', ']'], ['<', '>']] as $delimiters) {
                if ($start === $delimiters[0] && $end === $delimiters[1]) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Converts string into regexp.
     */
    abstract protected function to_regex(string $str): string;
}