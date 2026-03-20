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
 * Transforms between a normalized format and a localized money string.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class Money_To_Localized_String_Transformer extends Number_To_Localized_String_Transformer
{
    private readonly int $divisor;
    public function __construct(?int $scale = 2, ?bool $grouping = true, ?int $rounding_mode = \Number_Formatter::ROUND_HALFUP, ?int $divisor = 1, ?string $locale = null, private readonly string $input = 'float')
    {
        parent::__construct($scale ?? 2, $grouping ?? true, $rounding_mode, $locale);
        $this->divisor = $divisor ?? 1;
    }
    public function transform(mixed $value): string
    {
        if (null !== $value && '' !== $value && 1 !== $this->divisor) {
            if (!is_numeric($value)) {
                throw new Transformation_Failed_Exception('Expected a numeric.');
            }
            $value /= $this->divisor;
        }
        return parent::transform($value);
    }
    public function reverse_transform(mixed $value): int|float|null
    {
        $value = parent::reverse_transform($value);
        if (null !== $value) {
            $value = (string) ($value * $this->divisor);
            if ('integer' !== $this->input) {
                return (float) $value;
            }
            if ($value > \PHP_INT_MAX || $value < \PHP_INT_MIN) {
                throw new Transformation_Failed_Exception(\sprintf('Cannot cast "%s" to an integer. Try setting the input to "float" instead.', $value));
            }
            $value = (int) $value;
        }
        return $value;
    }
}