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
namespace Symfony\Component\Console\Output;

use Symfony\Component\Console\Formatter\Output_Formatter_Interface;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Terminal;
/**
 * @author Pierre du Plessis <pdples@gmail.com>
 * @author Gabriel Ostrolucký <gabriel.ostrolucky@gmail.com>
 */
class Console_Section_Output extends Stream_Output
{
    private array $content = [];
    private int $lines = 0;
    private readonly array $sections;
    private readonly Terminal $terminal;
    private int $max_height = 0;
    /**
     * @param resource               $stream
     * @param ConsoleSectionOutput[] $sections
     */
    public function __construct($stream, array &$sections, int $verbosity, bool $decorated, Output_Formatter_Interface $formatter)
    {
        parent::__construct($stream, $verbosity, $decorated, $formatter);
        array_unshift($sections, $this);
        $this->sections =& $sections;
        $this->terminal = new Terminal();
    }
    /**
     * Defines a maximum number of lines for this section.
     *
     * When more lines are added, the section will automatically scroll to the
     * end (i.e. remove the first lines to comply with the max height).
     */
    public function set_max_height(int $max_height): void
    {
        // when changing max height, clear output of current section and redraw again with the new height
        $previous_max_height = $this->max_height;
        $this->max_height = $max_height;
        $existing_content = $this->pop_stream_content_until_current_section($previous_max_height ? min($previous_max_height, $this->lines) : $this->lines);
        parent::do_write($this->get_visible_content(), false);
        parent::do_write($existing_content, false);
    }
    /**
     * Clears previous output for this section.
     *
     * @param int $lines Number of lines to clear. If null, then the entire output of this section is cleared
     */
    public function clear(?int $lines = null): void
    {
        if (!$this->content || !$this->is_decorated()) {
            return;
        }
        if ($lines) {
            array_splice($this->content, -$lines);
        } else {
            $lines = $this->lines;
            $this->content = [];
        }
        $this->lines -= $lines;
        parent::do_write($this->pop_stream_content_until_current_section($this->max_height ? min($this->max_height, $lines) : $lines), false);
    }
    /**
     * Overwrites the previous output with a new message.
     */
    public function overwrite(string|iterable $message): void
    {
        $this->clear();
        $this->writeln($message);
    }
    public function get_content(): string
    {
        return implode('', $this->content);
    }
    public function get_visible_content(): string
    {
        if (0 === $this->max_height) {
            return $this->get_content();
        }
        return implode('', \array_slice($this->content, -$this->max_height));
    }
    /**
     * @internal
     */
    public function add_content(string $input, bool $newline = true): int
    {
        $width = $this->terminal->get_width();
        $lines = explode(\PHP_EOL, $input);
        $lines_added = 0;
        $count = \count($lines) - 1;
        foreach ($lines as $i => $line_content) {
            // re-add the line break (that has been removed in the above `explode()` for
            // - every line that is not the last line
            // - if $newline is required, also add it to the last line
            if ($i < $count || $newline) {
                $line_content .= \PHP_EOL;
            }
            // skip line if there is no text (or newline for that matter)
            if ('' === $line_content) {
                continue;
            }
            // For the first line, check if the previous line (last entry of `$this->content`)
            // needs to be continued (i.e. does not end with a line break).
            if (0 === $i && false !== ($last_line = end($this->content)) && !str_ends_with((string) $last_line, \PHP_EOL)) {
                // deduct the line count of the previous line
                $this->lines -= (int) ceil($this->get_display_length($last_line) / $width) ?: 1;
                // concatenate previous and new line
                $line_content = $last_line . $line_content;
                // replace last entry of `$this->content` with the new expanded line
                array_splice($this->content, -1, 1, $line_content);
            } else {
                // otherwise just add the new content
                $this->content[] = $line_content;
            }
            $lines_added += (int) ceil($this->get_display_length($line_content) / $width) ?: 1;
        }
        $this->lines += $lines_added;
        return $lines_added;
    }
    /**
     * @internal
     */
    public function add_new_line_of_input_submit(): void
    {
        $this->content[] = \PHP_EOL;
        ++$this->lines;
    }
    protected function do_write(string $message, bool $newline): void
    {
        // Simulate newline behavior for consistent output formatting, avoiding extra logic
        if (!$newline && str_ends_with($message, \PHP_EOL)) {
            $message = substr($message, 0, -\strlen(\PHP_EOL));
            $newline = true;
        }
        if (!$this->is_decorated()) {
            parent::do_write($message, $newline);
            return;
        }
        // Check if the previous line (last entry of `$this->content`) needs to be continued
        // (i.e. does not end with a line break). In which case, it needs to be erased first.
        $lines_to_clear = $delete_last_line = ($last_line = end($this->content) ?: '') && !str_ends_with((string) $last_line, \PHP_EOL) ? 1 : 0;
        $lines_added = $this->add_content($message, $newline);
        if ($line_overflow = $this->max_height > 0 && $this->lines > $this->max_height) {
            // on overflow, clear the whole section and redraw again (to remove the first lines)
            $lines_to_clear = $this->max_height;
        }
        $erased_content = $this->pop_stream_content_until_current_section($lines_to_clear);
        if ($line_overflow) {
            // redraw existing lines of the section
            $previous_lines_of_section = \array_slice($this->content, $this->lines - $this->max_height, $this->max_height - $lines_added);
            parent::do_write(implode('', $previous_lines_of_section), false);
        }
        // if the last line was removed, re-print its content together with the new content.
        // otherwise, just print the new content.
        parent::do_write($delete_last_line ? $last_line . $message : $message, true);
        parent::do_write($erased_content, false);
    }
    /**
     * At initial stage, cursor is at the end of stream output. This method makes cursor crawl upwards until it hits
     * current section. Then it erases content it crawled through. Optionally, it erases part of current section too.
     */
    private function pop_stream_content_until_current_section(int $number_of_lines_to_clear_from_current_section = 0): string
    {
        $number_of_lines_to_clear = $number_of_lines_to_clear_from_current_section;
        $erased_content = [];
        foreach ($this->sections as $section) {
            if ($section === $this) {
                break;
            }
            $number_of_lines_to_clear += $section->max_height ? min($section->lines, $section->max_height) : $section->lines;
            if ('' !== $section_content = $section->get_visible_content()) {
                if (!str_ends_with((string) $section_content, \PHP_EOL)) {
                    $section_content .= \PHP_EOL;
                }
                $erased_content[] = $section_content;
            }
        }
        if ($number_of_lines_to_clear > 0) {
            // move cursor up n lines
            parent::do_write(\sprintf("\x1b[%dA", $number_of_lines_to_clear), false);
            // erase to end of screen
            parent::do_write("\x1b[0J", false);
        }
        return implode('', array_reverse($erased_content));
    }
    private function get_display_length(string $text): int
    {
        return Helper::width(Helper::remove_decoration($this->get_formatter(), str_replace("\t", '        ', $text)));
    }
}