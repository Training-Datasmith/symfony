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
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Form\Form_Error;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Contracts\Translation\Translator_Interface;
class Color_Type extends Abstract_Type
{
    /**
     * @see https://www.w3.org/TR/html52/sec-forms.html#color-state-typecolor
     */
    private const HTML5_PATTERN = '/^#[0-9a-f]{6}$/i';
    public function __construct(private readonly ?Translator_Interface $translator = null)
    {
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        if (!$options['html5']) {
            return;
        }
        $translator = $this->translator;
        $builder->add_event_listener(Form_Events::PRE_SUBMIT, static function (Form_Event $event) use ($translator): void {
            $value = $event->get_data();
            if (null === $value || '' === $value) {
                return;
            }
            if (\is_string($value) && preg_match(self::HTML5_PATTERN, $value)) {
                return;
            }
            $message_template = 'This value is not a valid HTML5 color.';
            $message_parameters = ['{{ value }}' => \is_scalar($value) ? (string) $value : \gettype($value)];
            $message = $translator?->trans($message_template, $message_parameters, 'validators') ?? $message_template;
            $event->get_form()->add_error(new Form_Error($message, $message_template, $message_parameters));
        });
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['html5' => false, 'invalid_message' => 'Please select a valid color.']);
        $resolver->set_allowed_types('html5', 'bool');
    }
    public function get_parent(): ?string
    {
        return Text_Type::class;
    }
    public function get_block_prefix(): string
    {
        return 'color';
    }
}