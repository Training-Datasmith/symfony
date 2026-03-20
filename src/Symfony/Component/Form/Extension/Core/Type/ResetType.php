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
use Symfony\Component\Form\Button_Type_Interface;
/**
 * A reset button.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Reset_Type extends Abstract_Type implements Button_Type_Interface
{
    public function get_parent(): ?string
    {
        return Button_Type::class;
    }
    public function get_block_prefix(): string
    {
        return 'reset';
    }
}