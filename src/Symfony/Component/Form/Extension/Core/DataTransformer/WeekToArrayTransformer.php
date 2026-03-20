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
 * Transforms between an ISO 8601 week date string and an array.
 *
 * @author Damien Fayet <damienf1521@gmail.com>
 *
 * @implements DataTransformerInterface<string, array{year: int|null, week: int|null}>
 */
class Week_To_Array_Transformer implements Data_Transformer_Interface
{
    public function transform(mixed $value): array
    {
        if (null === $value) {
            return ['year' => null, 'week' => null];
        }
        if (!\is_string($value)) {
            throw new Transformation_Failed_Exception(\sprintf('Value is expected to be a string but was "%s".', get_debug_type($value)));
        }
        if (0 === preg_match('/^(?P<year>\d{4})-W(?P<week>\d{2})$/', $value, $matches)) {
            throw new Transformation_Failed_Exception('Given data does not follow the date format "Y-\WW".');
        }
        return ['year' => (int) $matches['year'], 'week' => (int) $matches['week']];
    }
    public function reverse_transform(mixed $value): ?string
    {
        if (null === $value || [] === $value) {
            return null;
        }
        if (!\is_array($value)) {
            throw new Transformation_Failed_Exception(\sprintf('Value is expected to be an array, but was "%s".', get_debug_type($value)));
        }
        if (!\array_key_exists('year', $value)) {
            throw new Transformation_Failed_Exception('Key "year" is missing.');
        }
        if (!\array_key_exists('week', $value)) {
            throw new Transformation_Failed_Exception('Key "week" is missing.');
        }
        if ($additional_keys = array_diff(array_keys($value), ['year', 'week'])) {
            throw new Transformation_Failed_Exception(\sprintf('Expected only keys "year" and "week" to be present, but also got ["%s"].', implode('", "', $additional_keys)));
        }
        if (null === $value['year'] && null === $value['week']) {
            return null;
        }
        if (!\is_int($value['year'])) {
            throw new Transformation_Failed_Exception(\sprintf('Year is expected to be an integer, but was "%s".', get_debug_type($value['year'])));
        }
        if (!\is_int($value['week'])) {
            throw new Transformation_Failed_Exception(\sprintf('Week is expected to be an integer, but was "%s".', get_debug_type($value['week'])));
        }
        // The 28th December is always in the last week of the year
        if (date('W', strtotime('28th December ' . $value['year'])) < $value['week']) {
            throw new Transformation_Failed_Exception(\sprintf('Week "%d" does not exist for year "%d".', $value['week'], $value['year']));
        }
        return \sprintf('%d-W%02d', $value['year'], $value['week']);
    }
}