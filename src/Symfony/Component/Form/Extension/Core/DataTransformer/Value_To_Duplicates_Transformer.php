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
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements DataTransformerInterface<mixed, array>
 */
class Value_To_Duplicates_Transformer implements Data_Transformer_Interface
{
    public function __construct(private readonly array $keys)
    {
    }
    public function transform(mixed $value): array
    {
        $result = [];
        foreach ($this->keys as $key) {
            $result[$key] = $value;
        }
        return $result;
    }
    public function reverse_transform(mixed $array): mixed
    {
        if (!\is_array($array)) {
            throw new Transformation_Failed_Exception('Expected an array.');
        }
        $result = current($array);
        $empty_keys = [];
        foreach ($this->keys as $key) {
            if (isset($array[$key]) && false !== $array[$key] && [] !== $array[$key]) {
                if ($array[$key] !== $result) {
                    throw new Transformation_Failed_Exception('All values in the array should be the same.');
                }
            } else {
                $empty_keys[] = $key;
            }
        }
        if (\count($empty_keys) > 0) {
            if (\count($empty_keys) == \count($this->keys)) {
                // All keys empty
                return null;
            }
            throw new Transformation_Failed_Exception(\sprintf('The keys "%s" should not be empty.', implode('", "', $empty_keys)));
        }
        return $result;
    }
}