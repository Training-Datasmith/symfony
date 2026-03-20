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
namespace Symfony\Component\Form\Extension\Html_Sanitizer\Type;

use Psr\Container\Container_Interface;
use Symfony\Component\Form\Abstract_Type_Extension;
use Symfony\Component\Form\Extension\Core\Type\Text_Type;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class Text_Type_Html_Sanitizer_Extension extends Abstract_Type_Extension
{
    public function __construct(private readonly Container_Interface $sanitizers, private readonly string $default_sanitizer = 'default')
    {
    }
    public static function get_extended_types(): iterable
    {
        return [Text_Type::class];
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['sanitize_html' => false, 'sanitizer' => null])->set_allowed_types('sanitize_html', 'bool')->set_allowed_types('sanitizer', ['string', 'null']);
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        if (!$options['sanitize_html']) {
            return;
        }
        $sanitizers = $this->sanitizers;
        $sanitizer = $options['sanitizer'] ?? $this->default_sanitizer;
        $builder->add_event_listener(Form_Events::PRE_SUBMIT, static function (Form_Event $event) use ($sanitizers, $sanitizer): void {
            if (\is_scalar($data = $event->get_data()) && '' !== trim($data)) {
                $event->set_data($sanitizers->get($sanitizer)->sanitize($data));
            }
        }, 10000);
    }
}