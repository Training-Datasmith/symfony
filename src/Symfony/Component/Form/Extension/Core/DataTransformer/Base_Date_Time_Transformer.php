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
use Symfony\Component\Form\Exception\InvalidArgumentException;
/**
 * @template TTransformedValue
 *
 * @implements DataTransformerInterface<\DateTimeInterface, TTransformedValue>
 */
abstract class Base_Date_Time_Transformer implements Data_Transformer_Interface
{
    protected static array $formats = [\Intl_Date_Formatter::NONE, \Intl_Date_Formatter::FULL, \Intl_Date_Formatter::LONG, \Intl_Date_Formatter::MEDIUM, \Intl_Date_Formatter::SHORT];
    protected string $input_timezone;
    protected string $output_timezone;
    /**
     * @param string|null $inputTimezone  The name of the input timezone
     * @param string|null $outputTimezone The name of the output timezone
     *
     * @throws InvalidArgumentException if a timezone is not valid
     */
    public function __construct(?string $input_timezone = null, ?string $output_timezone = null)
    {
        $this->input_timezone = $input_timezone ?: date_default_timezone_get();
        $this->output_timezone = $output_timezone ?: date_default_timezone_get();
        // Check if input and output timezones are valid
        try {
            new \DateTimeZone($this->input_timezone);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(\sprintf('Input timezone is invalid: "%s".', $this->input_timezone), $e->get_code(), $e);
        }
        try {
            new \DateTimeZone($this->output_timezone);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(\sprintf('Output timezone is invalid: "%s".', $this->output_timezone), $e->get_code(), $e);
        }
    }
}