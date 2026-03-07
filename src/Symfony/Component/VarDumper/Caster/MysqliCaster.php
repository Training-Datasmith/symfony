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
final class MysqliCaster
{
    public static function castMysqliDriver(\mysqli_driver $c, array $a): array
    {
        foreach ($a as $k => $v) {
            if (isset($c->$k)) {
                $a[$k] = $c->$k;
            }
        }

        return $a;
    }
}
