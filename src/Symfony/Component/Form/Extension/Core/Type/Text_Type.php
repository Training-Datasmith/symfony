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
use Symfony\Component\Options_Resolver\Options_Resolver;
class Text_Type extends Abstract_Type implements Data_Transformer_Interface
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        // When empty_data is explicitly set to an empty string,
        // a string should always be returned when NULL is submitted
        // This gives more control and thus helps preventing some issues
        // with PHP 7 which allows type hinting strings in functions
        // See https://github.com/symfony/symfony/issues/5906#issuecomment-203189375
        if ('' === $options['empty_data']) {
            $builder->add_view_transformer($this);
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['compound' => false]);
    }
    public function get_block_prefix(): string
    {
        return 'text';
    }
    public function transform(mixed $data): mixed
    {
        // Model data should not be transformed
        return $data;
    }
    public function reverse_transform(mixed $data): mixed
    {
        return $data ?? '';
    }
}