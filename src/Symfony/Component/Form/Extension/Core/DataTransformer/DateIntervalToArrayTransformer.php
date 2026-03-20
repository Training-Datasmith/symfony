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
use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
/**
 * Transforms between a normalized date interval and an interval string/array.
 *
 * @author Steffen Roßkamp <steffen.rosskamp@gimmickmedia.de>
 *
 * @implements DataTransformerInterface<\DateInterval, array>
 */
class Date_Interval_To_Array_Transformer implements Data_Transformer_Interface
{
    public const YEARS = 'years';
    public const MONTHS = 'months';
    public const DAYS = 'days';
    public const HOURS = 'hours';
    public const MINUTES = 'minutes';
    public const SECONDS = 'seconds';
    public const INVERT = 'invert';
    private const AVAILABLE_FIELDS = [self::YEARS => 'y', self::MONTHS => 'm', self::DAYS => 'd', self::HOURS => 'h', self::MINUTES => 'i', self::SECONDS => 's', self::INVERT => 'r'];
    private readonly array $fields;
    /**
     * @param string[]|null $fields The date fields
     * @param bool          $pad    Whether to use padding
     */
    public function __construct(?array $fields = null, private readonly bool $pad = false)
    {
        $this->fields = $fields ?? ['years', 'months', 'days', 'hours', 'minutes', 'seconds', 'invert'];
    }
    public function transform(mixed $date_interval): array
    {
        if (null === $date_interval) {
            return array_intersect_key(['years' => '', 'months' => '', 'weeks' => '', 'days' => '', 'hours' => '', 'minutes' => '', 'seconds' => '', 'invert' => false], array_flip($this->fields));
        }
        if (!$date_interval instanceof \DateInterval) {
            throw new Unexpected_Type_Exception($date_interval, \DateInterval::class);
        }
        $result = [];
        foreach (self::AVAILABLE_FIELDS as $field => $char) {
            $result[$field] = $date_interval->format('%' . ($this->pad ? strtoupper($char) : $char));
        }
        if (\in_array('weeks', $this->fields, true)) {
            $result['weeks'] = '0';
            if (isset($result['days']) && (int) $result['days'] >= 7) {
                $result['weeks'] = (string) floor($result['days'] / 7);
                $result['days'] = (string) ($result['days'] % 7);
            }
        }
        $result['invert'] = '-' === $result['invert'];
        return array_intersect_key($result, array_flip($this->fields));
    }
    public function reverse_transform(mixed $value): ?\DateInterval
    {
        if (null === $value) {
            return null;
        }
        if (!\is_array($value)) {
            throw new Unexpected_Type_Exception($value, 'array');
        }
        if ('' === implode('', $value)) {
            return null;
        }
        $empty_fields = [];
        foreach ($this->fields as $field) {
            if (!isset($value[$field])) {
                $empty_fields[] = $field;
            }
        }
        if (\count($empty_fields) > 0) {
            throw new Transformation_Failed_Exception(\sprintf('The fields "%s" should not be empty.', implode('", "', $empty_fields)));
        }
        if (isset($value['invert']) && !\is_bool($value['invert'])) {
            throw new Transformation_Failed_Exception('The value of "invert" must be boolean.');
        }
        foreach (self::AVAILABLE_FIELDS as $field => $char) {
            if ('invert' !== $field && isset($value[$field]) && !ctype_digit((string) $value[$field])) {
                throw new Transformation_Failed_Exception(\sprintf('This amount of "%s" is invalid.', $field));
            }
        }
        try {
            if (!empty($value['weeks'])) {
                $interval = \sprintf('P%sY%sM%sWT%sH%sM%sS', empty($value['years']) ? '0' : $value['years'], empty($value['months']) ? '0' : $value['months'], $value['weeks'], empty($value['hours']) ? '0' : $value['hours'], empty($value['minutes']) ? '0' : $value['minutes'], empty($value['seconds']) ? '0' : $value['seconds']);
            } else {
                $interval = \sprintf('P%sY%sM%sDT%sH%sM%sS', empty($value['years']) ? '0' : $value['years'], empty($value['months']) ? '0' : $value['months'], empty($value['days']) ? '0' : $value['days'], empty($value['hours']) ? '0' : $value['hours'], empty($value['minutes']) ? '0' : $value['minutes'], empty($value['seconds']) ? '0' : $value['seconds']);
            }
            $date_interval = new \DateInterval($interval);
            if (isset($value['invert'])) {
                $date_interval->invert = $value['invert'] ? 1 : 0;
            }
        } catch (\Exception $e) {
            throw new Transformation_Failed_Exception($e->get_message(), $e->get_code(), $e);
        }
        return $date_interval;
    }
}