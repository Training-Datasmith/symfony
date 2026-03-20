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
namespace Symfony\Component\Html_Sanitizer\Text_Sanitizer;

/**
 * @internal
 */
final class String_Sanitizer
{
    private const REPLACEMENTS = [
        // "&#34;" is shorter than "&quot;"
        '&quot;' => '&#34;',
        // Fix several potential issues in how browsers interpret attribute values
        '+' => '&#43;',
        '=' => '&#61;',
        '@' => '&#64;',
        '`' => '&#96;',
        // Some DB engines will transform UTF8 full-width characters with
        // their classical version if the data is saved in a non-UTF8 field
        '＜' => '&#xFF1C;',
        '＞' => '&#xFF1E;',
        '＋' => '&#xFF0B;',
        '＝' => '&#xFF1D;',
        '＠' => '&#xFF20;',
        '｀' => '&#xFF40;',
    ];
    /**
     * Applies a transformation to lowercase following W3C HTML Standard.
     *
     * @see https://w3c.github.io/html-reference/terminology.html#case-insensitive
     */
    public static function html_lower(string $string): string
    {
        return strtolower($string);
    }
    /**
     * Encodes the HTML entities in the given string for safe injection in a document's DOM.
     */
    public static function encode_html_entities(string $string): string
    {
        return strtr(htmlspecialchars($string, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'), self::REPLACEMENTS);
    }
}