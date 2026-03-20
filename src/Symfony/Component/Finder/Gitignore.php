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
 * Gitignore matches against text.
 *
 * @author Michael Voříšek <vorismi3@fel.cvut.cz>
 * @author Ahmed Abdou <mail@ahmd.io>
 */
class Gitignore
{
    /**
     * Returns a regexp which is the equivalent of the gitignore pattern.
     *
     * Format specification: https://git-scm.com/docs/gitignore#_pattern_format
     */
    public static function to_regex(string $gitignore_file_content): string
    {
        return self::build_regex($gitignore_file_content, false);
    }
    public static function to_regex_matching_negated_patterns(string $gitignore_file_content): string
    {
        return self::build_regex($gitignore_file_content, true);
    }
    private static function build_regex(string $gitignore_file_content, bool $inverted): string
    {
        $gitignore_file_content = preg_replace('~(?<!\\\\)#[^\n\r]*~', '', $gitignore_file_content);
        $gitignore_lines = preg_split('~\r\n?|\n~', (string) $gitignore_file_content);
        $res = self::line_to_regex('');
        foreach ($gitignore_lines as $line) {
            $line = preg_replace('~(?<!\\\\)[ \t]+$~', '', $line);
            if (str_starts_with((string) $line, '!')) {
                $line = substr((string) $line, 1);
                $is_negative = true;
            } else {
                $is_negative = false;
            }
            if ('' !== $line) {
                if ($is_negative xor $inverted) {
                    $res = '(?!' . self::line_to_regex($line) . '$)' . $res;
                } else {
                    $res = '(?:' . $res . '|' . self::line_to_regex($line) . ')';
                }
            }
        }
        return '~^(?:' . $res . ')~s';
    }
    private static function line_to_regex(string $gitignore_line): string
    {
        if ('' === $gitignore_line) {
            return '$f';
            // always false
        }
        $slash_pos = strpos($gitignore_line, '/');
        if (false !== $slash_pos && \strlen($gitignore_line) - 1 !== $slash_pos) {
            if (0 === $slash_pos) {
                $gitignore_line = substr($gitignore_line, 1);
            }
            $is_absolute = true;
        } else {
            $is_absolute = false;
        }
        $regex = preg_quote(str_replace('\\', '', $gitignore_line), '~');
        $regex = preg_replace_callback('~\\\\\\[((?:\\\\!)?)([^\[\]]*)\\\\\\]~', static fn(array $matches): string => '[' . ('' !== $matches[1] ? '^' : '') . str_replace('\-', '-', $matches[2]) . ']', $regex);
        $regex = preg_replace('~(?:(?:\\\\\\*){2,}(/?))+~', '(?:(?:(?!//).(?<!//))+$1)?', (string) $regex);
        $regex = preg_replace('~\\\\\\*~', '[^/]*', (string) $regex);
        $regex = preg_replace('~\\\\\\?~', '[^/]', (string) $regex);
        return ($is_absolute ? '' : '(?:[^/]+/)*') . $regex . (!str_ends_with($gitignore_line, '/') ? '(?:$|/)' : '');
    }
}