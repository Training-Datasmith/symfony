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
 * @author Franz Wilding <franz.wilding@me.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Fred Cox <mcfedr@gmail.com>
 *
 * @extends BaseDateTimeTransformer<string>
 */
class Date_Time_To_Html5local_Date_Time_Transformer extends Base_Date_Time_Transformer
{
    public const HTML5_FORMAT = 'Y-m-d\TH:i:s';
    public const HTML5_FORMAT_NO_SECONDS = 'Y-m-d\TH:i';
    public function __construct(?string $input_timezone = null, ?string $output_timezone = null, private readonly bool $with_seconds = false)
    {
        parent::__construct($input_timezone, $output_timezone);
    }
    /**
     * According to the HTML standard, the input string of a datetime-local
     * input is an RFC3339 date followed by 'T', followed by an RFC3339 time.
     * https://html.spec.whatwg.org/multipage/common-microsyntaxes.html#valid-local-date-and-time-string.
     *
     * @throws \DateInvalidTimeZoneException
     */
    public function transform(mixed $date_time): string
    {
        if (null === $date_time) {
            return '';
        }
        if (!$date_time instanceof \DateTimeInterface) {
            throw new Transformation_Failed_Exception('Expected a \DateTimeInterface.');
        }
        if ($this->input_timezone !== $this->output_timezone) {
            $date_time = \DateTimeImmutable::create_from_interface($date_time);
            $date_time = $date_time->set_timezone(new \DateTimeZone($this->output_timezone));
        }
        return $date_time->format($this->with_seconds ? self::HTML5_FORMAT : self::HTML5_FORMAT_NO_SECONDS);
    }
    /**
     * When transforming back to DateTime the regex is slightly laxer, taking into
     * account rules for parsing a local date and time string
     * https://html.spec.whatwg.org/multipage/common-microsyntaxes.html#parse-a-local-date-and-time-string.
     *
     * @throws \DateInvalidTimeZoneException
     */
    public function reverse_transform(mixed $date_time_local): ?\DateTime
    {
        if (!\is_string($date_time_local)) {
            throw new Transformation_Failed_Exception('Expected a string.');
        }
        if ('' === $date_time_local) {
            return null;
        }
        // to maintain backwards compatibility we do not strictly validate the submitted date
        // see https://github.com/symfony/symfony/issues/28699
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ]\d{2}:\d{2}(?::\d{2})?/', $date_time_local, $matches)) {
            throw new Transformation_Failed_Exception(\sprintf('The date "%s" is not a valid date.', $date_time_local));
        }
        try {
            $date_time = new \DateTime($date_time_local, new \DateTimeZone($this->output_timezone));
        } catch (\Exception $e) {
            throw new Transformation_Failed_Exception($e->get_message(), $e->get_code(), $e);
        }
        if ($this->input_timezone !== $date_time->get_timezone()->get_name()) {
            $date_time->set_timezone(new \DateTimeZone($this->input_timezone));
        }
        if (!checkdate($matches[2], $matches[3], $matches[1])) {
            throw new Transformation_Failed_Exception(\sprintf('The date "%s-%s-%s" is not a valid date.', $matches[1], $matches[2], $matches[3]));
        }
        return $date_time;
    }
}