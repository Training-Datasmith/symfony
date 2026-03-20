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

if (!\function_exists(now::class)) {
    /**
     * @throws \DateMalformedStringException When the modifier is invalid
     */
    function now(string $modifier = 'now'): Date_Point
    {
        if ('now' !== $modifier) {
            return new Date_Point($modifier);
        }
        $now = Clock::get()->now();
        return $now instanceof Date_Point ? $now : Date_Point::create_from_interface($now);
    }
}