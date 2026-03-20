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
namespace Symfony\Component\Form\Extension\Validator\Type;

use Symfony\Component\Form\Abstract_Type_Extension;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Component\Validator\Constraints\Group_Sequence;
/**
 * Encapsulates common logic of {@link FormTypeValidatorExtension} and
 * {@link SubmitTypeValidatorExtension}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Base_Validator_Extension extends Abstract_Type_Extension
{
    public function configure_options(Options_Resolver $resolver): void
    {
        // Make sure that validation groups end up as null, closure or array
        $validation_groups_normalizer = static function (Options $options, $groups): array|null|callable|\Symfony\Component\Validator\Constraints\Group_Sequence {
            if (false === $groups) {
                return [];
            }
            if (!$groups) {
                return null;
            }
            if (\is_callable($groups)) {
                return $groups;
            }
            if ($groups instanceof Group_Sequence) {
                return $groups;
            }
            return (array) $groups;
        };
        $resolver->set_defaults(['validation_groups' => null]);
        $resolver->set_normalizer('validation_groups', $validation_groups_normalizer);
    }
}