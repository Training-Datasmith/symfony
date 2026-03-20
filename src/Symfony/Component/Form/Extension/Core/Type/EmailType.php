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
class Email_Type extends Abstract_Type
{
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['invalid_message' => 'Please enter a valid email address.']);
    }
    public function get_parent(): ?string
    {
        return Text_Type::class;
    }
    public function get_block_prefix(): string
    {
        return 'email';
    }
}