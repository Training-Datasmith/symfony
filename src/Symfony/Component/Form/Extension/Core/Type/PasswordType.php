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
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Password_Type extends Abstract_Type
{
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        if ($options['always_empty'] || !$form->is_submitted()) {
            $view->vars['value'] = '';
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['always_empty' => true, 'trim' => false, 'invalid_message' => 'The password is invalid.']);
    }
    public function get_parent(): ?string
    {
        return Text_Type::class;
    }
    public function get_block_prefix(): string
    {
        return 'password';
    }
}