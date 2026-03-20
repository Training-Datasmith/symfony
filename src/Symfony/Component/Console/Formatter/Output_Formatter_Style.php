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

use Symfony\Component\Console\Color;
/**
 * Formatter style class for defining styles.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class Output_Formatter_Style implements Output_Formatter_Style_Interface
{
    private Color $color;
    private string $foreground;
    private string $background;
    private array $options;
    private ?string $href = null;
    private bool $handles_href_gracefully;
    /**
     * Initializes output formatter style.
     *
     * @param string|null $foreground The style foreground color name
     * @param string|null $background The style background color name
     */
    public function __construct(?string $foreground = null, ?string $background = null, array $options = [])
    {
        $this->color = new Color($this->foreground = $foreground ?: '', $this->background = $background ?: '', $this->options = $options);
    }
    public function set_foreground(?string $color): void
    {
        $this->color = new Color($this->foreground = $color ?: '', $this->background, $this->options);
    }
    public function set_background(?string $color): void
    {
        $this->color = new Color($this->foreground, $this->background = $color ?: '', $this->options);
    }
    public function set_href(string $url): void
    {
        $this->href = $url;
    }
    public function set_option(string $option): void
    {
        $this->options[] = $option;
        $this->color = new Color($this->foreground, $this->background, $this->options);
    }
    public function unset_option(string $option): void
    {
        $pos = array_search($option, $this->options);
        if (false !== $pos) {
            unset($this->options[$pos]);
        }
        $this->color = new Color($this->foreground, $this->background, $this->options);
    }
    public function set_options(array $options): void
    {
        $this->color = new Color($this->foreground, $this->background, $this->options = $options);
    }
    public function apply(string $text): string
    {
        $this->handles_href_gracefully ??= 'JetBrains-JediTerm' !== getenv('TERMINAL_EMULATOR') && (!getenv('KONSOLE_VERSION') || (int) getenv('KONSOLE_VERSION') > 201100) && !isset($_SERVER['IDEA_INITIAL_DIRECTORY']);
        if (null !== $this->href && $this->handles_href_gracefully) {
            $text = "\x1b]8;;{$this->href}\x1b\\{$text}\x1b]8;;\x1b\\";
        }
        return $this->color->apply($text);
    }
}