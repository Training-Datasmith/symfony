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
namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Formatter\Wrappable_Output_Formatter_Interface;
use Symfony\Component\Console\Output\Console_Section_Output;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Provides helpers to display a table.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Саша Стаменковић <umpirsky@gmail.com>
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 * @author Max Grigorian <maxakawizard@gmail.com>
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class Table
{
    private const SEPARATOR_TOP = 0;
    private const SEPARATOR_TOP_BOTTOM = 1;
    private const SEPARATOR_MID = 2;
    private const SEPARATOR_BOTTOM = 3;
    private const BORDER_OUTSIDE = 0;
    private const BORDER_INSIDE = 1;
    private const DISPLAY_ORIENTATION_DEFAULT = 'default';
    private const DISPLAY_ORIENTATION_HORIZONTAL = 'horizontal';
    private const DISPLAY_ORIENTATION_VERTICAL = 'vertical';
    private ?string $header_title = null;
    private ?string $footer_title = null;
    private array $headers = [];
    private array $rows = [];
    private array $effective_column_widths = [];
    private int $number_of_columns;
    private Table_Style $style;
    private array $column_styles = [];
    private array $column_widths = [];
    private array $column_max_widths = [];
    private bool $rendered = false;
    private string $display_orientation = self::DISPLAY_ORIENTATION_DEFAULT;
    private static array $styles;
    public function __construct(private readonly Output_Interface $output)
    {
        self::$styles ??= self::init_styles();
        $this->set_style('default');
    }
    /**
     * Sets a style definition.
     */
    public static function set_style_definition(string $name, Table_Style $style): void
    {
        self::$styles ??= self::init_styles();
        self::$styles[$name] = $style;
    }
    /**
     * Gets a style definition by name.
     */
    public static function get_style_definition(string $name): Table_Style
    {
        self::$styles ??= self::init_styles();
        return self::$styles[$name] ?? throw new InvalidArgumentException(\sprintf('Style "%s" is not defined.', $name));
    }
    /**
     * Sets table style.
     *
     * @return $this
     */
    public function set_style(Table_Style|string $name): static
    {
        $this->style = $this->resolve_style($name);
        return $this;
    }
    /**
     * Gets the current table style.
     */
    public function get_style(): Table_Style
    {
        return $this->style;
    }
    /**
     * Sets table column style.
     *
     * @param TableStyle|string $name The style name or a TableStyle instance
     *
     * @return $this
     */
    public function set_column_style(int $column_index, Table_Style|string $name): static
    {
        $this->column_styles[$column_index] = $this->resolve_style($name);
        return $this;
    }
    /**
     * Gets the current style for a column.
     *
     * If style was not set, it returns the global table style.
     */
    public function get_column_style(int $column_index): Table_Style
    {
        return $this->column_styles[$column_index] ?? $this->get_style();
    }
    /**
     * Sets the minimum width of a column.
     *
     * @return $this
     */
    public function set_column_width(int $column_index, int $width): static
    {
        $this->column_widths[$column_index] = $width;
        return $this;
    }
    /**
     * Sets the minimum width of all columns.
     *
     * @return $this
     */
    public function set_column_widths(array $widths): static
    {
        $this->column_widths = [];
        foreach ($widths as $index => $width) {
            $this->set_column_width($index, $width);
        }
        return $this;
    }
    /**
     * Sets the maximum width of a column.
     *
     * Any cell within this column which contents exceeds the specified width will be wrapped into multiple lines, while
     * formatted strings are preserved.
     *
     * @return $this
     */
    public function set_column_max_width(int $column_index, int $width): static
    {
        if (!$this->output->get_formatter() instanceof Wrappable_Output_Formatter_Interface) {
            throw new \LogicException(\sprintf('Setting a maximum column width is only supported when using a "%s" formatter, got "%s".', Wrappable_Output_Formatter_Interface::class, get_debug_type($this->output->get_formatter())));
        }
        $this->column_max_widths[$column_index] = $width;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_headers(array $headers): static
    {
        $headers = array_values($headers);
        if ($headers && !\is_array($headers[0])) {
            $headers = [$headers];
        }
        $this->headers = $headers;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_rows(array $rows): static
    {
        $this->rows = [];
        return $this->add_rows($rows);
    }
    /**
     * @return $this
     */
    public function add_rows(array $rows): static
    {
        foreach ($rows as $row) {
            $this->add_row($row);
        }
        return $this;
    }
    /**
     * @return $this
     */
    public function add_row(Table_Separator|array $row): static
    {
        if ($row instanceof Table_Separator) {
            $this->rows[] = $row;
            return $this;
        }
        $this->rows[] = array_values($row);
        return $this;
    }
    /**
     * Adds a row to the table, and re-renders the table.
     *
     * @return $this
     */
    public function append_row(Table_Separator|array $row): static
    {
        if (!$this->output instanceof Console_Section_Output) {
            throw new RuntimeException(\sprintf('Output should be an instance of "%s" when calling "%s".', Console_Section_Output::class, __METHOD__));
        }
        if ($this->rendered) {
            $this->output->clear($this->calculate_row_count());
        }
        $this->add_row($row);
        $this->render();
        return $this;
    }
    /**
     * @return $this
     */
    public function set_row(int|string $column, array $row): static
    {
        $this->rows[$column] = $row;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_header_title(?string $title): static
    {
        $this->header_title = $title;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_footer_title(?string $title): static
    {
        $this->footer_title = $title;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_horizontal(bool $horizontal = true): static
    {
        $this->display_orientation = $horizontal ? self::DISPLAY_ORIENTATION_HORIZONTAL : self::DISPLAY_ORIENTATION_DEFAULT;
        return $this;
    }
    /**
     * @return $this
     */
    public function set_vertical(bool $vertical = true): static
    {
        $this->display_orientation = $vertical ? self::DISPLAY_ORIENTATION_VERTICAL : self::DISPLAY_ORIENTATION_DEFAULT;
        return $this;
    }
    /**
     * Renders table to output.
     *
     * Example:
     *
     *     +---------------+-----------------------+------------------+
     *     | ISBN          | Title                 | Author           |
     *     +---------------+-----------------------+------------------+
     *     | 99921-58-10-7 | Divine Comedy         | Dante Alighieri  |
     *     | 9971-5-0210-0 | A Tale of Two Cities  | Charles Dickens  |
     *     | 960-425-059-0 | The Lord of the Rings | J. R. R. Tolkien |
     *     +---------------+-----------------------+------------------+
     */
    public function render(): void
    {
        $divider = new Table_Separator();
        $is_cell_with_colspan = static fn($cell): bool => $cell instanceof Table_Cell && $cell->get_colspan() >= 2;
        $horizontal = self::DISPLAY_ORIENTATION_HORIZONTAL === $this->display_orientation;
        $vertical = self::DISPLAY_ORIENTATION_VERTICAL === $this->display_orientation;
        $rows = [];
        if ($horizontal) {
            foreach ($this->headers[0] ?? [] as $i => $header) {
                $rows[$i] = [$header];
                foreach ($this->rows as $row) {
                    if ($row instanceof Table_Separator) {
                        continue;
                    }
                    if (isset($row[$i])) {
                        $rows[$i][] = $row[$i];
                    } elseif ($is_cell_with_colspan($rows[$i][0])) {
                        // Noop, there is a "title"
                    } else {
                        $rows[$i][] = null;
                    }
                }
            }
        } elseif ($vertical) {
            $formatter = $this->output->get_formatter();
            $max_header_length = array_reduce($this->headers[0] ?? [], static fn($max, ?string $header) => max($max, Helper::width(Helper::remove_decoration($formatter, $header))), 0);
            foreach ($this->rows as $row) {
                if ($row instanceof Table_Separator) {
                    continue;
                }
                if ($rows) {
                    $rows[] = [$divider];
                }
                $contains_colspan = false;
                foreach ($row as $cell) {
                    if ($contains_colspan = $is_cell_with_colspan($cell)) {
                        break;
                    }
                }
                $headers = $this->headers[0] ?? [];
                $max_rows = max(\count($headers), \count($row));
                for ($i = 0; $i < $max_rows; ++$i) {
                    $cell = (string) ($row[$i] ?? '');
                    $eol = str_contains($cell, "\r\n") ? "\r\n" : "\n";
                    $parts = explode($eol, $cell);
                    foreach ($parts as $idx => $part) {
                        if ($headers && !$contains_colspan) {
                            if (0 === $idx) {
                                $rows[] = [\sprintf('<comment>%s%s</>: %s', str_repeat(' ', $max_header_length - Helper::width(Helper::remove_decoration($formatter, $headers[$i] ?? ''))), $headers[$i] ?? '', $part)];
                            } else {
                                $rows[] = [\sprintf('%s  %s', str_pad('', $max_header_length, ' ', \STR_PAD_LEFT), $part)];
                            }
                        } elseif ('' !== $cell) {
                            $rows[] = [$part];
                        }
                    }
                }
            }
        } else {
            $rows = array_merge($this->headers, [$divider], $this->rows);
        }
        $this->calculate_number_of_columns($rows);
        $row_groups = $this->build_table_rows($rows);
        $this->calculate_columns_width($row_groups);
        $is_header = !$horizontal;
        $is_first_row = $horizontal;
        $has_title = (bool) $this->header_title;
        foreach ($row_groups as $row_group) {
            $is_header_separator_rendered = false;
            foreach ($row_group as $row) {
                if ($divider === $row) {
                    $is_header = false;
                    $is_first_row = true;
                    continue;
                }
                if ($row instanceof Table_Separator) {
                    $this->render_row_separator();
                    continue;
                }
                if (!$row) {
                    continue;
                }
                if ($is_header && !$is_header_separator_rendered && $this->style->display_outside_border()) {
                    $this->render_row_separator(self::SEPARATOR_TOP, $has_title ? $this->header_title : null, $has_title ? $this->style->get_header_title_format() : null);
                    $has_title = false;
                    $is_header_separator_rendered = true;
                }
                if ($is_first_row) {
                    $this->render_row_separator($horizontal ? self::SEPARATOR_TOP : self::SEPARATOR_TOP_BOTTOM, $has_title ? $this->header_title : null, $has_title ? $this->style->get_header_title_format() : null);
                    $is_first_row = false;
                    $has_title = false;
                }
                if ($vertical) {
                    $is_header = false;
                    $is_first_row = false;
                }
                if ($horizontal) {
                    $this->render_row($row, $this->style->get_cell_row_format(), $this->style->get_cell_header_format());
                } else {
                    $this->render_row($row, $is_header ? $this->style->get_cell_header_format() : $this->style->get_cell_row_format());
                }
            }
        }
        if ($this->get_style()->display_outside_border()) {
            $this->render_row_separator(self::SEPARATOR_BOTTOM, $this->footer_title, $this->style->get_footer_title_format());
        }
        $this->cleanup();
        $this->rendered = true;
    }
    /**
     * Renders horizontal header separator.
     *
     * Example:
     *
     *     +-----+-----------+-------+
     */
    private function render_row_separator(int $type = self::SEPARATOR_MID, ?string $title = null, ?string $title_format = null): void
    {
        if (!$count = $this->number_of_columns) {
            return;
        }
        $borders = $this->style->get_border_chars();
        if (!$borders[0] && !$borders[2] && !$this->style->get_crossing_char()) {
            return;
        }
        $crossings = $this->style->get_crossing_chars();
        if (self::SEPARATOR_MID === $type) {
            [$horizontal, $left_char, $mid_char, $right_char] = [$borders[2], $crossings[8], $crossings[0], $crossings[4]];
        } elseif (self::SEPARATOR_TOP === $type) {
            [$horizontal, $left_char, $mid_char, $right_char] = [$borders[0], $crossings[1], $crossings[2], $crossings[3]];
        } elseif (self::SEPARATOR_TOP_BOTTOM === $type) {
            [$horizontal, $left_char, $mid_char, $right_char] = [$borders[0], $crossings[9], $crossings[10], $crossings[11]];
        } else {
            [$horizontal, $left_char, $mid_char, $right_char] = [$borders[0], $crossings[7], $crossings[6], $crossings[5]];
        }
        $markup = $left_char;
        for ($column = 0; $column < $count; ++$column) {
            $markup .= str_repeat((string) $horizontal, $this->effective_column_widths[$column]);
            $markup .= $column === $count - 1 ? $right_char : $mid_char;
        }
        if (null !== $title) {
            $title_length = Helper::width(Helper::remove_decoration($formatter = $this->output->get_formatter(), $formatted_title = \sprintf($title_format, $title)));
            $markup_length = Helper::width($markup);
            if ($title_length > $limit = $markup_length - 4) {
                $title_length = $limit;
                $format_length = Helper::width(Helper::remove_decoration($formatter, \sprintf($title_format, '')));
                $formatted_title = \sprintf($title_format, Helper::substr($title, 0, $limit - $format_length - 3) . '...');
            }
            $title_start = intdiv($markup_length - $title_length, 2);
            if (false === mb_detect_encoding((string) $markup, null, true)) {
                $markup = substr_replace($markup, $formatted_title, $title_start, $title_length);
            } else {
                $markup = mb_substr((string) $markup, 0, $title_start) . $formatted_title . mb_substr((string) $markup, $title_start + $title_length);
            }
        }
        $this->output->writeln(\sprintf($this->style->get_border_format(), $markup));
    }
    /**
     * Renders vertical column separator.
     */
    private function render_column_separator(int $type = self::BORDER_OUTSIDE): string
    {
        $borders = $this->style->get_border_chars();
        return \sprintf($this->style->get_border_format(), self::BORDER_OUTSIDE === $type ? $borders[1] : $borders[3]);
    }
    /**
     * Renders table row.
     *
     * Example:
     *
     *     | 9971-5-0210-0 | A Tale of Two Cities  | Charles Dickens  |
     */
    private function render_row(array $row, string $cell_format, ?string $first_cell_format = null): void
    {
        $row_content = $this->render_column_separator(self::BORDER_OUTSIDE);
        $columns = $this->get_row_columns($row);
        $last = \count($columns) - 1;
        foreach ($columns as $i => $column) {
            if ($first_cell_format && 0 === $i) {
                $row_content .= $this->render_cell($row, $column, $first_cell_format);
            } else {
                $row_content .= $this->render_cell($row, $column, $cell_format);
            }
            $row_content .= $this->render_column_separator($last === $i ? self::BORDER_OUTSIDE : self::BORDER_INSIDE);
        }
        $this->output->writeln($row_content);
    }
    /**
     * Renders table cell with padding.
     */
    private function render_cell(array $row, int $column, string $cell_format): string
    {
        $cell = $row[$column] ?? '';
        $width = $this->effective_column_widths[$column];
        if ($cell instanceof Table_Cell && $cell->get_colspan() > 1) {
            // add the width of the following columns(numbers of colspan).
            foreach (range($column + 1, $column + $cell->get_colspan() - 1) as $next_column) {
                $width += $this->get_column_separator_width() + $this->effective_column_widths[$next_column];
            }
        }
        // str_pad won't work properly with multi-byte strings, we need to fix the padding
        $width += \strlen($cell) - Helper::width($cell) - substr_count($cell, "\x00");
        $style = $this->get_column_style($column);
        if ($cell instanceof Table_Separator) {
            return \sprintf($style->get_border_format(), str_repeat((string) $style->get_border_chars()[2], $width));
        }
        $width += Helper::length($cell) - Helper::length(Helper::remove_decoration($this->output->get_formatter(), $cell));
        $content = \sprintf($style->get_cell_row_content_format(), $cell);
        $pad_type = $style->get_pad_type();
        if ($cell instanceof Table_Cell && $cell->get_style() instanceof Table_Cell_Style) {
            $is_not_styled_by_tag = !preg_match('/^<(\w+|(\w+=[\w,]+;?)*)>.+<\/(\w+|(\w+=\w+;?)*)?>$/', $cell);
            if ($is_not_styled_by_tag) {
                $cell_format = $cell->get_style()->get_cell_format();
                if (!\is_string($cell_format)) {
                    $tag = http_build_query($cell->get_style()->get_tag_options(), '', ';');
                    $cell_format = '<' . $tag . '>%s</>';
                }
                if (str_contains($content, '</>')) {
                    $content = str_replace('</>', '', $content);
                    $width -= 3;
                }
                if (str_contains($content, '<fg=default;bg=default>')) {
                    $content = str_replace('<fg=default;bg=default>', '', $content);
                    $width -= \strlen('<fg=default;bg=default>');
                }
            }
            $pad_type = $cell->get_style()->get_pad_by_align();
        }
        return \sprintf($cell_format, str_pad($content, $width, $style->get_padding_char(), $pad_type));
    }
    /**
     * Calculate number of columns for this table.
     */
    private function calculate_number_of_columns(array $rows): void
    {
        $columns = [0];
        foreach ($rows as $row) {
            if ($row instanceof Table_Separator) {
                continue;
            }
            $columns[] = $this->get_number_of_columns($row);
        }
        $this->number_of_columns = max($columns);
    }
    private function build_table_rows(array $rows): Table_Rows
    {
        /** @var WrappableOutputFormatterInterface $formatter */
        $formatter = $this->output->get_formatter();
        $unmerged_rows = [];
        for ($row_key = 0; $row_key < \count($rows); ++$row_key) {
            $rows = $this->fill_next_rows($rows, $row_key);
            // Remove any new line breaks and replace it with a new line
            foreach ($rows[$row_key] as $column => $cell) {
                $colspan = $cell instanceof Table_Cell ? $cell->get_colspan() : 1;
                $min_wrapped_width = 0;
                $width_applied = [];
                $length_column_border = $this->get_column_separator_width() + Helper::width($this->style->get_cell_row_content_format()) - 2;
                for ($i = $column; $i < $column + $colspan; ++$i) {
                    if (isset($this->column_max_widths[$i])) {
                        $min_wrapped_width += $this->column_max_widths[$i];
                        $width_applied[] = ['type' => 'max', 'column' => $i];
                    } elseif (($this->column_widths[$i] ?? 0) > 0 && $colspan > 1) {
                        $min_wrapped_width += $this->column_widths[$i];
                        $width_applied[] = ['type' => 'min', 'column' => $i];
                    }
                }
                if (1 === \count($width_applied)) {
                    if ($colspan > 1) {
                        $min_wrapped_width *= $colspan;
                        // previous logic
                    }
                } elseif (\count($width_applied) > 1) {
                    $min_wrapped_width += (\count($width_applied) - 1) * $length_column_border;
                }
                $cell_width = Helper::width(Helper::remove_decoration($formatter, $cell));
                if ($min_wrapped_width && $cell_width > $min_wrapped_width) {
                    $cell = $formatter->format_and_wrap($cell, $min_wrapped_width);
                }
                // update minimal columnWidths for spanned columns
                if ($colspan > 1 && $min_wrapped_width > 0) {
                    $columns_min_width_processed = [];
                    $cell_width = min($cell_width, $min_wrapped_width);
                    foreach ($width_applied as $item) {
                        if ('max' === $item['type'] && $cell_width >= $this->column_max_widths[$item['column']]) {
                            $min_width_column = $this->column_max_widths[$item['column']];
                            $this->column_widths[$item['column']] = $min_width_column;
                            $columns_min_width_processed[$item['column']] = true;
                            $cell_width -= $min_width_column + $length_column_border;
                        }
                    }
                    for ($i = $column; $i < $column + $colspan; ++$i) {
                        if (isset($columns_min_width_processed[$i])) {
                            continue;
                        }
                        $this->column_widths[$i] = $cell_width + $length_column_border;
                    }
                }
                if (!str_contains($cell ?? '', "\n")) {
                    continue;
                }
                $eol = str_contains($cell ?? '', "\r\n") ? "\r\n" : "\n";
                $escaped = implode($eol, array_map(Output_Formatter::escape_trailing_backslash(...), explode($eol, (string) $cell)));
                $cell = $cell instanceof Table_Cell ? new Table_Cell($escaped, ['colspan' => $cell->get_colspan()]) : $escaped;
                $lines = explode($eol, str_replace($eol, '<fg=default;bg=default></>' . $eol, $cell));
                foreach ($lines as $line_key => $line) {
                    if ($colspan > 1) {
                        $line = new Table_Cell($line, ['colspan' => $colspan]);
                    }
                    if (0 === $line_key) {
                        $rows[$row_key][$column] = $line;
                    } else {
                        if (!\array_key_exists($row_key, $unmerged_rows) || !\array_key_exists($line_key, $unmerged_rows[$row_key])) {
                            $unmerged_rows[$row_key][$line_key] = $this->copy_row($rows, $row_key);
                        }
                        $unmerged_rows[$row_key][$line_key][$column] = $line;
                    }
                }
            }
        }
        return new Table_Rows(function () use ($rows, $unmerged_rows): \Traversable {
            foreach ($rows as $row_key => $row) {
                $row_group = [$row instanceof Table_Separator ? $row : $this->fill_cells($row)];
                if (isset($unmerged_rows[$row_key])) {
                    foreach ($unmerged_rows[$row_key] as $row) {
                        $row_group[] = $row instanceof Table_Separator ? $row : $this->fill_cells($row);
                    }
                }
                yield $row_group;
            }
        });
    }
    private function calculate_row_count(): int
    {
        $number_of_rows = \count(iterator_to_array($this->build_table_rows(array_merge($this->headers, [new Table_Separator()], $this->rows))));
        if ($this->headers) {
            ++$number_of_rows;
            // Add row for header separator
        }
        if ($this->rows) {
            ++$number_of_rows;
            // Add row for footer separator
        }
        return $number_of_rows;
    }
    /**
     * fill rows that contains rowspan > 1.
     *
     * @throws InvalidArgumentException
     */
    private function fill_next_rows(array $rows, int $line): array
    {
        $unmerged_rows = [];
        foreach ($rows[$line] as $column => $cell) {
            if (null !== $cell && !$cell instanceof Table_Cell && !\is_scalar($cell) && !$cell instanceof \Stringable) {
                throw new InvalidArgumentException(\sprintf('A cell must be a TableCell, a scalar or an object implementing "__toString()", "%s" given.', get_debug_type($cell)));
            }
            if ($cell instanceof Table_Cell && $cell->get_rowspan() > 1) {
                $nb_lines = $cell->get_rowspan() - 1;
                $lines = [$cell];
                if (str_contains($cell, "\n")) {
                    $eol = str_contains($cell, "\r\n") ? "\r\n" : "\n";
                    $lines = explode($eol, str_replace($eol, '<fg=default;bg=default>' . $eol . '</>', $cell));
                    $nb_lines = \count($lines) > $nb_lines ? substr_count($cell, $eol) : $nb_lines;
                    $rows[$line][$column] = new Table_Cell($lines[0], ['colspan' => $cell->get_colspan(), 'style' => $cell->get_style()]);
                    unset($lines[0]);
                }
                // create a two dimensional array (rowspan x colspan)
                $unmerged_rows = array_replace_recursive(array_fill($line + 1, $nb_lines, []), $unmerged_rows);
                foreach ($unmerged_rows as $unmerged_row_key => $unmerged_row) {
                    $value = $lines[$unmerged_row_key - $line] ?? '';
                    $unmerged_rows[$unmerged_row_key][$column] = new Table_Cell($value, ['colspan' => $cell->get_colspan(), 'style' => $cell->get_style()]);
                    if ($nb_lines === $unmerged_row_key - $line) {
                        break;
                    }
                }
            }
        }
        foreach ($unmerged_rows as $unmerged_row_key => $unmerged_row) {
            // we need to know if $unmergedRow will be merged or inserted into $rows
            if (isset($rows[$unmerged_row_key]) && \is_array($rows[$unmerged_row_key]) && $this->get_number_of_columns($rows[$unmerged_row_key]) + $this->get_number_of_columns($unmerged_row) <= $this->number_of_columns) {
                foreach ($unmerged_row as $cell_key => $cell) {
                    // insert cell into row at cellKey position
                    array_splice($rows[$unmerged_row_key], $cell_key, 0, [$cell]);
                }
            } else {
                $row = $this->copy_row($rows, $unmerged_row_key - 1);
                foreach ($unmerged_row as $column => $cell) {
                    if ($cell) {
                        $row[$column] = $cell;
                    }
                }
                array_splice($rows, $unmerged_row_key, 0, [$row]);
            }
        }
        return $rows;
    }
    /**
     * fill cells for a row that contains colspan > 1.
     */
    private function fill_cells(iterable $row): iterable
    {
        $new_row = [];
        foreach ($row as $column => $cell) {
            $new_row[] = $cell;
            if ($cell instanceof Table_Cell && $cell->get_colspan() > 1) {
                foreach (range($column + 1, $column + $cell->get_colspan() - 1) as $position) {
                    // insert empty value at column position
                    $new_row[] = '';
                }
            }
        }
        return $new_row ?: $row;
    }
    private function copy_row(array $rows, int $line): array
    {
        $row = $rows[$line];
        foreach ($row as $cell_key => $cell_value) {
            $row[$cell_key] = '';
            if ($cell_value instanceof Table_Cell) {
                $row[$cell_key] = new Table_Cell('', ['colspan' => $cell_value->get_colspan()]);
            }
        }
        return $row;
    }
    /**
     * Gets number of columns by row.
     */
    private function get_number_of_columns(array $row): int
    {
        $columns = \count($row);
        foreach ($row as $column) {
            $columns += $column instanceof Table_Cell ? $column->get_colspan() - 1 : 0;
        }
        return $columns;
    }
    /**
     * Gets list of columns for the given row.
     */
    private function get_row_columns(array $row): array
    {
        $columns = range(0, $this->number_of_columns - 1);
        foreach ($row as $cell_key => $cell) {
            if ($cell instanceof Table_Cell && $cell->get_colspan() > 1) {
                // exclude grouped columns.
                $columns = array_diff($columns, range($cell_key + 1, $cell_key + $cell->get_colspan() - 1));
            }
        }
        return $columns;
    }
    /**
     * Calculates columns widths.
     */
    private function calculate_columns_width(iterable $groups): void
    {
        for ($column = 0; $column < $this->number_of_columns; ++$column) {
            $lengths = [];
            foreach ($groups as $group) {
                foreach ($group as $row) {
                    if ($row instanceof Table_Separator) {
                        continue;
                    }
                    foreach ($row as $i => $cell) {
                        if ($cell instanceof Table_Cell) {
                            $text_content = Helper::remove_decoration($this->output->get_formatter(), $cell);
                            $text_length = Helper::width($text_content);
                            if ($text_length > 0) {
                                $content_columns = mb_str_split($text_content, ceil($text_length / $cell->get_colspan()));
                                foreach ($content_columns as $position => $content) {
                                    $row[$i + $position] = $content;
                                }
                            }
                        }
                    }
                    $lengths[] = $this->get_cell_width($row, $column);
                }
            }
            $this->effective_column_widths[$column] = max($lengths) + Helper::width($this->style->get_cell_row_content_format()) - 2;
        }
    }
    private function get_column_separator_width(): int
    {
        return Helper::width(\sprintf($this->style->get_border_format(), $this->style->get_border_chars()[3]));
    }
    private function get_cell_width(array $row, int $column): int
    {
        $cell_width = 0;
        if (isset($row[$column])) {
            $cell = $row[$column];
            $cell_width = Helper::width(Helper::remove_decoration($this->output->get_formatter(), $cell));
        }
        $column_width = $this->column_widths[$column] ?? 0;
        $cell_width = max($cell_width, $column_width);
        return isset($this->column_max_widths[$column]) ? min($this->column_max_widths[$column], $cell_width) : $cell_width;
    }
    /**
     * Called after rendering to cleanup cache data.
     */
    private function cleanup(): void
    {
        $this->effective_column_widths = [];
        unset($this->number_of_columns);
    }
    /**
     * @return array<string, TableStyle>
     */
    private static function init_styles(): array
    {
        $markdown = new Table_Style();
        $markdown->set_default_crossing_char('|')->set_display_outside_border(false);
        $borderless = new Table_Style();
        $borderless->set_horizontal_border_chars('=')->set_vertical_border_chars(' ')->set_default_crossing_char(' ');
        $compact = new Table_Style();
        $compact->set_horizontal_border_chars('')->set_vertical_border_chars('')->set_default_crossing_char('')->set_cell_row_content_format('%s ');
        $style_guide = new Table_Style();
        $style_guide->set_horizontal_border_chars('-')->set_vertical_border_chars(' ')->set_default_crossing_char(' ')->set_cell_header_format('%s');
        $box = (new Table_Style())->set_horizontal_border_chars('─')->set_vertical_border_chars('│')->set_crossing_chars('┼', '┌', '┬', '┐', '┤', '┘', '┴', '└', '├');
        $box_double = (new Table_Style())->set_horizontal_border_chars('═', '─')->set_vertical_border_chars('║', '│')->set_crossing_chars('┼', '╔', '╤', '╗', '╢', '╝', '╧', '╚', '╟', '╠', '╪', '╣');
        return ['default' => new Table_Style(), 'markdown' => $markdown, 'borderless' => $borderless, 'compact' => $compact, 'symfony-style-guide' => $style_guide, 'box' => $box, 'box-double' => $box_double];
    }
    private function resolve_style(Table_Style|string $name): Table_Style
    {
        if ($name instanceof Table_Style) {
            return $name;
        }
        return self::$styles[$name] ?? throw new InvalidArgumentException(\sprintf('Style "%s" is not defined.', $name));
    }
}