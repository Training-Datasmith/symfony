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
namespace Symfony\Component\Console\Event_Listener;

use Symfony\Component\Console\Console_Events;
use Symfony\Component\Console\Event\Question_Answered_Event;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Validator\Validator\Validator_Interface;
/**
 * Validates Question answers (user input) using the Validator component.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final readonly class Validate_Question_Input_Listener implements Event_Subscriber_Interface
{
    public function __construct(private Validator_Interface $validator)
    {
    }
    public function on_question_answered(Question_Answered_Event $event): void
    {
        $violations = $this->validator->validate($event->value, $event->constraints);
        foreach ($violations as $violation) {
            $event->add_violation($violation->get_message());
        }
    }
    public static function get_subscribed_events(): array
    {
        return [Console_Events::QUESTION_ANSWERED => 'onQuestionAnswered'];
    }
}