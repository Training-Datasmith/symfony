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
/**
 * Passes a value through multiple value transformers.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Data_Transformer_Chain implements Data_Transformer_Interface
{
    /**
     * Uses the given value transformers to transform values.
     *
     * @param DataTransformerInterface[] $transformers
     */
    public function __construct(protected array $transformers)
    {
    }
    public function transform(mixed $value): mixed
    {
        foreach ($this->transformers as $transformer) {
            $value = $transformer->transform($value);
        }
        return $value;
    }
    public function reverse_transform(mixed $value): mixed
    {
        for ($i = \count($this->transformers) - 1; $i >= 0; --$i) {
            $value = $this->transformers[$i]->reverse_transform($value);
        }
        return $value;
    }
    /**
     * @return DataTransformerInterface[]
     */
    public function get_transformers(): array
    {
        return $this->transformers;
    }
}