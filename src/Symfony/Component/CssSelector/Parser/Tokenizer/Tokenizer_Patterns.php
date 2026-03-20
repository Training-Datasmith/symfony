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
namespace Symfony\Component\Css_Selector\Parser\Tokenizer;

/**
 * CSS selector tokenizer patterns builder.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Tokenizer_Patterns
{
    private readonly string $unicode_escape_pattern;
    private readonly string $simple_escape_pattern;
    private readonly string $new_line_escape_pattern;
    private readonly string $escape_pattern;
    private readonly string $string_escape_pattern;
    private readonly string $non_ascii_pattern;
    private readonly string $nm_char_pattern;
    private readonly string $nm_start_pattern;
    private readonly string $identifier_pattern;
    private readonly string $hash_pattern;
    private readonly string $number_pattern;
    private readonly string $quoted_string_pattern;
    public function __construct()
    {
        $this->unicode_escape_pattern = '\\\\([0-9a-f]{1,6})(?:\r\n|[ \n\r\t\f])?';
        $this->simple_escape_pattern = '\\\\(.)';
        $this->new_line_escape_pattern = '\\\\(?:\n|\r\n|\r|\f)';
        $this->escape_pattern = $this->unicode_escape_pattern . '|\\\\[^\n\r\f0-9a-f]';
        $this->string_escape_pattern = $this->new_line_escape_pattern . '|' . $this->escape_pattern;
        $this->non_ascii_pattern = '[^\x00-\x7F]';
        $this->nm_char_pattern = '[_a-z0-9-]|' . $this->escape_pattern . '|' . $this->non_ascii_pattern;
        $this->nm_start_pattern = '[_a-z]|' . $this->escape_pattern . '|' . $this->non_ascii_pattern;
        $this->identifier_pattern = '-?(?:' . $this->nm_start_pattern . ')(?:' . $this->nm_char_pattern . ')*';
        $this->hash_pattern = '#((?:' . $this->nm_char_pattern . ')+)';
        $this->number_pattern = '[+-]?(?:[0-9]*\.[0-9]+|[0-9]+)';
        $this->quoted_string_pattern = '([^\n\r\f\\\\%s]|' . $this->string_escape_pattern . ')*';
    }
    public function get_new_line_escape_pattern(): string
    {
        return '~' . $this->new_line_escape_pattern . '~';
    }
    public function get_simple_escape_pattern(): string
    {
        return '~' . $this->simple_escape_pattern . '~';
    }
    public function get_unicode_escape_pattern(): string
    {
        return '~' . $this->unicode_escape_pattern . '~i';
    }
    public function get_identifier_pattern(): string
    {
        return '~^' . $this->identifier_pattern . '~i';
    }
    public function get_hash_pattern(): string
    {
        return '~^' . $this->hash_pattern . '~i';
    }
    public function get_number_pattern(): string
    {
        return '~^' . $this->number_pattern . '~';
    }
    public function get_quoted_string_pattern(string $quote): string
    {
        return '~^' . \sprintf($this->quoted_string_pattern, $quote) . '~i';
    }
}