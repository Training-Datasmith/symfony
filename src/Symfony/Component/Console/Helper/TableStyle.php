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
use Symfony\Component\Console\Exception\LogicException;
/**
 * Defines the styles for a Table.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Саша Стаменковић <umpirsky@gmail.com>
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class Table_Style
{
    private string $padding_char = ' ';
    private string $horizontal_outside_border_char = '-';
    private string $horizontal_inside_border_char = '-';
    private string $vertical_outside_border_char = '|';
    private string $vertical_inside_border_char = '|';
    private string $crossing_char = '+';
    private string $crossing_top_right_char = '+';
    private string $crossing_top_mid_char = '+';
    private string $crossing_top_left_char = '+';
    private string $crossing_mid_right_char = '+';
    private string $crossing_bottom_right_char = '+';
    private string $crossing_bottom_mid_char = '+';
    private string $crossing_bottom_left_char = '+';
    private string $crossing_mid_left_char = '+';
    private string $crossing_top_left_bottom_char = '+';
    private string $crossing_top_mid_bottom_char = '+';
    private string $crossing_top_right_bottom_char = '+';
    private string $header_title_format = '<fg=black;bg=white;options=bold> %s </>';
    private string $footer_title_format = '<fg=black;bg=white;options=bold> %s </>';
    private string $cell_header_format = '<info>%s</info>';
    private string $cell_row_format = '%s';
    private string $cell_row_content_format = ' %s ';
    private string $border_format = '%s';
    private bool $display_outside_border = true;
    private int $pad_type = \STR_PAD_RIGHT;
    /**
     * Sets padding character, used for cell padding.
     *
     * @return $this
     */
    public function set_padding_char(string $padding_char): static
    {
        if (!$padding_char) {
            throw new LogicException('The padding char must not be empty.');
        }
        $this->padding_char = $padding_char;
        return $this;
    }
    /**
     * Gets padding character, used for cell padding.
     */
    public function get_padding_char(): string
    {
        return $this->padding_char;
    }
    /**
     * Sets horizontal border characters.
     *
     * <code>
     * ╔═══════════════╤══════════════════════════╤══════════════════╗
     * ║ ISBN          │ Title                    │ Author           ║
     * ╠═══════1═══════╪══════════════════════════╪══════════════════╣
     * ║ 99921-58-10-7 │ Divine Comedy            │ Dante Alighieri  ║
     * ║ 9971-5-0210-0 │ A Tale of Two Cities     │ Charles Dickens  ║
     * ╟───────2───────┼──────────────────────────┼──────────────────╢
     * ║ 960-425-059-0 │ The Lord of the Rings    │ J. R. R. Tolkien ║
     * ║ 80-902734-1-6 │ And Then There Were None │ Agatha Christie  ║
     * ╚═══════════════╧══════════════════════════╧══════════════════╝
     * </code>
     *
     * @return $this
     */
    public function set_horizontal_border_chars(string $outside, ?string $inside = null): static
    {
        $this->horizontal_outside_border_char = $outside;
        $this->horizontal_inside_border_char = $inside ?? $outside;
        return $this;
    }
    /**
     * Sets vertical border characters.
     *
     * <code>
     * ╔═══════════════╤══════════════════════════╤══════════════════╗
     * 1 ISBN          2 Title                    │ Author           ║
     * ╠═══════════════╪══════════════════════════╪══════════════════╣
     * ║ 99921-58-10-7 │ Divine Comedy            │ Dante Alighieri  ║
     * ║ 9971-5-0210-0 │ A Tale of Two Cities     │ Charles Dickens  ║
     * ║ 960-425-059-0 │ The Lord of the Rings    │ J. R. R. Tolkien ║
     * ║ 80-902734-1-6 │ And Then There Were None │ Agatha Christie  ║
     * ╚═══════════════╧══════════════════════════╧══════════════════╝
     * </code>
     *
     * @return $this
     */
    public function set_vertical_border_chars(string $outside, ?string $inside = null): static
    {
        $this->vertical_outside_border_char = $outside;
        $this->vertical_inside_border_char = $inside ?? $outside;
        return $this;
    }
    /**
     * Gets border characters.
     *
     * @internal
     */
    public function get_border_chars(): array
    {
        return [$this->horizontal_outside_border_char, $this->vertical_outside_border_char, $this->horizontal_inside_border_char, $this->vertical_inside_border_char];
    }
    /**
     * Sets crossing characters.
     *
     * Example:
     * <code>
     * 1═══════════════2══════════════════════════2══════════════════3
     * ║ ISBN          │ Title                    │ Author           ║
     * 8'══════════════0'═════════════════════════0'═════════════════4'
     * ║ 99921-58-10-7 │ Divine Comedy            │ Dante Alighieri  ║
     * ║ 9971-5-0210-0 │ A Tale of Two Cities     │ Charles Dickens  ║
     * 8───────────────0──────────────────────────0──────────────────4
     * ║ 960-425-059-0 │ The Lord of the Rings    │ J. R. R. Tolkien ║
     * ║ 80-902734-1-6 │ And Then There Were None │ Agatha Christie  ║
     * 7═══════════════6══════════════════════════6══════════════════5
     * </code>
     *
     * @param string      $cross          Crossing char (see #0 of example)
     * @param string      $topLeft        Top left char (see #1 of example)
     * @param string      $topMid         Top mid char (see #2 of example)
     * @param string      $topRight       Top right char (see #3 of example)
     * @param string      $midRight       Mid right char (see #4 of example)
     * @param string      $bottomRight    Bottom right char (see #5 of example)
     * @param string      $bottomMid      Bottom mid char (see #6 of example)
     * @param string      $bottomLeft     Bottom left char (see #7 of example)
     * @param string      $midLeft        Mid left char (see #8 of example)
     * @param string|null $topLeftBottom  Top left bottom char (see #8' of example), equals to $midLeft if null
     * @param string|null $topMidBottom   Top mid bottom char (see #0' of example), equals to $cross if null
     * @param string|null $topRightBottom Top right bottom char (see #4' of example), equals to $midRight if null
     *
     * @return $this
     */
    public function set_crossing_chars(string $cross, string $top_left, string $top_mid, string $top_right, string $mid_right, string $bottom_right, string $bottom_mid, string $bottom_left, string $mid_left, ?string $top_left_bottom = null, ?string $top_mid_bottom = null, ?string $top_right_bottom = null): static
    {
        $this->crossing_char = $cross;
        $this->crossing_top_left_char = $top_left;
        $this->crossing_top_mid_char = $top_mid;
        $this->crossing_top_right_char = $top_right;
        $this->crossing_mid_right_char = $mid_right;
        $this->crossing_bottom_right_char = $bottom_right;
        $this->crossing_bottom_mid_char = $bottom_mid;
        $this->crossing_bottom_left_char = $bottom_left;
        $this->crossing_mid_left_char = $mid_left;
        $this->crossing_top_left_bottom_char = $top_left_bottom ?? $mid_left;
        $this->crossing_top_mid_bottom_char = $top_mid_bottom ?? $cross;
        $this->crossing_top_right_bottom_char = $top_right_bottom ?? $mid_right;
        return $this;
    }
    /**
     * Sets default crossing character used for each cross.
     *
     * @see {@link setCrossingChars()} for setting each crossing individually.
     */
    public function set_default_crossing_char(string $char): self
    {
        return $this->set_crossing_chars($char, $char, $char, $char, $char, $char, $char, $char, $char);
    }
    /**
     * Gets crossing character.
     */
    public function get_crossing_char(): string
    {
        return $this->crossing_char;
    }
    /**
     * Gets crossing characters.
     *
     * @internal
     */
    public function get_crossing_chars(): array
    {
        return [$this->crossing_char, $this->crossing_top_left_char, $this->crossing_top_mid_char, $this->crossing_top_right_char, $this->crossing_mid_right_char, $this->crossing_bottom_right_char, $this->crossing_bottom_mid_char, $this->crossing_bottom_left_char, $this->crossing_mid_left_char, $this->crossing_top_left_bottom_char, $this->crossing_top_mid_bottom_char, $this->crossing_top_right_bottom_char];
    }
    /**
     * Sets header cell format.
     *
     * @return $this
     */
    public function set_cell_header_format(string $cell_header_format): static
    {
        $this->cell_header_format = $cell_header_format;
        return $this;
    }
    /**
     * Gets header cell format.
     */
    public function get_cell_header_format(): string
    {
        return $this->cell_header_format;
    }
    /**
     * Sets row cell format.
     *
     * @return $this
     */
    public function set_cell_row_format(string $cell_row_format): static
    {
        $this->cell_row_format = $cell_row_format;
        return $this;
    }
    /**
     * Gets row cell format.
     */
    public function get_cell_row_format(): string
    {
        return $this->cell_row_format;
    }
    /**
     * Sets row cell content format.
     *
     * @return $this
     */
    public function set_cell_row_content_format(string $cell_row_content_format): static
    {
        $this->cell_row_content_format = $cell_row_content_format;
        return $this;
    }
    /**
     * Gets row cell content format.
     */
    public function get_cell_row_content_format(): string
    {
        return $this->cell_row_content_format;
    }
    /**
     * Sets table border format.
     *
     * @return $this
     */
    public function set_border_format(string $border_format): static
    {
        $this->border_format = $border_format;
        return $this;
    }
    /**
     * Gets table border format.
     */
    public function get_border_format(): string
    {
        return $this->border_format;
    }
    /**
     * Sets cell padding type.
     *
     * @return $this
     */
    public function set_pad_type(int $pad_type): static
    {
        if (!\in_array($pad_type, [\STR_PAD_LEFT, \STR_PAD_RIGHT, \STR_PAD_BOTH], true)) {
            throw new InvalidArgumentException('Invalid padding type. Expected one of (STR_PAD_LEFT, STR_PAD_RIGHT, STR_PAD_BOTH).');
        }
        $this->pad_type = $pad_type;
        return $this;
    }
    /**
     * Gets cell padding type.
     */
    public function get_pad_type(): int
    {
        return $this->pad_type;
    }
    public function get_header_title_format(): string
    {
        return $this->header_title_format;
    }
    /**
     * @return $this
     */
    public function set_header_title_format(string $format): static
    {
        $this->header_title_format = $format;
        return $this;
    }
    public function get_footer_title_format(): string
    {
        return $this->footer_title_format;
    }
    /**
     * @return $this
     */
    public function set_footer_title_format(string $format): static
    {
        $this->footer_title_format = $format;
        return $this;
    }
    public function set_display_outside_border(bool $display_out_side_border): static
    {
        $this->display_outside_border = $display_out_side_border;
        return $this;
    }
    public function display_outside_border(): bool
    {
        return $this->display_outside_border;
    }
}