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
 * Transforms between a date string and a DateInterval object.
 *
 * @author Steffen Roßkamp <steffen.rosskamp@gimmickmedia.de>
 *
 * @implements DataTransformerInterface<\DateInterval, string>
 */
class Date_Interval_To_String_Transformer implements Data_Transformer_Interface
{
    /**
     * Transforms a \DateInterval instance to a string.
     *
     * @see \DateInterval::format() for supported formats
     *
     * @param string $format The date format
     */
    public function __construct(private readonly string $format = 'P%yY%mM%dDT%hH%iM%sS')
    {
    }
    public function transform(mixed $value): string
    {
        if (null === $value) {
            return '';
        }
        if (!$value instanceof \DateInterval) {
            throw new Unexpected_Type_Exception($value, \DateInterval::class);
        }
        return $value->format($this->format);
    }
    public function reverse_transform(mixed $value): ?\DateInterval
    {
        if (null === $value) {
            return null;
        }
        if (!\is_string($value)) {
            throw new Unexpected_Type_Exception($value, 'string');
        }
        if ('' === $value) {
            return null;
        }
        if (!$this->is_iso8601($value)) {
            throw new Transformation_Failed_Exception('Non ISO 8601 date strings are not supported yet.');
        }
        $value_pattern = '/^' . preg_replace('/%([yYmMdDhHiIsSwW])(\w)/', '(?P<$1>\d+)$2', $this->format) . '$/';
        if (!preg_match($value_pattern, $value)) {
            throw new Transformation_Failed_Exception(\sprintf('Value "%s" contains intervals not accepted by format "%s".', $value, $this->format));
        }
        try {
            $date_interval = new \DateInterval($value);
        } catch (\Exception $e) {
            throw new Transformation_Failed_Exception($e->get_message(), $e->get_code(), $e);
        }
        return $date_interval;
    }
    private function is_iso8601(string $string): bool
    {
        return preg_match('/^P(?=\w*(?:\d|%\w))(?:\d+Y|%[yY]Y)?(?:\d+M|%[mM]M)?(?:(?:\d+D|%[dD]D)|(?:\d+W|%[wW]W))?(?:T(?:\d+H|[hH]H)?(?:\d+M|[iI]M)?(?:\d+S|[sS]S)?)?$/', $string);
    }
}