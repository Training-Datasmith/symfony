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
namespace Symfony\Bridge\Doctrine\Form\Data_Transformer;

use Doctrine\Common\Collections\Array_Collection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Readable_Collection;
use Symfony\Component\Form\Data_Transformer_Interface;
use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements DataTransformerInterface<Collection|array, array>
 */
class Collection_To_Array_Transformer implements Data_Transformer_Interface
{
    public function transform(mixed $collection): mixed
    {
        if (null === $collection) {
            return [];
        }
        // For cases when the collection getter returns $collection->toArray()
        // in order to prevent modifications of the returned collection
        if (\is_array($collection)) {
            return $collection;
        }
        if (!$collection instanceof Readable_Collection) {
            throw new Transformation_Failed_Exception(\sprintf('Expected a "%s" object.', Readable_Collection::class));
        }
        return $collection->to_array();
    }
    public function reverse_transform(mixed $array): Collection
    {
        if ('' === $array || null === $array) {
            $array = [];
        } else {
            $array = (array) $array;
        }
        return new Array_Collection($array);
    }
}