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
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @extends BaseDateTimeTransformer<string>
 */
class Date_Time_To_Rfc3339transformer extends Base_Date_Time_Transformer
{
    public function transform(mixed $date_time): ?string
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
        return preg_replace('/\+00:00$/', 'Z', $date_time->format('c'));
    }
    public function reverse_transform(mixed $rfc3339): ?\DateTime
    {
        if (!\is_string($rfc3339)) {
            throw new Transformation_Failed_Exception('Expected a string.');
        }
        if ('' === $rfc3339) {
            return null;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})T\d{2}:\d{2}(?::\d{2})?(?:\.\d+)?(?:Z|(?:(?:\+|-)\d{2}:\d{2}))$/', $rfc3339, $matches)) {
            throw new Transformation_Failed_Exception(\sprintf('The date "%s" is not a valid date.', $rfc3339));
        }
        try {
            $date_time = new \DateTime($rfc3339);
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