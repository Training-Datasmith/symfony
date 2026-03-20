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
namespace Symfony\Component\Form\Extension\Core\Event_Listener;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Form\Form_Error;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class Transformation_Failure_Listener implements Event_Subscriber_Interface
{
    public function __construct(private readonly ?Translator_Interface $translator = null)
    {
    }
    public static function get_subscribed_events(): array
    {
        return [Form_Events::POST_SUBMIT => ['convertTransformationFailureToFormError', -1024]];
    }
    public function convert_transformation_failure_to_form_error(Form_Event $event): void
    {
        $form = $event->get_form();
        if (null === $form->get_transformation_failure() || !$form->is_valid()) {
            return;
        }
        foreach ($form as $child) {
            if (!$child->is_synchronized()) {
                return;
            }
        }
        $client_data_as_string = \is_scalar($form->get_view_data()) ? (string) $form->get_view_data() : get_debug_type($form->get_view_data());
        $message_template = $form->get_config()->get_option('invalid_message', 'The value {{ value }} is not valid.');
        $message_parameters = array_replace(['{{ value }}' => $client_data_as_string], $form->get_config()->get_option('invalid_message_parameters', []));
        if (null !== $this->translator) {
            $message = $this->translator->trans($message_template, $message_parameters);
        } else {
            $message = strtr($message_template, $message_parameters);
        }
        $form->add_error(new Form_Error($message, $message_template, $message_parameters, null, $form->get_transformation_failure()));
    }
}