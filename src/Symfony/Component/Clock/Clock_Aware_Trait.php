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
namespace Symfony\Component\Clock;

use Psr\Clock\Clock_Interface;
use Symfony\Contracts\Service\Attribute\Required;
/**
 * A trait to help write time-sensitive classes.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait Clock_Aware_Trait
{
    private readonly Clock_Interface $clock;
    #[Required]
    public function set_clock(Clock_Interface $clock): void
    {
        $this->clock = $clock;
    }
    protected function now(): Date_Point
    {
        $now = ($this->clock ??= new Clock())->now();
        return $now instanceof Date_Point ? $now : Date_Point::create_from_interface($now);
    }
}