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
namespace Symfony\Component\Form;

/**
 * Reverses a transformer.
 *
 * When the transform() method is called, the reversed transformer's
 * reverseTransform() method is called and vice versa.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Reversed_Transformer implements Data_Transformer_Interface
{
    public function __construct(protected Data_Transformer_Interface $reversed_transformer)
    {
    }
    public function transform(mixed $value): mixed
    {
        return $this->reversed_transformer->reverse_transform($value);
    }
    public function reverse_transform(mixed $value): mixed
    {
        return $this->reversed_transformer->transform($value);
    }
}