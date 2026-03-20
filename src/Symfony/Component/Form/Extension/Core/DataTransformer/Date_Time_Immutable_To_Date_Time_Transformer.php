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

use Symfony\Component\Form\Data_Transformer_Interface;
use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
/**
 * Transforms between a DateTimeImmutable object and a DateTime object.
 *
 * @author Valentin Udaltsov <udaltsov.valentin@gmail.com>
 *
 * @implements DataTransformerInterface<\DateTimeImmutable, \DateTime>
 */
final class Date_Time_Immutable_To_Date_Time_Transformer implements Data_Transformer_Interface
{
    public function transform(mixed $value): ?\DateTime
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof \DateTimeImmutable) {
            throw new Transformation_Failed_Exception('Expected a \DateTimeImmutable.');
        }
        return \DateTime::create_from_immutable($value);
    }
    public function reverse_transform(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof \DateTime) {
            throw new Transformation_Failed_Exception('Expected a \DateTime.');
        }
        return \DateTimeImmutable::create_from_mutable($value);
    }
}