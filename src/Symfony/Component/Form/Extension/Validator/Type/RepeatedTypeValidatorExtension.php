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
use Symfony\Component\Form\Extension\Core\Type\Repeated_Type;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Repeated_Type_Validator_Extension extends Abstract_Type_Extension
{
    public function configure_options(Options_Resolver $resolver): void
    {
        // Map errors to the first field
        $error_mapping = static fn(Options $options): array => ['.' => $options['first_name']];
        $resolver->set_defaults(['error_mapping' => $error_mapping]);
    }
    public static function get_extended_types(): iterable
    {
        return [Repeated_Type::class];
    }
}