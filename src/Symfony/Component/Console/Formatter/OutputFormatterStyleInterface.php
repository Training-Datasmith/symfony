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

/**
 * Formatter style interface for defining styles.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
interface Output_Formatter_Style_Interface
{
    /**
     * Sets style foreground color.
     */
    public function set_foreground(?string $color): void;
    /**
     * Sets style background color.
     */
    public function set_background(?string $color): void;
    /**
     * Sets some specific style option.
     */
    public function set_option(string $option): void;
    /**
     * Unsets some specific style option.
     */
    public function unset_option(string $option): void;
    /**
     * Sets multiple style options at once.
     */
    public function set_options(array $options): void;
    /**
     * Applies the style to a given text.
     */
    public function apply(string $text): string;
}