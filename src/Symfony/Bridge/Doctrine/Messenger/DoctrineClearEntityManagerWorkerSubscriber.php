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
namespace Symfony\Bridge\Doctrine\Messenger;

use Doctrine\Persistence\Manager_Registry;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Messenger\Event\Worker_Message_Failed_Event;
use Symfony\Component\Messenger\Event\Worker_Message_Handled_Event;
/**
 * Clears entity managers between messages being handled to avoid outdated data.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class Doctrine_Clear_Entity_Manager_Worker_Subscriber implements Event_Subscriber_Interface
{
    public function __construct(private readonly Manager_Registry $manager_registry)
    {
    }
    public function on_worker_message_handled(): void
    {
        $this->clear_entity_managers();
    }
    public function on_worker_message_failed(): void
    {
        $this->clear_entity_managers();
    }
    public static function get_subscribed_events(): array
    {
        return [Worker_Message_Handled_Event::class => 'onWorkerMessageHandled', Worker_Message_Failed_Event::class => 'onWorkerMessageFailed'];
    }
    private function clear_entity_managers(): void
    {
        foreach ($this->manager_registry->get_managers() as $manager) {
            $manager->clear();
        }
    }
}