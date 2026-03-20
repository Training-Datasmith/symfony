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
final class Null_Output_Formatter_Style implements Output_Formatter_Style_Interface
{
    public function apply(string $text): string
    {
        return $text;
    }
    public function set_background(?string $color): void
    {
        // do nothing
    }
    public function set_foreground(?string $color): void
    {
        // do nothing
    }
    public function set_option(string $option): void
    {
        // do nothing
    }
    public function set_options(array $options): void
    {
        // do nothing
    }
    public function unset_option(string $option): void
    {
        // do nothing
    }
}