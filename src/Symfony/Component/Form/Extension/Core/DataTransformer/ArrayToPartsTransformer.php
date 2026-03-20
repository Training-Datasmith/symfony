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
 * @implements DataTransformerInterface<array, array>
 */
class Array_To_Parts_Transformer implements Data_Transformer_Interface
{
    public function __construct(private readonly array $part_mapping)
    {
    }
    public function transform(mixed $array): mixed
    {
        if (!\is_array($array ??= [])) {
            throw new Transformation_Failed_Exception('Expected an array.');
        }
        $result = [];
        foreach ($this->part_mapping as $part_key => $original_keys) {
            if (!$array) {
                $result[$part_key] = null;
            } else {
                $result[$part_key] = array_intersect_key($array, array_flip($original_keys));
            }
        }
        return $result;
    }
    public function reverse_transform(mixed $array): mixed
    {
        if (!\is_array($array)) {
            throw new Transformation_Failed_Exception('Expected an array.');
        }
        $result = [];
        $empty_keys = [];
        foreach ($this->part_mapping as $part_key => $original_keys) {
            if (!empty($array[$part_key])) {
                foreach ($original_keys as $original_key) {
                    if (isset($array[$part_key][$original_key])) {
                        $result[$original_key] = $array[$part_key][$original_key];
                    }
                }
            } else {
                $empty_keys[] = $part_key;
            }
        }
        if (\count($empty_keys) > 0) {
            if (\count($empty_keys) === \count($this->part_mapping)) {
                // All parts empty
                return null;
            }
            throw new Transformation_Failed_Exception(\sprintf('The keys "%s" should not be empty.', implode('", "', $empty_keys)));
        }
        return $result;
    }
}