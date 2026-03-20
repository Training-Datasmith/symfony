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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Symfony\Component\Scheduler\Event_Listener\Dispatch_Scheduler_Event_Listener;
use Symfony\Component\Scheduler\Messenger\Scheduler_Transport_Factory;
use Symfony\Component\Scheduler\Messenger\Serializer\Normalizer\Scheduler_Trigger_Normalizer;
use Symfony\Component\Scheduler\Messenger\Service_Call_Message_Handler;
return static function (Container_Configurator $container): void {
    $container->services()->set('scheduler.messenger.service_call_message_handler', Service_Call_Message_Handler::class)->args([tagged_locator('scheduler.task')])->tag('messenger.message_handler')->set('scheduler.messenger_transport_factory', Scheduler_Transport_Factory::class)->args([tagged_locator('scheduler.schedule_provider', 'name'), service('clock')])->tag('messenger.transport_factory')->set('scheduler.event_listener', Dispatch_Scheduler_Event_Listener::class)->args([tagged_locator('scheduler.schedule_provider', 'name'), service('event_dispatcher')])->tag('kernel.event_subscriber')->set('serializer.normalizer.scheduler_trigger', Scheduler_Trigger_Normalizer::class)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -880]);
};