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
use Symfony\Component\Form\Submit_Button_Type_Interface;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * A submit button.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Submit_Type extends Abstract_Type implements Submit_Button_Type_Interface
{
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $view->vars['clicked'] = $form->is_clicked();
        if (!$options['validate']) {
            $view->vars['attr']['formnovalidate'] = true;
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_default('validate', true);
        $resolver->set_allowed_types('validate', 'bool');
    }
    public function get_parent(): ?string
    {
        return Button_Type::class;
    }
    public function get_block_prefix(): string
    {
        return 'submit';
    }
}