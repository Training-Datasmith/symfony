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
namespace Symfony\Bundle\Web_Profiler_Bundle\Profiler;

use Symfony\Component\Error_Handler\Error_Renderer\File_Link_Formatter;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Filter;
/**
 * Twig extension related to PHP code and used by the profiler and the default exception templates.
 *
 * This extension should only be used for debugging tools code
 * that is never executed in a production environment.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Code_Extension extends Abstract_Extension
{
    private readonly string|File_Link_Formatter|array|false $file_link_format;
    public function __construct(string|File_Link_Formatter $file_link_format, private string $project_dir, private readonly string $charset)
    {
        $this->file_link_format = ($file_link_format ?: \ini_get('xdebug.file_link_format')) ?: get_cfg_var('xdebug.file_link_format');
        $this->project_dir = str_replace('\\', '/', $project_dir) . '/';
    }
    public function get_filters(): array
    {
        return [new Twig_Filter('abbr_class', $this->abbr_class(...), ['is_safe' => ['html'], 'pre_escape' => 'html']), new Twig_Filter('abbr_method', $this->abbr_method(...), ['is_safe' => ['html'], 'pre_escape' => 'html']), new Twig_Filter('format_args', $this->format_args(...), ['is_safe' => ['html']]), new Twig_Filter('format_args_as_text', $this->format_args_as_text(...)), new Twig_Filter('file_excerpt', $this->file_excerpt(...), ['is_safe' => ['html']]), new Twig_Filter('format_file', $this->format_file(...), ['is_safe' => ['html']]), new Twig_Filter('format_file_from_text', $this->format_file_from_text(...), ['is_safe' => ['html']]), new Twig_Filter('format_log_message', $this->format_log_message(...), ['is_safe' => ['html']]), new Twig_Filter('file_link', $this->get_file_link(...)), new Twig_Filter('file_relative', $this->get_file_relative(...))];
    }
    public function abbr_class(string $class): string
    {
        $parts = explode('\\', $class);
        $short = array_pop($parts);
        return \sprintf('<abbr title="%s">%s</abbr>', $class, $short);
    }
    public function abbr_method(string $method): string
    {
        if (str_contains($method, '::')) {
            [$class, $method] = explode('::', $method, 2);
            $result = \sprintf('%s::%s()', $this->abbr_class($class), $method);
        } elseif ('Closure' === $method) {
            $result = \sprintf('<abbr title="%s">%1$s</abbr>', $method);
        } else {
            $result = \sprintf('<abbr title="%s">%1$s</abbr>()', $method);
        }
        return $result;
    }
    /**
     * Formats an array as a string.
     */
    public function format_args(array $args): string
    {
        $result = [];
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $item[1] = htmlspecialchars((string) $item[1], \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset);
                $parts = explode('\\', $item[1]);
                $short = array_pop($parts);
                $formatted_value = \sprintf('<em>object</em>(<abbr title="%s">%s</abbr>)', $item[1], $short);
            } elseif ('array' === $item[0]) {
                $formatted_value = \sprintf('<em>array</em>(%s)', \is_array($item[1]) ? $this->format_args($item[1]) : htmlspecialchars(var_export($item[1], true), \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset));
            } elseif ('null' === $item[0]) {
                $formatted_value = '<em>null</em>';
            } elseif ('boolean' === $item[0]) {
                $formatted_value = '<em>' . strtolower(htmlspecialchars(var_export($item[1], true), \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset)) . '</em>';
            } elseif ('resource' === $item[0]) {
                $formatted_value = '<em>resource</em>';
            } elseif (\is_string($item[1]) && preg_match('/[^\x07-\x0D\x1B\x20-\xFF]/', $item[1])) {
                $formatted_value = '<em>binary string</em>';
            } else {
                $formatted_value = str_replace("\n", '', htmlspecialchars(var_export($item[1], true), \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset));
            }
            $result[] = \is_int($key) ? $formatted_value : \sprintf("'%s' => %s", htmlspecialchars($key, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset), $formatted_value);
        }
        return implode(', ', $result);
    }
    /**
     * Formats an array as a string.
     */
    public function format_args_as_text(array $args): string
    {
        return strip_tags($this->format_args($args));
    }
    /**
     * Returns an excerpt of a code file around the given line number.
     */
    public function file_excerpt(string $file, int $line, int $src_context = 3): ?string
    {
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }
        $contents = file_get_contents($file);
        if (!str_contains($contents, '<?php') && !str_contains($contents, '<?=')) {
            $lines = explode("\n", $contents);
            if (0 > $src_context) {
                $src_context = \count($lines);
            }
            return $this->format_file_excerpt($this->extract_excerpt_lines($lines, $line, $src_context), $line, $src_context);
        }
        // highlight_string could throw warnings
        // see https://bugs.php.net/25725
        $code = @highlight_string($contents, true);
        // remove main pre/code tags
        $code = preg_replace('#^<pre.*?>\s*<code.*?>(.*)</code>\s*</pre>#s', '\1', $code);
        // split multiline span tags
        $code = preg_replace_callback('#<span ([^>]++)>((?:[^<\n]*+\n)++[^<]*+)</span>#', static fn(array $m): string => "<span {$m[1]}>" . str_replace("\n", "</span>\n<span {$m[1]}>", $m[2]) . '</span>', (string) $code);
        $lines = explode("\n", (string) $code);
        if (0 > $src_context) {
            $src_context = \count($lines);
        }
        return $this->format_file_excerpt(array_map(self::fix_code_markup(...), $this->extract_excerpt_lines($lines, $line, $src_context)), $line, $src_context);
    }
    private function extract_excerpt_lines(array $lines, int $selected_line, int $src_context): array
    {
        return \array_slice($lines, max($selected_line - $src_context, 0), min($src_context * 2 + 1, \count($lines) - $selected_line + $src_context), true);
    }
    private function format_file_excerpt(array $lines, int $selected_line, int $src_context): string
    {
        $start = max($selected_line - $src_context, 1);
        return "<ol start=\"{$start}\">" . implode("\n", array_map(static fn(string $line, int $num): string => '<li' . (++$num === $selected_line ? ' class="selected"' : '') . "><a class=\"anchor\" id=\"line{$num}\"></a><code>{$line}</code></li>", $lines, array_keys($lines))) . '</ol>';
    }
    /**
     * Formats a file path.
     */
    public function format_file(string $file, int $line, ?string $text = null): string
    {
        $file = trim($file);
        if (null === $text) {
            if (null !== $rel = $this->get_file_relative($file)) {
                $rel = explode('/', htmlspecialchars($rel, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset), 2);
                $text = \sprintf('<abbr title="%s%2$s">%s</abbr>%s', htmlspecialchars($this->project_dir, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset), $rel[0], '/' . ($rel[1] ?? ''));
            } else {
                $text = htmlspecialchars($file, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset);
            }
        } else {
            $text = htmlspecialchars($text, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset);
        }
        if (0 < $line) {
            $text .= ' at line ' . $line;
        }
        if (false !== $link = $this->get_file_link($file, $line)) {
            return \sprintf('<a href="%s" title="Click to open this file" class="file_link">%s</a>', htmlspecialchars($link, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset), $text);
        }
        return $text;
    }
    public function get_file_link(string $file, int $line): string|false
    {
        if ($fmt = $this->file_link_format) {
            return \is_string($fmt) ? strtr($fmt, ['%f' => $file, '%l' => $line]) : $fmt->format($file, $line);
        }
        return false;
    }
    public function get_file_relative(string $file): ?string
    {
        $file = str_replace('\\', '/', $file);
        if (null !== $this->project_dir && str_starts_with($file, $this->project_dir)) {
            return ltrim(substr($file, \strlen($this->project_dir)), '/');
        }
        return null;
    }
    public function format_file_from_text(string $text): string
    {
        return preg_replace_callback('/in ("|&quot;)?(.+?)\1(?: +(?:on|at))? +line (\d+)/s', fn($match): string => 'in ' . $this->format_file($match[2], $match[3]), $text);
    }
    /**
     * @internal
     */
    public function format_log_message(string $message, array $context): string
    {
        if ($context && str_contains($message, '{')) {
            $replacements = [];
            foreach ($context as $key => $val) {
                if (\is_scalar($val)) {
                    $replacements['{' . $key . '}'] = $val;
                }
            }
            if ($replacements) {
                $message = strtr($message, $replacements);
            }
        }
        return htmlspecialchars($message, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset);
    }
    protected static function fix_code_markup(string $line): string
    {
        // </span> ending tag from previous line
        $opening = strpos($line, '<span');
        $closing = strpos($line, '</span>');
        if (false !== $closing && (false === $opening || $closing < $opening)) {
            $line = substr_replace($line, '', $closing, 7);
        }
        // missing </span> tag at the end of line
        $opening = strpos($line, '<span');
        $closing = strpos($line, '</span>');
        if (false !== $opening && (false === $closing || $closing < $opening)) {
            $line .= '</span>';
        }
        return trim($line);
    }
}