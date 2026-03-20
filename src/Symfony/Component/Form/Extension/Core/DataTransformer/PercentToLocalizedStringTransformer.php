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
 * Transforms between a normalized format (integer or float) and a percentage value.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @implements DataTransformerInterface<int|float, string>
 */
class Percent_To_Localized_String_Transformer implements Data_Transformer_Interface
{
    public const FRACTIONAL = 'fractional';
    public const INTEGER = 'integer';
    protected static array $types = [self::FRACTIONAL, self::INTEGER];
    private readonly string $type;
    private readonly int $scale;
    /**
     * @see self::$types for a list of supported types
     *
     * @param int  $roundingMode A value from \NumberFormatter, such as \NumberFormatter::ROUND_HALFUP
     * @param bool $html5Format  Use an HTML5 specific format, see https://www.w3.org/TR/html51/sec-forms.html#date-time-and-number-formats
     *
     * @throws UnexpectedTypeException if the given value of type is unknown
     */
    public function __construct(?int $scale = null, ?string $type = null, private readonly int $rounding_mode = \Number_Formatter::ROUND_HALFUP, private readonly bool $html5Format = false)
    {
        $type ??= self::FRACTIONAL;
        if (!\in_array($type, self::$types, true)) {
            throw new Unexpected_Type_Exception($type, implode('", "', self::$types));
        }
        $this->type = $type;
        $this->scale = $scale ?? 0;
    }
    public function transform(mixed $value): string
    {
        if (null === $value) {
            return '';
        }
        if (!is_numeric($value)) {
            throw new Transformation_Failed_Exception('Expected a numeric.');
        }
        if (self::FRACTIONAL == $this->type) {
            $value *= 100;
        }
        $formatter = $this->get_number_formatter();
        $value = $formatter->format($value);
        if (intl_is_failure($formatter->get_error_code())) {
            throw new Transformation_Failed_Exception($formatter->get_error_message());
        }
        // replace the UTF-8 non break spaces
        return $value;
    }
    public function reverse_transform(mixed $value): int|float|null
    {
        if (!\is_string($value)) {
            throw new Transformation_Failed_Exception('Expected a string.');
        }
        if ('' === $value) {
            return null;
        }
        $position = 0;
        $formatter = $this->get_number_formatter();
        $group_sep = $formatter->get_symbol(\Number_Formatter::GROUPING_SEPARATOR_SYMBOL);
        $dec_sep = $formatter->get_symbol(\Number_Formatter::DECIMAL_SEPARATOR_SYMBOL);
        $grouping = $formatter->get_attribute(\Number_Formatter::GROUPING_USED);
        if ('.' !== $dec_sep && (!$grouping || '.' !== $group_sep)) {
            $value = str_replace('.', $dec_sep, $value);
        }
        if (',' !== $dec_sep && (!$grouping || ',' !== $group_sep)) {
            $value = str_replace(',', $dec_sep, $value);
        }
        if (str_contains($value, $dec_sep)) {
            $type = \Number_Formatter::TYPE_DOUBLE;
        } else {
            $type = \PHP_INT_SIZE === 8 ? \Number_Formatter::TYPE_INT64 : \Number_Formatter::TYPE_INT32;
        }
        try {
            // replace normal spaces so that the formatter can read them
            $result = @$formatter->parse(str_replace(' ', " ", $value), $type, $position);
        } catch (\Intl_Exception $e) {
            throw new Transformation_Failed_Exception($e->get_message(), 0, $e);
        }
        if (intl_is_failure($formatter->get_error_code())) {
            throw new Transformation_Failed_Exception($formatter->get_error_message(), $formatter->get_error_code());
        }
        if (self::FRACTIONAL == $this->type) {
            $result /= 100;
        }
        if (\function_exists('mb_detect_encoding') && false !== $encoding = mb_detect_encoding($value, null, true)) {
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
        return $this->round($result);
    }
    /**
     * Returns a preconfigured \NumberFormatter instance.
     */
    protected function get_number_formatter(): \Number_Formatter
    {
        // Values used in HTML5 number inputs should be formatted as in "1234.5", ie. 'en' format without grouping,
        // according to https://www.w3.org/TR/html51/sec-forms.html#date-time-and-number-formats
        $formatter = new \Number_Formatter($this->html5Format ? 'en' : \Locale::get_default(), \Number_Formatter::DECIMAL);
        if ($this->html5Format) {
            $formatter->set_attribute(\Number_Formatter::GROUPING_USED, 0);
        }
        $formatter->set_attribute(\Number_Formatter::FRACTION_DIGITS, $this->scale);
        $formatter->set_attribute(\Number_Formatter::ROUNDING_MODE, $this->rounding_mode);
        return $formatter;
    }
    /**
     * Rounds a number according to the configured scale and rounding mode.
     */
    private function round(int|float $number): int|float
    {
        // shift number to maintain the correct scale during rounding
        $rounding_coef = 10 ** $this->scale;
        if (self::FRACTIONAL === $this->type) {
            $rounding_coef *= 100;
        }
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
        return 1 === $rounding_coef ? (int) $number : $number / $rounding_coef;
    }
}