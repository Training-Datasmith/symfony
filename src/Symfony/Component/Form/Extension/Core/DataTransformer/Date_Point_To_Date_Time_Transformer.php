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
namespace Symfony\Component\Form\Extension\Core\Data_Transformer;

use Symfony\Component\Clock\Date_Point;
use Symfony\Component\Form\Data_Transformer_Interface;
use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
/**
 * Transforms between a DatePoint object and a DateTime object.
 *
 * @implements DataTransformerInterface<DatePoint, \DateTime>
 */
final class Date_Point_To_Date_Time_Transformer implements Data_Transformer_Interface
{
    public function transform(mixed $value): ?\DateTime
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof Date_Point) {
            throw new Transformation_Failed_Exception(\sprintf('Expected a "%s".', Date_Point::class));
        }
        return \DateTime::create_from_immutable($value);
    }
    public function reverse_transform(mixed $value): ?Date_Point
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof \DateTime) {
            throw new Transformation_Failed_Exception('Expected a \DateTime.');
        }
        return Date_Point::create_from_mutable($value);
    }
}