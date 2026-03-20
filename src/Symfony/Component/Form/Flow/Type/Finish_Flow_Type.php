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
namespace Symfony\Component\Form\Flow\Type;

use Symfony\Component\Form\Flow\Abstract_Button_Flow_Type;
use Symfony\Component\Form\Flow\Button_Flow_Interface;
use Symfony\Component\Form\Flow\Form_Flow_Cursor;
use Symfony\Component\Form\Flow\Form_Flow_Interface;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Finish_Flow_Type extends Abstract_Button_Flow_Type
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->set_attribute('action', 'finish');
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['handler' => static fn(mixed $data, Button_Flow_Interface $button, Form_Flow_Interface $flow) => $flow->reset(), 'include_if' => static fn(Form_Flow_Cursor $cursor): bool => $cursor->is_last_step()]);
    }
}