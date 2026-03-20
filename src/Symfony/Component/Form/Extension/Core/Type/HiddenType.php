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
namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\Abstract_Type;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Hidden_Type extends Abstract_Type
{
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults([
            // hidden fields cannot have a required attribute
            'required' => false,
            // Pass errors to the parent
            'error_bubbling' => true,
            'compound' => false,
            'invalid_message' => 'The hidden field is invalid.',
        ]);
    }
    public function get_block_prefix(): string
    {
        return 'hidden';
    }
}