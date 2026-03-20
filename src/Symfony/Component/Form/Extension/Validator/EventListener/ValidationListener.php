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
namespace Symfony\Component\Form\Extension\Validator\Event_Listener;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Form\Extension\Validator\Constraints\Form;
use Symfony\Component\Form\Extension\Validator\Violation_Mapper\Violation_Mapper_Interface;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
use Symfony\Component\Validator\Validator\Validator_Interface;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Validation_Listener implements Event_Subscriber_Interface
{
    public static function get_subscribed_events(): array
    {
        return [Form_Events::POST_SUBMIT => 'validateForm'];
    }
    public function __construct(private readonly Validator_Interface $validator, private readonly Violation_Mapper_Interface $violation_mapper)
    {
    }
    public function validate_form(Form_Event $event): void
    {
        $form = $event->get_form();
        if ($form->is_root()) {
            // Form groups are validated internally (FormValidator). Here we don't set groups as they are retrieved into the validator.
            foreach ($this->validator->validate($form) as $violation) {
                // Allow the "invalid" constraint to be put onto
                // non-synchronized forms
                $allow_non_synchronized = $violation->get_constraint() instanceof Form && Form::NOT_SYNCHRONIZED_ERROR === $violation->get_code();
                $this->violation_mapper->map_violation($violation, $form, $allow_non_synchronized);
            }
        }
    }
}