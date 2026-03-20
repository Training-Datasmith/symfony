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
 * @implements DataTransformerInterface<mixed, string>
 */
class Choice_To_Value_Transformer implements Data_Transformer_Interface
{
    public function __construct(private readonly Choice_List_Interface $choice_list)
    {
    }
    public function transform(mixed $choice): mixed
    {
        return (string) current($this->choice_list->get_values_for_choices([$choice]));
    }
    public function reverse_transform(mixed $value): mixed
    {
        if (null !== $value && !\is_string($value)) {
            throw new Transformation_Failed_Exception('Expected a string or null.');
        }
        $choices = $this->choice_list->get_choices_for_values([(string) $value]);
        if (1 !== \count($choices)) {
            if (null === $value || '' === $value) {
                return null;
            }
            throw new Transformation_Failed_Exception(\sprintf('The choice "%s" does not exist or is not unique.', $value));
        }
        return current($choices);
    }
}