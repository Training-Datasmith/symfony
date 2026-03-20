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
 * Transforms between a number type and a localized number with grouping
 * (each thousand) and comma separators.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @implements DataTransformerInterface<int|float, string>
 */
class Number_To_Localized_String_Transformer implements Data_Transformer_Interface
{
    protected bool $grouping;
    protected int $rounding_mode;
    public function __construct(private readonly ?int $scale = null, ?bool $grouping = false, ?int $rounding_mode = \Number_Formatter::ROUND_HALFUP, private readonly ?string $locale = null)
    {
        $this->grouping = $grouping ?? false;
        $this->rounding_mode = $rounding_mode ?? \Number_Formatter::ROUND_HALFUP;
    }
    public function transform(mixed $value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }
        if (!is_numeric($value)) {
            throw new Transformation_Failed_Exception('Expected a numeric.');
        }
        $formatter = $this->get_number_formatter();
        $value = $formatter->format($value);
        if (intl_is_failure($formatter->get_error_code())) {
            throw new Transformation_Failed_Exception($formatter->get_error_message());
        }
        // Convert non-breaking and narrow non-breaking spaces to normal ones
        return str_replace([" ", " "], ' ', $value);
    }
    public function reverse_transform(mixed $value): int|float|null
    {
        if (null !== $value && !\is_string($value)) {
            throw new Transformation_Failed_Exception('Expected a string.');
        }
        if (null === $value || '' === $value) {
            return null;
        }
        if (\in_array($value, ['NaN', 'NAN', 'nan'], true)) {
            throw new Transformation_Failed_Exception('"NaN" is not a valid number.');
        }
        $position = 0;
        $formatter = $this->get_number_formatter();
        $group_sep = $formatter->get_symbol(\Number_Formatter::GROUPING_SEPARATOR_SYMBOL);
        $dec_sep = $formatter->get_symbol(\Number_Formatter::DECIMAL_SEPARATOR_SYMBOL);
        if ('.' !== $dec_sep && (!$this->grouping || '.' !== $group_sep)) {
            $value = str_replace('.', $dec_sep, $value);
        }
        if (',' !== $dec_sep && (!$this->grouping || ',' !== $group_sep)) {
            $value = str_replace(',', $dec_sep, $value);
        }
        // If the value is in exponential notation with a negative exponent, we end up with a float value too
        if (str_contains($value, $dec_sep) || false !== stripos($value, 'e-')) {
            $type = \Number_Formatter::TYPE_DOUBLE;
        } else {
            $type = \PHP_INT_SIZE === 8 ? \Number_Formatter::TYPE_INT64 : \Number_Formatter::TYPE_INT32;
        }
        try {
            $result = @$formatter->parse($value, $type, $position);
        } catch (\Intl_Exception $e) {
            throw new Transformation_Failed_Exception($e->get_message(), $e->get_code(), $e);
        }
        if (intl_is_failure($formatter->get_error_code())) {
            throw new Transformation_Failed_Exception($formatter->get_error_message(), $formatter->get_error_code());
        }
        if ($result >= \PHP_INT_MAX || $result <= -\PHP_INT_MAX) {
            throw new Transformation_Failed_Exception('I don\'t have a clear idea what infinity looks like.');
        }
        $result = $this->cast_parsed_value($result);
        if (false !== $encoding = mb_detect_encoding($value, null, true)) {
            $length = mb_strlen($value, $encoding);
            $remainder = mb_substr($value, $position, $length, $encoding);
        } else {
            $length = \strlen($value);
            $remainder = substr($value, $position, $length);
        }
        // After parsing, position holds the index of the character where the
        // parsing stopped
        if ($position < $length) {
            // Check if there are unrecognized characters at the end of the
            // number (excluding whitespace characters)
            $remainder = trim($remainder, " \t\n\r\x00\v ");
            if ('' !== $remainder) {
                throw new Transformation_Failed_Exception(\sprintf('The number contains unrecognized characters: "%s".', $remainder));
            }
        }
        // NumberFormatter::parse() does not round
        return $this->round($result);
    }
    /**
     * Returns a preconfigured \NumberFormatter instance.
     */
    protected function get_number_formatter(): \Number_Formatter
    {
        $formatter = new \Number_Formatter($this->locale ?? \Locale::get_default(), \Number_Formatter::DECIMAL);
        if (null !== $this->scale) {
            $formatter->set_attribute(\Number_Formatter::FRACTION_DIGITS, $this->scale);
            $formatter->set_attribute(\Number_Formatter::ROUNDING_MODE, $this->rounding_mode);
        }
        $formatter->set_attribute(\Number_Formatter::GROUPING_USED, $this->grouping);
        return $formatter;
    }
    /**
     * @internal
     */
    protected function cast_parsed_value(int|float $value): int|float
    {
        if (\is_int($value) && ($float = (float) $value) < \PHP_INT_MAX && $value === (int) $float) {
            return $float;
        }
        return $value;
    }
    /**
     * Rounds a number according to the configured scale and rounding mode.
     */
    private function round(int|float $number): int|float
    {
        if (\is_int($number)) {
            return $number;
        }
        if (null !== $this->scale) {
            // shift number to maintain the correct scale during rounding
            $rounding_coef = 10 ** $this->scale;
            // string representation to avoid rounding errors, similar to bcmul()
            $number = (string) ($number * $rounding_coef);
            $number = match ($this->rounding_mode) {
                \Number_Formatter::ROUND_CEILING => ceil($number),
                \Number_Formatter::ROUND_FLOOR => floor($number),
                \Number_Formatter::ROUND_UP => $number > 0 ? ceil($number) : floor($number),
                \Number_Formatter::ROUND_DOWN => $number > 0 ? floor($number) : ceil($number),
                \Number_Formatter::ROUND_HALFEVEN => round($number, 0, \PHP_ROUND_HALF_EVEN),
                \Number_Formatter::ROUND_HALFUP => round($number, 0, \PHP_ROUND_HALF_UP),
                \Number_Formatter::ROUND_HALFDOWN => round($number, 0, \PHP_ROUND_HALF_DOWN),
            };
            $number = 1 === $rounding_coef ? (int) $number : $number / $rounding_coef;
        }
        return $number;
    }
}