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

use Symfony\Component\Console\Exception\InvalidArgumentException;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Julien Boudry <julien@condorcet.vote>
 */
enum Ansi_Color_Mode
{
    /*
     * Classical 4-bit Ansi colors, including 8 classical colors and 8 bright color. Output syntax is "ESC[${foreGroundColorcode};${backGroundColorcode}m"
     * Must be compatible with all terminals and it's the minimal version supported.
     */
    case Ansi4;
    /*
     * 8-bit Ansi colors (240 different colors + 16 duplicate color codes, ensuring backward compatibility).
     * Output syntax is: "ESC[38;5;${foreGroundColorcode};48;5;${backGroundColorcode}m"
     * Should be compatible with most terminals.
     */
    case Ansi8;
    /*
     * 24-bit Ansi colors (RGB).
     * Output syntax is: "ESC[38;2;${foreGroundColorcodeRed};${foreGroundColorcodeGreen};${foreGroundColorcodeBlue};48;2;${backGroundColorcodeRed};${backGroundColorcodeGreen};${backGroundColorcodeBlue}m"
     * May be compatible with many modern terminals.
     */
    case Ansi24;
    /**
     * Converts an RGB hexadecimal color to the corresponding Ansi code.
     */
    public function convert_from_hex_to_ansi_color_code(string $hex_color): string
    {
        $hex_color = str_replace('#', '', $hex_color);
        if (3 === \strlen($hex_color)) {
            $hex_color = $hex_color[0] . $hex_color[0] . $hex_color[1] . $hex_color[1] . $hex_color[2] . $hex_color[2];
        }
        if (6 !== \strlen($hex_color)) {
            throw new InvalidArgumentException(\sprintf('Invalid "#%s" color.', $hex_color));
        }
        $color = hexdec($hex_color);
        $r = $color >> 16 & 255;
        $g = $color >> 8 & 255;
        $b = $color & 255;
        return match ($this) {
            self::Ansi4 => (string) $this->convert_from_rgb($r, $g, $b),
            self::Ansi8 => '8;5;' . $this->convert_from_rgb($r, $g, $b),
            self::Ansi24 => \sprintf('8;2;%d;%d;%d', $r, $g, $b),
        };
    }
    private function convert_from_rgb(int $r, int $g, int $b): int
    {
        return match ($this) {
            self::Ansi4 => $this->degrade_hex_color_to_ansi4($r, $g, $b),
            self::Ansi8 => $this->degrade_hex_color_to_ansi8($r, $g, $b),
            default => throw new InvalidArgumentException("RGB cannot be converted to {$this->name}."),
        };
    }
    private function degrade_hex_color_to_ansi4(int $r, int $g, int $b): int
    {
        return round($b / 255) << 2 | round($g / 255) << 1 | round($r / 255);
    }
    /**
     * Inspired from https://github.com/ajalt/colormath/blob/e464e0da1b014976736cf97250063248fc77b8e7/colormath/src/commonMain/kotlin/com/github/ajalt/colormath/model/Ansi256.kt code (MIT license).
     */
    private function degrade_hex_color_to_ansi8(int $r, int $g, int $b): int
    {
        if ($r === $g && $g === $b) {
            if ($r < 8) {
                return 16;
            }
            if ($r > 248) {
                return 231;
            }
            return (int) round(($r - 8) / 247 * 24) + 232;
        }
        return 16 + 36 * (int) round($r / 255 * 5) + 6 * (int) round($g / 255 * 5) + (int) round($b / 255 * 5);
    }
}