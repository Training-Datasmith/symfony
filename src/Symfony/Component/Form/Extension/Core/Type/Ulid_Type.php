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
use Symfony\Component\Form\Extension\Core\Data_Transformer\Ulid_To_String_Transformer;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * @author Pavel Dyakonov <wapinet@mail.ru>
 */
class Ulid_Type extends Abstract_Type
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->add_view_transformer(new Ulid_To_String_Transformer());
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['compound' => false, 'invalid_message' => 'Please enter a valid ULID.']);
    }
}