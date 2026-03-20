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
use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Extension\Http_Foundation\Http_Foundation_Request_Handler;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Request_Handler_Interface;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form_Type_Http_Foundation_Extension extends Abstract_Type_Extension
{
    public function __construct(private readonly ?Request_Handler_Interface $request_handler = new Http_Foundation_Request_Handler())
    {
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->set_request_handler($this->request_handler);
    }
    public static function get_extended_types(): iterable
    {
        return [Form_Type::class];
    }
}