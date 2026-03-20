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
 * @implements DataTransformerInterface<numeric-string, float>
 */
class String_To_Float_Transformer implements Data_Transformer_Interface
{
    public function __construct(private readonly ?int $scale = null)
    {
    }
    public function transform(mixed $value): ?float
    {
        if (null === $value) {
            return null;
        }
        if (!\is_string($value) || !is_numeric($value)) {
            throw new Transformation_Failed_Exception('Expected a numeric string.');
        }
        return (float) $value;
    }
    public function reverse_transform(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!\is_int($value) && !\is_float($value)) {
            throw new Transformation_Failed_Exception('Expected a numeric.');
        }
        if ($this->scale > 0) {
            return number_format((float) $value, $this->scale, '.', '');
        }
        return (string) $value;
    }
}