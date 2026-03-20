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

use Symfony\Component\Form\Choice_List\Choice_List_Interface;
use Symfony\Component\Form\Data_Transformer_Interface;
use Symfony\Component\Form\Exception\Transformation_Failed_Exception;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements DataTransformerInterface<array, array>
 */
class Choices_To_Values_Transformer implements Data_Transformer_Interface
{
    public function __construct(private readonly Choice_List_Interface $choice_list)
    {
    }
    public function transform(mixed $array): array
    {
        if (null === $array) {
            return [];
        }
        if (!\is_array($array)) {
            throw new Transformation_Failed_Exception('Expected an array.');
        }
        return $this->choice_list->get_values_for_choices($array);
    }
    public function reverse_transform(mixed $array): array
    {
        if (null === $array) {
            return [];
        }
        if (!\is_array($array)) {
            throw new Transformation_Failed_Exception('Expected an array.');
        }
        $choices = $this->choice_list->get_choices_for_values($array);
        if (\count($choices) !== \count($array)) {
            throw new Transformation_Failed_Exception('Could not find all matching choices for the given values.');
        }
        return $choices;
    }
}