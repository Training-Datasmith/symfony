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
use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
/**
 * Transforms between a Boolean and a string.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @implements DataTransformerInterface<bool, string>
 */
class Boolean_To_String_Transformer implements Data_Transformer_Interface
{
    /**
     * @param string $trueValue The value emitted upon transform if the input is true
     */
    public function __construct(private readonly string $true_value, private readonly array $false_values = [null])
    {
        if (\in_array($this->true_value, $this->false_values, true)) {
            throw new InvalidArgumentException('The specified "true" value is contained in the false-values.');
        }
    }
    public function transform(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!\is_bool($value)) {
            throw new Transformation_Failed_Exception('Expected a Boolean.');
        }
        return $value ? $this->true_value : null;
    }
    public function reverse_transform(mixed $value): bool
    {
        if (\in_array($value, $this->false_values, true)) {
            return false;
        }
        if (!\is_string($value)) {
            throw new Transformation_Failed_Exception('Expected a string.');
        }
        return true;
    }
}