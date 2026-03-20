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
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
final class SqliteCaster
{
    public static function castSqlite3Result(\SQLite3Result $result, array $a): array
    {
        $numColumns = $result->numColumns();
        for ($i = 0; $i < $numColumns; ++$i) {
            $a[Caster::PREFIX_VIRTUAL.'columnNames'][$i] = $result->columnName($i);
        }

        return $a;
    }
}
