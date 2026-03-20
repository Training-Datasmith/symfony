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
namespace Symfony\Component\Form;

use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Util\String_Util;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Abstract_Type implements Form_Type_Interface
{
    public function get_parent(): ?string
    {
        return Form_Type::class;
    }
    public function configure_options(Options_Resolver $resolver): void
    {
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
    }
    public function finish_view(Form_View $view, Form_Interface $form, array $options): void
    {
    }
    public function get_block_prefix(): string
    {
        return String_Util::fqcn_to_block_prefix(static::class) ?: '';
    }
}