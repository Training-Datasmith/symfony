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
namespace Symfony\Bridge\Doctrine\Types;

use Doctrine\DBAL\Platforms\Abstract_Platform;
use Doctrine\DBAL\Types\Date_Time_Immutable_Type;
use Symfony\Component\Clock\Date_Point;
final class Date_Point_Type extends Date_Time_Immutable_Type
{
    public const NAME = 'date_point';
    /**
     * @param T $value
     *
     * @return (T is null ? null : DatePoint)
     *
     * @template T
     */
    public function convert_to_php_value(mixed $value, Abstract_Platform $platform): ?Date_Point
    {
        if (null === $value || $value instanceof Date_Point) {
            return $value;
        }
        $value = parent::convert_to_php_value($value, $platform);
        return Date_Point::create_from_interface($value);
    }
    public function get_name(): string
    {
        return self::NAME;
    }
}