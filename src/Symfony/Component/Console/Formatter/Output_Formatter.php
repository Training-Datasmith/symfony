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
namespace Symfony\Component\Console\Formatter;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\Helper;
use function Symfony\Component\String\b;
/**
 * Formatter class for console output.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class Output_Formatter implements Wrappable_Output_Formatter_Interface
{
    private array $styles = [];
    private Output_Formatter_Style_Stack $style_stack;
    public function __clone()
    {
        $this->style_stack = clone $this->style_stack;
        foreach ($this->styles as $key => $value) {
            $this->styles[$key] = clone $value;
        }
    }
    /**
     * Escapes "<" and ">" special chars in given text.
     */
    public static function escape(string $text): string
    {
        $text = preg_replace('/([^\\\\]|^)([<>])/', '$1\\\\$2', $text);
        return self::escape_trailing_backslash($text);
    }
    /**
     * Escapes trailing "\" in given text.
     *
     * @internal
     */
    public static function escape_trailing_backslash(string $text): string
    {
        if (str_ends_with($text, '\\')) {
            $len = \strlen($text);
            $text = rtrim($text, '\\');
            $text = str_replace("\x00", '', $text);
            $text .= str_repeat("\x00", $len - \strlen($text));
        }
        return $text;
    }
    /**
     * Initializes console output formatter.
     *
     * @param OutputFormatterStyleInterface[] $styles Array of "name => FormatterStyle" instances
     */
    public function __construct(private bool $decorated = false, array $styles = [])
    {
        $this->set_style('error', new Output_Formatter_Style('white', 'red'));
        $this->set_style('info', new Output_Formatter_Style('green'));
        $this->set_style('comment', new Output_Formatter_Style('yellow'));
        $this->set_style('question', new Output_Formatter_Style('black', 'cyan'));
        foreach ($styles as $name => $style) {
            $this->set_style($name, $style);
        }
        $this->style_stack = new Output_Formatter_Style_Stack();
    }
    public function set_decorated(bool $decorated): void
    {
        $this->decorated = $decorated;
    }
    public function is_decorated(): bool
    {
        return $this->decorated;
    }
    public function set_style(string $name, Output_Formatter_Style_Interface $style): void
    {
        $this->styles[strtolower($name)] = $style;
    }
    public function has_style(string $name): bool
    {
        return isset($this->styles[strtolower($name)]);
    }
    public function get_style(string $name): Output_Formatter_Style_Interface
    {
        if (!$this->has_style($name)) {
            throw new InvalidArgumentException(\sprintf('Undefined style: "%s".', $name));
        }
        return $this->styles[strtolower($name)];
    }
    public function format(?string $message): ?string
    {
        return $this->format_and_wrap($message, 0);
    }
    public function format_and_wrap(?string $message, int $width): string
    {
        if (null === $message) {
            return '';
        }
        $offset = 0;
        $output = '';
        $open_tag_regex = '[a-z](?:[^\\\\<>]*+ | \\\\.)*';
        $close_tag_regex = '[a-z][^<>]*+';
        $current_line_length = 0;
        preg_match_all("#<(({$open_tag_regex}) | /({$close_tag_regex})?)>#ix", $message, $matches, \PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $i => $match) {
            $pos = $match[1];
            $text = $match[0];
            if (0 != $pos && '\\' == $message[$pos - 1]) {
                continue;
            }
            // convert byte position to character position.
            $pos = Helper::length(substr($message, 0, $pos));
            // add the text up to the next tag
            $output .= $this->apply_current_style(Helper::substr($message, $offset, $pos - $offset), $output, $width, $current_line_length);
            $offset = $pos + Helper::length($text);
            // opening tag?
            if ($open = '/' !== $text[1]) {
                $tag = $matches[1][$i][0];
            } else {
                $tag = $matches[3][$i][0] ?? '';
            }
            if (!$open && !$tag) {
                // </>
                $this->style_stack->pop();
            } elseif (null === $style = $this->create_style_from_string($tag)) {
                $output .= $this->apply_current_style($text, $output, $width, $current_line_length);
            } elseif ($open) {
                $this->style_stack->push($style);
            } else {
                $this->style_stack->pop($style);
            }
        }
        $output .= $this->apply_current_style(Helper::substr($message, $offset), $output, $width, $current_line_length);
        return strtr($output, ["\x00" => '\\', '\<' => '<', '\>' => '>']);
    }
    public function get_style_stack(): Output_Formatter_Style_Stack
    {
        return $this->style_stack;
    }
    /**
     * Tries to create new style instance from string.
     */
    private function create_style_from_string(string $string): ?Output_Formatter_Style_Interface
    {
        if (isset($this->styles[$string])) {
            return $this->styles[$string];
        }
        if (!preg_match_all('/([^=]+)=([^;]+)(;|$)/', $string, $matches, \PREG_SET_ORDER)) {
            return null;
        }
        $style = new Output_Formatter_Style();
        foreach ($matches as $match) {
            array_shift($match);
            $match[0] = strtolower($match[0]);
            if ('fg' == $match[0]) {
                $style->set_foreground(strtolower($match[1]));
            } elseif ('bg' == $match[0]) {
                $style->set_background(strtolower($match[1]));
            } elseif ('href' === $match[0]) {
                $url = preg_replace('{\\\\([<>])}', '$1', $match[1]);
                $style->set_href($url);
            } elseif ('options' === $match[0]) {
                preg_match_all('([^,;]+)', strtolower($match[1]), $options);
                $options = array_shift($options);
                foreach ($options as $option) {
                    $style->set_option($option);
                }
            } else {
                return null;
            }
        }
        return $style;
    }
    /**
     * Applies current style from stack to text, if must be applied.
     */
    private function apply_current_style(string $text, string $current, int $width, int &$current_line_length): string
    {
        if ('' === $text) {
            return '';
        }
        if (!$width) {
            return $this->is_decorated() ? $this->style_stack->get_current()->apply($text) : $text;
        }
        if (!$current_line_length && '' !== $current) {
            $text = ltrim($text);
        }
        if ($current_line_length) {
            $lines = explode("\n", $text, 2);
            $prefix = Helper::substr($lines[0], 0, $i = $width - $current_line_length) . "\n";
            $text = Helper::substr($lines[0], $i);
            if (isset($lines[1])) {
                // $prefix may contain the full first line in which the \n is already a part of $prefix.
                if ('' !== $text) {
                    $text .= "\n";
                }
                $text .= $lines[1];
            }
        } else {
            $prefix = '';
        }
        preg_match('~(\n)$~', $text, $matches);
        $text = $prefix . $this->add_line_breaks($text, $width);
        $text = rtrim($text, "\n") . ($matches[1] ?? '');
        if (!$current_line_length && '' !== $current && !str_ends_with($current, "\n")) {
            $text = "\n" . $text;
        }
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            $current_line_length = 0 === $i ? $current_line_length + Helper::length($line) : Helper::length($line);
            if ($width <= $current_line_length) {
                $current_line_length = 0;
            }
        }
        if ($this->is_decorated()) {
            foreach ($lines as $i => $line) {
                $lines[$i] = $this->style_stack->get_current()->apply($line);
            }
        }
        return implode("\n", $lines);
    }
    private function add_line_breaks(string $text, int $width): string
    {
        $encoding = mb_detect_encoding($text, null, true) ?: 'UTF-8';
        return b($text)->to_unicode_string($encoding)->wordwrap($width, "\n", true)->to_byte_string($encoding);
    }
}