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
 * Transforms between a date string and a DateTime object.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @extends BaseDateTimeTransformer<string>
 */
class Date_Time_To_String_Transformer extends Base_Date_Time_Transformer
{
    /**
     * Format used for parsing strings.
     *
     * Different than the {@link $generateFormat} because formats for parsing
     * support additional characters in PHP that are not supported for
     * generating strings.
     */
    private string $parse_format;
    /**
     * Transforms a \DateTime instance to a string.
     *
     * @see \DateTime::format() for supported formats
     *
     * @param string|null $inputTimezone  The name of the input timezone
     * @param string|null $outputTimezone The name of the output timezone
     * @param string $generateFormat The date format
     * @param string|null $parseFormat    The parse format when different from $format
     */
    public function __construct(?string $input_timezone = null, ?string $output_timezone = null, private readonly string $generate_format = 'Y-m-d H:i:s', ?string $parse_format = null)
    {
        parent::__construct($input_timezone, $output_timezone);
        $this->parse_format = $parse_format ?? $this->generate_format;
        // See https://php.net/datetime.createfromformat
        // The character "|" in the format makes sure that the parts of a date
        // that are *not* specified in the format are reset to the corresponding
        // values from 1970-01-01 00:00:00 instead of the current time.
        // Without "|" and "Y-m-d", "2010-02-03" becomes "2010-02-03 12:32:47",
        // where the time corresponds to the current server time.
        // With "|" and "Y-m-d", "2010-02-03" becomes "2010-02-03 00:00:00",
        // which is at least deterministic and thus used here.
        if (!str_contains($this->parse_format, '|')) {
            $this->parse_format .= '|';
        }
    }
    public function transform(mixed $date_time): string
    {
        if (null === $date_time) {
            return '';
        }
        if (!$date_time instanceof \DateTimeInterface) {
            throw new Transformation_Failed_Exception('Expected a \DateTimeInterface.');
        }
        $date_time = \DateTimeImmutable::create_from_interface($date_time);
        $date_time = $date_time->set_timezone(new \DateTimeZone($this->output_timezone));
        return $date_time->format($this->generate_format);
    }
    public function reverse_transform(mixed $value): ?\DateTime
    {
        if (!$value) {
            return null;
        }
        if (!\is_string($value)) {
            throw new Transformation_Failed_Exception('Expected a string.');
        }
        if (str_contains($value, "\x00")) {
            throw new Transformation_Failed_Exception('Null bytes not allowed.');
        }
        $output_tz = new \DateTimeZone($this->output_timezone);
        $date_time = \DateTime::create_from_format($this->parse_format, $value, $output_tz);
        $last_errors = \DateTime::get_last_errors() ?: ['error_count' => 0, 'warning_count' => 0];
        if (0 < $last_errors['warning_count'] || 0 < $last_errors['error_count']) {
            throw new Transformation_Failed_Exception(implode(', ', array_merge(array_values($last_errors['warnings']), array_values($last_errors['errors']))));
        }
        try {
            if ($this->input_timezone !== $this->output_timezone) {
                $date_time->set_timezone(new \DateTimeZone($this->input_timezone));
            }
        } catch (\Exception $e) {
            throw new Transformation_Failed_Exception($e->get_message(), $e->get_code(), $e);
        }
        return $date_time;
    }
}