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
namespace Symfony\Component\Finder;

/**
 * Glob matches globbing patterns against text.
 *
 *     if match_glob("foo.*", "foo.bar") echo "matched\n";
 *
 *     // prints foo.bar and foo.baz
 *     $regex = glob_to_regex("foo.*");
 *     for (['foo.bar', 'foo.baz', 'foo', 'bar'] as $t)
 *     {
 *         if (/$regex/) echo "matched: $car\n";
 *     }
 *
 * Glob implements glob(3) style matching that can be used to match
 * against text, rather than fetching names from a filesystem.
 *
 * Based on the Perl Text::Glob module.
 *
 * @author Fabien Potencier <fabien@symfony.com> PHP port
 * @author     Richard Clamp <richardc@unixbeard.net> Perl version
 * @copyright  2004-2005 Fabien Potencier <fabien@symfony.com>
 * @copyright  2002 Richard Clamp <richardc@unixbeard.net>
 */
class Glob
{
    /**
     * Returns a regexp which is the equivalent of the glob pattern.
     */
    public static function to_regex(string $glob, bool $strict_leading_dot = true, bool $strict_wildcard_slash = true, string $delimiter = '#'): string
    {
        $first_byte = true;
        $escaping = false;
        $in_curlies = 0;
        $regex = '';
        if ($unanchored = str_starts_with($glob, '**/')) {
            $glob = '/' . $glob;
        }
        $size_glob = \strlen($glob);
        for ($i = 0; $i < $size_glob; ++$i) {
            $car = $glob[$i];
            if ($first_byte && $strict_leading_dot && '.' !== $car) {
                $regex .= '(?=[^\.])';
            }
            $first_byte = '/' === $car;
            if ($first_byte && $strict_wildcard_slash && isset($glob[$i + 2]) && '**' === $glob[$i + 1] . $glob[$i + 2] && (!isset($glob[$i + 3]) || '/' === $glob[$i + 3])) {
                $car = '[^/]++/';
                if (!isset($glob[$i + 3])) {
                    $car .= '?';
                }
                if ($strict_leading_dot) {
                    $car = '(?=[^\.])' . $car;
                }
                $car = '/(?:' . $car . ')*';
                $i += 2 + isset($glob[$i + 3]);
                if ('/' === $delimiter) {
                    $car = str_replace('/', '\/', $car);
                }
            }
            if ($delimiter === $car || '.' === $car || '(' === $car || ')' === $car || '|' === $car || '+' === $car || '^' === $car || '$' === $car) {
                $regex .= "\\{$car}";
            } elseif ('*' === $car) {
                $regex .= $escaping ? '\*' : ($strict_wildcard_slash ? '[^/]*' : '.*');
            } elseif ('?' === $car) {
                $regex .= $escaping ? '\?' : ($strict_wildcard_slash ? '[^/]' : '.');
            } elseif ('{' === $car) {
                $regex .= $escaping ? '\{' : '(';
                if (!$escaping) {
                    ++$in_curlies;
                }
            } elseif ('}' === $car && $in_curlies) {
                $regex .= $escaping ? '}' : ')';
                if (!$escaping) {
                    --$in_curlies;
                }
            } elseif (',' === $car && $in_curlies) {
                $regex .= $escaping ? ',' : '|';
            } elseif ('\\' === $car) {
                if ($escaping) {
                    $regex .= '\\\\';
                    $escaping = false;
                } else {
                    $escaping = true;
                }
                continue;
            } else {
                $regex .= $car;
            }
            $escaping = false;
        }
        if ($unanchored) {
            $regex = substr_replace($regex, '?', 1 + ('/' === $delimiter) + ($strict_leading_dot ? \strlen('(?=[^\.])') : 0), 0);
        }
        return $delimiter . '^' . $regex . '$' . $delimiter;
    }
}