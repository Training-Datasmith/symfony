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

use Ramsey\Uuid\UuidInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
final class UuidCaster
{
    public static function castRamseyUuid(UuidInterface $c, array $a): array
    {
        return $a + [
            Caster::PREFIX_VIRTUAL.'uuid' => (string) $c,
        ];
    }
}
