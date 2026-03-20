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
 * Transforms between a timezone identifier string and a IntlTimeZone object.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @implements DataTransformerInterface<\IntlTimeZone|array<\IntlTimeZone>, string|array<string>>
 */
class Intl_Time_Zone_To_String_Transformer implements Data_Transformer_Interface
{
    public function __construct(private readonly bool $multiple = false)
    {
    }
    public function transform(mixed $intl_time_zone): mixed
    {
        if (null === $intl_time_zone) {
            return null;
        }
        if ($this->multiple) {
            if (!\is_array($intl_time_zone)) {
                throw new Transformation_Failed_Exception('Expected an array of \IntlTimeZone objects.');
            }
            return array_map([new self(), 'transform'], $intl_time_zone);
        }
        if (!$intl_time_zone instanceof \Intl_Time_Zone) {
            throw new Transformation_Failed_Exception('Expected a \IntlTimeZone object.');
        }
        return $intl_time_zone->get_id();
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
        $intl_time_zone = \Intl_Time_Zone::create_time_zone($value);
        if ('Etc/Unknown' === $intl_time_zone->get_id()) {
            throw new Transformation_Failed_Exception(\sprintf('Unknown timezone identifier "%s".', $value));
        }
        return $intl_time_zone;
    }
}