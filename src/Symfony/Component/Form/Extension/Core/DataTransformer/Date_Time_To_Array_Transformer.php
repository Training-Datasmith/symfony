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

use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
/**
 * Transforms between a normalized time and a localized time string/array.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @extends BaseDateTimeTransformer<array>
 */
class Date_Time_To_Array_Transformer extends Base_Date_Time_Transformer
{
    private readonly array $fields;
    /**
     * @param string|null   $inputTimezone  The input timezone
     * @param string|null   $outputTimezone The output timezone
     * @param string[]|null $fields         The date fields
     * @param bool          $pad            Whether to use padding
     */
    public function __construct(?string $input_timezone = null, ?string $output_timezone = null, ?array $fields = null, private readonly bool $pad = false, private readonly ?\DateTimeInterface $reference_date = new \DateTimeImmutable('1970-01-01 00:00:00'))
    {
        parent::__construct($input_timezone, $output_timezone);
        $this->fields = $fields ?? ['year', 'month', 'day', 'hour', 'minute', 'second'];
    }
    public function transform(mixed $date_time): array
    {
        if (null === $date_time) {
            return array_intersect_key(['year' => '', 'month' => '', 'day' => '', 'hour' => '', 'minute' => '', 'second' => ''], array_flip($this->fields));
        }
        if (!$date_time instanceof \DateTimeInterface) {
            throw new Transformation_Failed_Exception('Expected a \DateTimeInterface.');
        }
        if ($this->input_timezone !== $this->output_timezone) {
            $date_time = \DateTimeImmutable::create_from_interface($date_time);
            $date_time = $date_time->set_timezone(new \DateTimeZone($this->output_timezone));
        }
        $result = array_intersect_key(['year' => $date_time->format('Y'), 'month' => $date_time->format('m'), 'day' => $date_time->format('d'), 'hour' => $date_time->format('H'), 'minute' => $date_time->format('i'), 'second' => $date_time->format('s')], array_flip($this->fields));
        if (!$this->pad) {
            foreach ($result as &$entry) {
                // remove leading zeros
                $entry = (string) (int) $entry;
            }
            // unset reference to keep scope clear
            unset($entry);
        }
        return $result;
    }
    public function reverse_transform(mixed $value): ?\DateTime
    {
        if (null === $value) {
            return null;
        }
        if (!\is_array($value)) {
            throw new Transformation_Failed_Exception('Expected an array.');
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
        if (isset($value['month']) && !ctype_digit((string) $value['month'])) {
            throw new Transformation_Failed_Exception('This month is invalid.');
        }
        if (isset($value['day']) && !ctype_digit((string) $value['day'])) {
            throw new Transformation_Failed_Exception('This day is invalid.');
        }
        if (isset($value['year']) && !ctype_digit((string) $value['year'])) {
            throw new Transformation_Failed_Exception('This year is invalid.');
        }
        if (!empty($value['month']) && !empty($value['day']) && !empty($value['year']) && false === checkdate($value['month'], $value['day'], $value['year'])) {
            throw new Transformation_Failed_Exception('This is an invalid date.');
        }
        if (isset($value['hour']) && !ctype_digit((string) $value['hour'])) {
            throw new Transformation_Failed_Exception('This hour is invalid.');
        }
        if (isset($value['minute']) && !ctype_digit((string) $value['minute'])) {
            throw new Transformation_Failed_Exception('This minute is invalid.');
        }
        if (isset($value['second']) && !ctype_digit((string) $value['second'])) {
            throw new Transformation_Failed_Exception('This second is invalid.');
        }
        try {
            $date_time = new \DateTime(\sprintf('%s-%s-%s %s:%s:%s', empty($value['year']) ? $this->reference_date->format('Y') : $value['year'], empty($value['month']) ? $this->reference_date->format('m') : $value['month'], empty($value['day']) ? $this->reference_date->format('d') : $value['day'], $value['hour'] ?? $this->reference_date->format('H'), $value['minute'] ?? $this->reference_date->format('i'), $value['second'] ?? $this->reference_date->format('s')), new \DateTimeZone($this->output_timezone));
            if ($this->input_timezone !== $this->output_timezone) {
                $date_time->set_timezone(new \DateTimeZone($this->input_timezone));
            }
        } catch (\Exception $e) {
            throw new Transformation_Failed_Exception($e->get_message(), $e->get_code(), $e);
        }
        return $date_time;
    }
}