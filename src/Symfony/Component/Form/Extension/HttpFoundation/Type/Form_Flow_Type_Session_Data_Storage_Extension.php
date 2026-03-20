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
namespace Symfony\Component\Form\Extension\Http_Foundation\Type;

use Symfony\Component\Form\Abstract_Type_Extension;
use Symfony\Component\Form\Flow\Data_Storage\Session_Data_Storage;
use Symfony\Component\Form\Flow\Form_Flow_Builder_Interface;
use Symfony\Component\Form\Flow\Type\Form_Flow_Type;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Http_Foundation\Request_Stack;
class Form_Flow_Type_Session_Data_Storage_Extension extends Abstract_Type_Extension
{
    public function __construct(private readonly ?Request_Stack $request_stack = null)
    {
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        if (!$builder instanceof Form_Flow_Builder_Interface) {
            throw new \InvalidArgumentException(\sprintf('The "%s" can only be used with FormFlowType.', self::class));
        }
        if (null === $this->request_stack || null !== $options['data_storage']) {
            return;
        }
        $key = \sprintf('_sf_formflow.%s_%s', strtolower(str_replace('\\', '_', $builder->get_type()->get_inner_type()::class)), $builder->get_name());
        $builder->set_data_storage(new Session_Data_Storage($key, $this->request_stack));
    }
    public static function get_extended_types(): iterable
    {
        return [Form_Flow_Type::class];
    }
}