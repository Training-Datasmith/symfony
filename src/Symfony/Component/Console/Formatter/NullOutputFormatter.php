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
 * @author Tien Xuan Vo <tien.xuan.vo@gmail.com>
 */
final class Null_Output_Formatter implements Output_Formatter_Interface
{
    private Null_Output_Formatter_Style $style;
    public function format(?string $message): ?string
    {
        return null;
    }
    public function get_style(string $name): Output_Formatter_Style_Interface
    {
        // to comply with the interface we must return a OutputFormatterStyleInterface
        return $this->style ??= new Null_Output_Formatter_Style();
    }
    public function has_style(string $name): bool
    {
        return false;
    }
    public function is_decorated(): bool
    {
        return false;
    }
    public function set_decorated(bool $decorated): void
    {
        // do nothing
    }
    public function set_style(string $name, Output_Formatter_Style_Interface $style): void
    {
        // do nothing
    }
}