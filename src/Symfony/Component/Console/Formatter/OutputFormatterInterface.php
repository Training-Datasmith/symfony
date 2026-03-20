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
 * Formatter interface for console output.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
interface Output_Formatter_Interface
{
    /**
     * Sets the decorated flag.
     */
    public function set_decorated(bool $decorated): void;
    /**
     * Whether the output will decorate messages.
     */
    public function is_decorated(): bool;
    /**
     * Sets a new style.
     */
    public function set_style(string $name, Output_Formatter_Style_Interface $style): void;
    /**
     * Checks if output formatter has style with specified name.
     */
    public function has_style(string $name): bool;
    /**
     * Gets style options from style with specified name.
     *
     * @throws \InvalidArgumentException When style isn't defined
     */
    public function get_style(string $name): Output_Formatter_Style_Interface;
    /**
     * Formats a message according to the given styles.
     */
    public function format(?string $message): ?string;
}