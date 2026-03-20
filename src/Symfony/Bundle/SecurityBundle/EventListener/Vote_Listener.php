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
namespace Symfony\Bundle\Security_Bundle\Event_Listener;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Security\Core\Authorization\Traceable_Access_Decision_Manager;
use Symfony\Component\Security\Core\Event\Vote_Event;
/**
 * Listen to vote events from traceable voters.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 *
 * @internal
 */
class Vote_Listener implements Event_Subscriber_Interface
{
    public function __construct(private readonly Traceable_Access_Decision_Manager $traceable_access_decision_manager)
    {
    }
    public function on_voter_vote(Vote_Event $event): void
    {
        $this->traceable_access_decision_manager->add_voter_vote($event->get_voter(), $event->get_attributes(), $event->get_vote(), $event->get_reasons());
    }
    public static function get_subscribed_events(): array
    {
        return ['debug.security.authorization.vote' => 'onVoterVote'];
    }
}