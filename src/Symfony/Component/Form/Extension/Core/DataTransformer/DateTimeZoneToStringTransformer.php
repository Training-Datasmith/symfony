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
 * Transforms between a timezone identifier string and a DateTimeZone object.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @implements DataTransformerInterface<\DateTimeZone|array<\DateTimeZone>, string|array<string>>
 */
class Date_Time_Zone_To_String_Transformer implements Data_Transformer_Interface
{
    public function __construct(private readonly bool $multiple = false)
    {
    }
    public function transform(mixed $date_time_zone): mixed
    {
        if (null === $date_time_zone) {
            return null;
        }
        if ($this->multiple) {
            if (!\is_array($date_time_zone)) {
                throw new Transformation_Failed_Exception('Expected an array of \DateTimeZone objects.');
            }
            return array_map([new self(), 'transform'], $date_time_zone);
        }
        if (!$date_time_zone instanceof \DateTimeZone) {
            throw new Transformation_Failed_Exception('Expected a \DateTimeZone object.');
        }
        return $date_time_zone->get_name();
    }
    public function reverse_transform(mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }
        if ($this->multiple) {
            if (!\is_array($value)) {
                throw new Transformation_Failed_Exception('Expected an array of timezone identifier strings.');
            }
            return array_map([new self(), 'reverseTransform'], $value);
        }
        if (!\is_string($value)) {
            throw new Transformation_Failed_Exception('Expected a timezone identifier string.');
        }
        try {
            return new \DateTimeZone($value);
        } catch (\Exception $e) {
            throw new Transformation_Failed_Exception($e->get_message(), $e->get_code(), $e);
        }
    }
}