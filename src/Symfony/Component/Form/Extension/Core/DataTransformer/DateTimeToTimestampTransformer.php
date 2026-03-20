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
 * Transforms between a timestamp and a DateTime object.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @extends BaseDateTimeTransformer<int|numeric-string>
 */
class Date_Time_To_Timestamp_Transformer extends Base_Date_Time_Transformer
{
    public function transform(mixed $date_time): ?int
    {
        if (null === $date_time) {
            return null;
        }
        if (!$date_time instanceof \DateTimeInterface) {
            throw new Transformation_Failed_Exception('Expected a \DateTimeInterface.');
        }
        return $date_time->get_timestamp();
    }
    public function reverse_transform(mixed $value): ?\DateTime
    {
        if (null === $value) {
            return null;
        }
        if (!is_numeric($value)) {
            throw new Transformation_Failed_Exception('Expected a numeric.');
        }
        try {
            $date_time = new \DateTime();
            $date_time->set_timezone(new \DateTimeZone($this->output_timezone));
            $date_time->set_timestamp($value);
            if ($this->input_timezone !== $this->output_timezone) {
                $date_time->set_timezone(new \DateTimeZone($this->input_timezone));
            }
        } catch (\Exception $e) {
            throw new Transformation_Failed_Exception($e->get_message(), $e->get_code(), $e);
        }
        return $date_time;
    }
}