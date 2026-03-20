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

use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
use Symfony\Component\Form\Exception\Unexpected_Type_Exception;
/**
 * Transforms between a normalized time and a localized time string.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @extends BaseDateTimeTransformer<string>
 */
class Date_Time_To_Localized_String_Transformer extends Base_Date_Time_Transformer
{
    /**
     * Unicode whitespace characters used by ICU in formatted date strings.
     *
     * @see https://unicode-org.atlassian.net/browse/CLDR-14032
     */
    private const NO_BREAK_SPACE = " ";
    private const NARROW_NO_BREAK_SPACE = " ";
    // Used by ICU 72+ before AM/PM
    private const THIN_SPACE = " ";
    private readonly int $date_format;
    private readonly int $time_format;
    /**
     * @see BaseDateTimeTransformer::formats for available format options
     *
     * @param string|null       $inputTimezone  The name of the input timezone
     * @param string|null       $outputTimezone The name of the output timezone
     * @param int|null          $dateFormat     The date format
     * @param int|null          $timeFormat     The time format
     * @param int|\IntlCalendar $calendar       One of the \IntlDateFormatter calendar constants or an \IntlCalendar instance
     * @param string|null       $pattern        A pattern to pass to \IntlDateFormatter
     *
     * @throws UnexpectedTypeException If a format is not supported or if a timezone is not a string
     */
    public function __construct(?string $input_timezone = null, ?string $output_timezone = null, ?int $date_format = null, ?int $time_format = null, private readonly int|\Intl_Calendar $calendar = \Intl_Date_Formatter::GREGORIAN, private readonly ?string $pattern = null)
    {
        parent::__construct($input_timezone, $output_timezone);
        $date_format ??= \Intl_Date_Formatter::MEDIUM;
        $time_format ??= \Intl_Date_Formatter::SHORT;
        if (!\in_array($date_format, self::$formats, true)) {
            throw new Unexpected_Type_Exception($date_format, implode('", "', self::$formats));
        }
        if (!\in_array($time_format, self::$formats, true)) {
            throw new Unexpected_Type_Exception($time_format, implode('", "', self::$formats));
        }
        if (\is_int($calendar) && !\in_array($calendar, [\Intl_Date_Formatter::GREGORIAN, \Intl_Date_Formatter::TRADITIONAL], true)) {
            throw new InvalidArgumentException('The "calendar" option should be either an \IntlDateFormatter constant or an \IntlCalendar instance.');
        }
        $this->date_format = $date_format;
        $this->time_format = $time_format;
    }
    public function transform(mixed $date_time): string
    {
        if (null === $date_time) {
            return '';
        }
        if (!$date_time instanceof \DateTimeInterface) {
            throw new Transformation_Failed_Exception('Expected a \DateTimeInterface.');
        }
        $value = $this->get_intl_date_formatter()->format($date_time->get_timestamp());
        if (0 != intl_get_error_code()) {
            throw new Transformation_Failed_Exception(intl_get_error_message());
        }
        return self::normalize_whitespace($value);
    }
    public function reverse_transform(mixed $value): ?\DateTime
    {
        if (!\is_string($value)) {
            throw new Transformation_Failed_Exception('Expected a string.');
        }
        if ('' === $value) {
            return null;
        }
        // date-only patterns require parsing to be done in UTC, as midnight might not exist in the local timezone due
        // to DST changes
        $date_only = $this->is_pattern_date_only();
        $date_formatter = $this->get_intl_date_formatter($date_only);
        $timestamp = $this->parse($date_formatter, $value);
        if (0 != intl_get_error_code()) {
            throw new Transformation_Failed_Exception(intl_get_error_message(), intl_get_error_code());
        }
        if ($timestamp > 253402214400) {
            // This timestamp represents UTC midnight of 9999-12-31 to prevent 5+ digit years
            throw new Transformation_Failed_Exception('Years beyond 9999 are not supported.');
        }
        if (false === $timestamp) {
            // the value couldn't be parsed but the Intl extension didn't report an error code, this
            // could be the case when the Intl polyfill is used which always returns 0 as the error code
            throw new Transformation_Failed_Exception(\sprintf('"%s" could not be parsed as a date.', $value));
        }
        try {
            if ($date_only) {
                // we only care about year-month-date, which has been delivered as a timestamp pointing to UTC midnight
                $date_time = new \DateTime(gmdate('Y-m-d', $timestamp), new \DateTimeZone($this->output_timezone));
            } else {
                // read timestamp into DateTime object - the formatter delivers a timestamp
                $date_time = new \DateTime(\sprintf('@%s', $timestamp));
            }
            // set timezone separately, as it would be ignored if set via the constructor,
            // see https://php.net/datetime.construct
            $date_time->set_timezone(new \DateTimeZone($this->output_timezone));
        } catch (\Exception $e) {
            throw new Transformation_Failed_Exception($e->get_message(), $e->get_code(), $e);
        }
        if ($this->output_timezone !== $this->input_timezone) {
            $date_time->set_timezone(new \DateTimeZone($this->input_timezone));
        }
        return $date_time;
    }
    /**
     * Returns a preconfigured IntlDateFormatter instance.
     *
     * @param bool $ignoreTimezone Use UTC regardless of the configured timezone
     */
    protected function get_intl_date_formatter(bool $ignore_timezone = false): \Intl_Date_Formatter
    {
        $date_format = $this->date_format;
        $time_format = $this->time_format;
        $timezone = new \DateTimeZone($ignore_timezone ? 'UTC' : $this->output_timezone);
        $calendar = $this->calendar;
        $pattern = $this->pattern;
        $intl_date_formatter = new \Intl_Date_Formatter(\Locale::get_default(), $date_format, $time_format, $timezone, $calendar, $pattern ?? '');
        $intl_date_formatter->set_lenient(false);
        return $intl_date_formatter;
    }
    /**
     * Checks if the pattern contains only a date.
     */
    protected function is_pattern_date_only(): bool
    {
        if (null === $this->pattern) {
            return false;
        }
        // strip escaped text
        $pattern = preg_replace("#'(.*?)'#", '', $this->pattern);
        // check for the absence of time-related placeholders
        return 0 === preg_match('#[ahHkKmsSAzZOvVxX]#', (string) $pattern);
    }
    /**
     * Normalizes various Unicode whitespace characters to regular ASCII spaces.
     *
     * ICU 72+ uses special Unicode whitespace characters (such as narrow no-break space U+202F)
     * in formatted date strings. This method ensures consistent handling regardless of ICU version
     * by normalizing these characters to regular ASCII spaces (U+0020).
     */
    private static function normalize_whitespace(string $string): string
    {
        return str_replace([self::NO_BREAK_SPACE, self::NARROW_NO_BREAK_SPACE, self::THIN_SPACE], ' ', $string);
    }
    /**
     * Parses a localized date string, handling ICU version differences in whitespace.
     *
     * ICU 72+ uses special Unicode whitespace characters (such as narrow no-break space U+202F)
     * that users typically don't type. This method first tries parsing the input as-is, then
     * tries with whitespace normalization to ensure compatibility across ICU versions.
     *
     * @throws TransformationFailedException When the input cannot be parsed
     */
    private function parse(\Intl_Date_Formatter $date_formatter, string $value): int|float|false
    {
        try {
            $timestamp = @$date_formatter->parse($value);
        } catch (\Intl_Exception $e) {
            throw new Transformation_Failed_Exception($e->get_message(), $e->get_code(), $e);
        }
        // If parsing failed and the value contains regular spaces, try with ICU 72+ whitespace
        if ((false === $timestamp || 0 !== intl_get_error_code()) && str_contains($value, ' ')) {
            $icu_value = str_replace(' ', self::NARROW_NO_BREAK_SPACE, $value);
            try {
                $timestamp = @$date_formatter->parse($icu_value);
            } catch (\Intl_Exception) {
                // Ignore, we'll use the original error below
            }
        }
        return $timestamp;
    }
}