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
use Symfony\Component\Form\Data_Transformer_Interface;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Form\Util\String_Util;
class Textarea_Type extends Abstract_Type implements Data_Transformer_Interface
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->add_view_transformer($this);
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        $view->vars['pattern'] = null;
        unset($view->vars['attr']['pattern']);
    }
    public function get_parent(): ?string
    {
        return Text_Type::class;
    }
    public function get_block_prefix(): string
    {
        return 'textarea';
    }
    public function transform(mixed $value): mixed
    {
        if (null === $value) {
            return '';
        }
        return $value;
    }
    public function reverse_transform(mixed $value): mixed
    {
        if (!\is_string($value)) {
            return $value;
        }
        if ('' === $value) {
            return null;
        }
        return String_Util::normalize_newlines($value);
    }
}