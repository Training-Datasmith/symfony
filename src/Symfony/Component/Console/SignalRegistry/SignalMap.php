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
namespace Symfony\Component\Console\Signal_Registry;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Signal_Map
{
    private static array $map;
    public static function get_signal_name(int $signal): ?string
    {
        if (!\extension_loaded('pcntl')) {
            return null;
        }
        if (!isset(self::$map)) {
            $r = new \ReflectionExtension('pcntl');
            $c = $r->get_constants();
            $map = array_filter($c, static fn($k): bool => str_starts_with((string) $k, 'SIG') && !str_starts_with((string) $k, 'SIG_') && 'SIGBABY' !== $k, \ARRAY_FILTER_USE_KEY);
            self::$map = array_flip($map);
        }
        return self::$map[$signal] ?? null;
    }
}