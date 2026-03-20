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
namespace Symfony\Component\Console;

use Symfony\Component\Console\Output\Ansi_Color_Mode;
class Terminal
{
    public const DEFAULT_COLOR_MODE = Ansi_Color_Mode::Ansi4;
    private static ?Ansi_Color_Mode $color_mode = null;
    private static ?int $width = null;
    private static ?int $height = null;
    private static ?bool $stty = null;
    private static ?bool $kitty_graphics = null;
    private static ?bool $iterm2Images = null;
    /**
     * About Ansi color types: https://en.wikipedia.org/wiki/ANSI_escape_code#Colors
     * For more information about true color support with terminals https://github.com/termstandard/colors/.
     */
    public static function get_color_mode(): Ansi_Color_Mode
    {
        // Use Cache from previous run (or user forced mode)
        if (null !== self::$color_mode) {
            return self::$color_mode;
        }
        // Try with $COLORTERM first
        if (\is_string($colorterm = getenv('COLORTERM'))) {
            $colorterm = strtolower($colorterm);
            if (str_contains($colorterm, 'truecolor')) {
                self::set_color_mode(Ansi_Color_Mode::Ansi24);
                return self::$color_mode;
            }
            if (str_contains($colorterm, '256color')) {
                self::set_color_mode(Ansi_Color_Mode::Ansi8);
                return self::$color_mode;
            }
        }
        // Try with $TERM
        if (\is_string($term = getenv('TERM'))) {
            $term = strtolower($term);
            if (str_contains($term, 'truecolor')) {
                self::set_color_mode(Ansi_Color_Mode::Ansi24);
                return self::$color_mode;
            }
            if (str_contains($term, '256color')) {
                self::set_color_mode(Ansi_Color_Mode::Ansi8);
                return self::$color_mode;
            }
        }
        self::set_color_mode(self::DEFAULT_COLOR_MODE);
        return self::$color_mode;
    }
    /**
     * Force a terminal color mode rendering.
     */
    public static function set_color_mode(?Ansi_Color_Mode $color_mode): void
    {
        self::$color_mode = $color_mode;
    }
    public function get_width(): int
    {
        $width = getenv('COLUMNS');
        if (false !== $width) {
            return (int) trim($width);
        }
        if (null === self::$width) {
            self::init_dimensions();
        }
        return self::$width ?: 80;
    }
    public function get_height(): int
    {
        $height = getenv('LINES');
        if (false !== $height) {
            return (int) trim($height);
        }
        if (null === self::$height) {
            self::init_dimensions();
        }
        return self::$height ?: 50;
    }
    /**
     * @internal
     */
    public static function has_stty_available(): bool
    {
        if (null !== self::$stty) {
            return self::$stty;
        }
        // skip check if shell_exec function is disabled
        if (!\function_exists('shell_exec')) {
            return false;
        }
        return self::$stty = (bool) @shell_exec('stty 2> ' . ('\\' === \DIRECTORY_SEPARATOR ? 'NUL' : '/dev/null'));
    }
    public static function supports_kitty_graphics(): bool
    {
        if (null !== self::$kitty_graphics) {
            return self::$kitty_graphics;
        }
        $term_program = getenv('TERM_PROGRAM') ?: '';
        if (\in_array($term_program, ['kitty', 'WezTerm', 'ghostty'], true)) {
            return self::$kitty_graphics = true;
        }
        if (str_contains(getenv('TERM') ?: '', 'kitty')) {
            return self::$kitty_graphics = true;
        }
        if (false !== getenv('GHOSTTY_RESOURCES_DIR')) {
            return self::$kitty_graphics = true;
        }
        if (false !== getenv('KONSOLE_VERSION')) {
            return self::$kitty_graphics = true;
        }
        return self::$kitty_graphics = false;
    }
    public static function supports_i_term2images(): bool
    {
        if (null !== self::$iterm2Images) {
            return self::$iterm2Images;
        }
        return self::$iterm2Images = 'iTerm.app' === getenv('TERM_PROGRAM');
    }
    public static function supports_image_protocol(): bool
    {
        if (self::supports_kitty_graphics()) {
            return true;
        }
        return self::supports_i_term2images();
    }
    public static function set_kitty_graphics_support(?bool $supported): void
    {
        self::$kitty_graphics = $supported;
    }
    public static function set_i_term2images_support(?bool $supported): void
    {
        self::$iterm2Images = $supported;
    }
    private static function init_dimensions(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $ansicon = getenv('ANSICON');
            if (false !== $ansicon && preg_match('/^(\d+)x(\d+)(?: \((\d+)x(\d+)\))?$/', trim($ansicon), $matches)) {
                // extract [w, H] from "wxh (WxH)"
                // or [w, h] from "wxh"
                self::$width = (int) $matches[1];
                self::$height = isset($matches[4]) ? (int) $matches[4] : (int) $matches[2];
            } elseif (!sapi_windows_vt100_support(fopen('php://stdout', 'w')) && self::has_stty_available()) {
                // only use stty on Windows if the terminal does not support vt100 (e.g. Windows 7 + git-bash)
                // testing for stty in a Windows 10 vt100-enabled console will implicitly disable vt100 support on STDOUT
                self::init_dimensions_using_stty();
            } elseif (null !== $dimensions = self::get_console_mode()) {
                // extract [w, h] from "wxh"
                self::$width = (int) $dimensions[0];
                self::$height = (int) $dimensions[1];
            }
        } else {
            self::init_dimensions_using_stty();
        }
    }
    private static function init_dimensions_using_stty(): void
    {
        if ($stty_string = self::get_stty_columns()) {
            if (preg_match('/rows.(\d+);.columns.(\d+);/is', $stty_string, $matches)) {
                // extract [w, h] from "rows h; columns w;"
                self::$width = (int) $matches[2];
                self::$height = (int) $matches[1];
            } elseif (preg_match('/;.(\d+).rows;.(\d+).columns/is', $stty_string, $matches)) {
                // extract [w, h] from "; h rows; w columns"
                self::$width = (int) $matches[2];
                self::$height = (int) $matches[1];
            }
        }
    }
    /**
     * Runs and parses mode CON if it's available, suppressing any error output.
     *
     * @return int[]|null An array composed of the width and the height or null if it could not be parsed
     */
    private static function get_console_mode(): ?array
    {
        $info = self::read_from_process('mode CON');
        if (null === $info || !preg_match('/--------+\r?\n.+?(\d+)\r?\n.+?(\d+)\r?\n/', $info, $matches)) {
            return null;
        }
        return [(int) $matches[2], (int) $matches[1]];
    }
    private static function get_stty_columns(): ?string
    {
        return self::read_from_process(['stty', '-a']);
    }
    private static function read_from_process(string|array $command): ?string
    {
        if (!\function_exists('proc_open')) {
            return null;
        }
        $descriptorspec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $cp = \function_exists('sapi_windows_cp_set') ? sapi_windows_cp_get() : 0;
        if (!$process = @proc_open($command, $descriptorspec, $pipes, null, null, ['suppress_errors' => true])) {
            return null;
        }
        $info = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        if ($cp) {
            sapi_windows_cp_set($cp);
        }
        return $info;
    }
}