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
use Symfony\Component\Form\Extension\Core\Event_Listener\Fix_Url_Protocol_Listener;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
use Symfony\Component\Options_Resolver\Options_Resolver;
class Url_Type extends Abstract_Type
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        if (null !== $options['default_protocol']) {
            $builder->add_event_subscriber(new Fix_Url_Protocol_Listener($options['default_protocol']));
        }
    }
    public function build_view(Form_View $view, Form_Interface $form, array $options): void
    {
        if ($options['default_protocol']) {
            $view->vars['attr']['inputmode'] = 'url';
            $view->vars['type'] = 'text';
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['default_protocol' => null, 'invalid_message' => 'Please enter a valid URL.']);
        $resolver->set_allowed_types('default_protocol', ['null', 'string']);
    }
    public function get_parent(): ?string
    {
        return Text_Type::class;
    }
    public function get_block_prefix(): string
    {
        return 'url';
    }
}