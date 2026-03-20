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
class Birthday_Type extends Abstract_Type
{
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['years' => range(date('Y') - 120, date('Y')), 'invalid_message' => 'Please enter a valid birthdate.']);
        $resolver->set_allowed_types('years', 'array');
    }
    public function get_parent(): ?string
    {
        return Date_Type::class;
    }
    public function get_block_prefix(): string
    {
        return 'birthday';
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        if ('single_text' === $options['widget']) {
            $view->vars['attr']['min'] ??= \sprintf('%d-01-01', min($options['years']));
            $view->vars['attr']['max'] ??= \sprintf('%d-12-31', max($options['years']));
        }
    }
}