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
 * CSS selector tokenizer escaping applier.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Tokenizer_Escaping
{
    public function __construct(private readonly Tokenizer_Patterns $patterns)
    {
    }
    public function escape_unicode(string $value): string
    {
        $value = $this->replace_unicode_sequences($value);
        return preg_replace($this->patterns->get_simple_escape_pattern(), '$1', $value);
    }
    public function escape_unicode_and_new_line(string $value): string
    {
        $value = preg_replace($this->patterns->get_new_line_escape_pattern(), '', $value);
        return $this->escape_unicode($value);
    }
    private function replace_unicode_sequences(string $value): string
    {
        return preg_replace_callback($this->patterns->get_unicode_escape_pattern(), static function ($match): string {
            $c = hexdec((string) $match[1]);
            if (0x80 > $c %= 0x200000) {
                return \chr($c);
            }
            if (0x800 > $c) {
                return \chr(0xc0 | $c >> 6) . \chr(0x80 | $c & 0x3f);
            }
            if (0x10000 > $c) {
                return \chr(0xe0 | $c >> 12) . \chr(0x80 | $c >> 6 & 0x3f) . \chr(0x80 | $c & 0x3f);
            }
            return '';
        }, $value);
    }
}