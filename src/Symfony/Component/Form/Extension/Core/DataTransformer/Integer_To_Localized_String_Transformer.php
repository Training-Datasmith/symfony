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
 * Transforms between an integer and a localized number with grouping
 * (each thousand) and comma separators.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Integer_To_Localized_String_Transformer extends Number_To_Localized_String_Transformer
{
    /**
     * Constructs a transformer.
     *
     * @param bool        $grouping     Whether thousands should be grouped
     * @param int|null    $roundingMode One of the ROUND_ constants in this class
     * @param string|null $locale       locale used for transforming
     */
    public function __construct(?bool $grouping = false, ?int $rounding_mode = \Number_Formatter::ROUND_DOWN, ?string $locale = null)
    {
        parent::__construct(0, $grouping, $rounding_mode, $locale);
    }
    public function reverse_transform(mixed $value): int|float|null
    {
        $decimal_separator = $this->get_number_formatter()->get_symbol(\Number_Formatter::DECIMAL_SEPARATOR_SYMBOL);
        if (\is_string($value) && str_contains($value, $decimal_separator)) {
            throw new Transformation_Failed_Exception(\sprintf('The value "%s" is not a valid integer.', $value));
        }
        $result = parent::reverse_transform($value);
        return null !== $result ? (int) $result : null;
    }
    /**
     * @internal
     */
    protected function cast_parsed_value(int|float $value): int|float
    {
        return $value;
    }
}