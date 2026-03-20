<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class CurlCaster
{
    public static function castCurl(\CurlHandle $h, array $a): array
    {
        foreach (curl_getinfo($h) as $key => $val) {
            $a[Caster::PREFIX_VIRTUAL.$key] = $val;
        }

        return $a;
    }
}
