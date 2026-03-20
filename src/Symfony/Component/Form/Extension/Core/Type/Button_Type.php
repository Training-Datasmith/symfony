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

use Symfony\Component\Form\Button_Type_Interface;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * A form button.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Button_Type extends Base_Type implements Button_Type_Interface
{
    public function get_parent(): ?string
    {
        return null;
    }
    public function get_block_prefix(): string
    {
        return 'button';
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        parent::configure_options($resolver);
        $resolver->set_default('auto_initialize', false);
    }
}