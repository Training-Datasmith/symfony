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

use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Dependency_Injection\Service_Locator;
use Symfony\Component\Messenger\Bridge\Amazon_Sqs\Transport\Amazon_Sqs_Transport_Factory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Amqp_Transport_Factory;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\Beanstalkd_Transport_Factory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Redis_Transport_Factory;
use Symfony\Component\Messenger\Event_Listener\Add_Error_Details_Stamp_Listener;
use Symfony\Component\Messenger\Event_Listener\Dispatch_Pcntl_Signal_Listener;
use Symfony\Component\Messenger\Event_Listener\Reset_Memory_Usage_Listener;
use Symfony\Component\Messenger\Event_Listener\Reset_Services_Listener;
use Symfony\Component\Messenger\Event_Listener\Send_Failed_Message_For_Retry_Listener;
use Symfony\Component\Messenger\Event_Listener\Send_Failed_Message_To_Failure_Transport_Listener;
use Symfony\Component\Messenger\Event_Listener\Stop_Worker_On_Custom_Stop_Exception_Listener;
use Symfony\Component\Messenger\Event_Listener\Stop_Worker_On_Restart_Signal_Listener;
use Symfony\Component\Messenger\Handler\Redispatch_Message_Handler;
use Symfony\Component\Messenger\Middleware\Add_Bus_Name_Stamp_Middleware;
use Symfony\Component\Messenger\Middleware\Add_Default_Stamps_Middleware;
use Symfony\Component\Messenger\Middleware\Decode_Failed_Message_Middleware;
use Symfony\Component\Messenger\Middleware\Deduplicate_Middleware;
use Symfony\Component\Messenger\Middleware\Dispatch_After_Current_Bus_Middleware;
use Symfony\Component\Messenger\Middleware\Failed_Message_Processing_Middleware;
use Symfony\Component\Messenger\Middleware\Handle_Message_Middleware;
use Symfony\Component\Messenger\Middleware\Reject_Redelivered_Message_Middleware;
use Symfony\Component\Messenger\Middleware\Router_Context_Middleware;
use Symfony\Component\Messenger\Middleware\Send_Message_Middleware;
use Symfony\Component\Messenger\Middleware\Traceable_Middleware;
use Symfony\Component\Messenger\Middleware\Validation_Middleware;
use Symfony\Component\Messenger\Retry\Multiplier_Retry_Strategy;
use Symfony\Component\Messenger\Routable_Message_Bus;
use Symfony\Component\Messenger\Transport\In_Memory\In_Memory_Transport_Factory;
use Symfony\Component\Messenger\Transport\Sender\Senders_Locator;
use Symfony\Component\Messenger\Transport\Serialization\Normalizer\Flatten_Exception_Normalizer;
use Symfony\Component\Messenger\Transport\Serialization\Php_Serializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer_Interface;
use Symfony\Component\Messenger\Transport\Serialization\Signing_Serializer;
use Symfony\Component\Messenger\Transport\Sync\Sync_Transport_Factory;
use Symfony\Component\Messenger\Transport\Transport_Factory;
use Symfony\Component\String\Lazy_String;
return static function (Container_Configurator $container): void {
    $container->services()->set('messenger.senders_locator', Senders_Locator::class)->args([abstract_arg('per message senders map'), abstract_arg('senders service locator')])->set('messenger.middleware.send_message', Send_Message_Middleware::class)->abstract()->args([service('messenger.senders_locator'), service('event_dispatcher')])->call('setLogger', [service('logger')->ignore_on_invalid()])->tag('monolog.logger', ['channel' => 'messenger'])->set('messenger.transport.symfony_serializer', Serializer::class)->args([service('serializer'), abstract_arg('format'), abstract_arg('context'), abstract_arg('message type to serialized type map')])->set('serializer.normalizer.flatten_exception', Flatten_Exception_Normalizer::class)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -880])->set('.messenger.transport.native_php_serializer', Php_Serializer::class)->set('messenger.transport.native_php_serializer', Php_Serializer::class)->factory('current')->args([[service('.messenger.transport.native_php_serializer')]])->alias('messenger.default_serializer', 'messenger.transport.native_php_serializer')->alias(Serializer_Interface::class, 'messenger.default_serializer')->set('messenger.signing_serializer', Signing_Serializer::class)->abstract()->args([service('.inner'), inline_service('string')->factory(class_exists(Lazy_String::class) ? [Lazy_String::class, 'fromCallable'] : 'current')->args([class_exists(Lazy_String::class, false) ? service_closure('.messenger.signing_serializer.signing_key') : [new Parameter('kernel.secret')]]), abstract_arg('message types to serializers')])->set('.messenger.signing_serializer.signing_key', 'string')->factory('current')->args([[new Parameter('kernel.secret')]])->set('messenger.middleware.handle_message', Handle_Message_Middleware::class)->abstract()->args([abstract_arg('bus handler resolver'), false, service('clock')->null_on_invalid()])->tag('monolog.logger', ['channel' => 'messenger'])->call('setLogger', [service('logger')->ignore_on_invalid()])->set('messenger.middleware.deduplicate_middleware', Deduplicate_Middleware::class)->args([service('lock.factory')])->set('messenger.middleware.add_default_stamps_middleware', Add_Default_Stamps_Middleware::class)->set('messenger.middleware.add_bus_name_stamp_middleware', Add_Bus_Name_Stamp_Middleware::class)->abstract()->set('messenger.middleware.dispatch_after_current_bus', Dispatch_After_Current_Bus_Middleware::class)->set('messenger.middleware.validation', Validation_Middleware::class)->args([service('validator')])->set('messenger.middleware.reject_redelivered_message_middleware', Reject_Redelivered_Message_Middleware::class)->set('messenger.middleware.failed_message_processing_middleware', Failed_Message_Processing_Middleware::class)->set('messenger.transport.serializer_locator', Service_Locator::class)->args([[]])->tag('container.service_locator')->set('messenger.middleware.decode_failed_message_middleware', Decode_Failed_Message_Middleware::class)->args([service('messenger.transport.serializer_locator')])->set('messenger.middleware.traceable', Traceable_Middleware::class)->abstract()->args([service('debug.stopwatch')])->set('messenger.middleware.router_context', Router_Context_Middleware::class)->args([service('router')])->set('messenger.receiver_locator', Service_Locator::class)->args([[]])->tag('container.service_locator')->set('messenger.transport_factory', Transport_Factory::class)->args([tagged_iterator('messenger.transport_factory')])->set('messenger.transport.amqp.factory', Amqp_Transport_Factory::class)->set('messenger.transport.redis.factory', Redis_Transport_Factory::class)->set('messenger.transport.sync.factory', Sync_Transport_Factory::class)->args([service('messenger.routable_message_bus')])->tag('messenger.transport_factory')->set('messenger.transport.in_memory.factory', In_Memory_Transport_Factory::class)->args([service('clock')->null_on_invalid()])->tag('messenger.transport_factory')->tag('kernel.reset', ['method' => 'reset'])->set('messenger.transport.sqs.factory', Amazon_Sqs_Transport_Factory::class)->args([service('logger')->ignore_on_invalid()])->tag('monolog.logger', ['channel' => 'messenger'])->set('messenger.transport.beanstalkd.factory', Beanstalkd_Transport_Factory::class)->set('messenger.retry_strategy_locator', Service_Locator::class)->args([[]])->tag('container.service_locator')->set('messenger.retry.abstract_multiplier_retry_strategy', Multiplier_Retry_Strategy::class)->abstract()->args([abstract_arg('max retries'), abstract_arg('delay ms'), abstract_arg('multiplier'), abstract_arg('max delay ms'), abstract_arg('jitter')])->set('messenger.rate_limiter_locator', Service_Locator::class)->args([[]])->tag('container.service_locator')->set('messenger.retry.send_failed_message_for_retry_listener', Send_Failed_Message_For_Retry_Listener::class)->args([abstract_arg('senders service locator'), service('messenger.retry_strategy_locator'), service('logger')->ignore_on_invalid(), service('event_dispatcher')])->tag('kernel.event_subscriber')->tag('monolog.logger', ['channel' => 'messenger'])->set('messenger.failure.add_error_details_stamp_listener', Add_Error_Details_Stamp_Listener::class)->tag('kernel.event_subscriber')->set('messenger.failure.send_failed_message_to_failure_transport_listener', Send_Failed_Message_To_Failure_Transport_Listener::class)->args([abstract_arg('failure transports'), service('logger')->ignore_on_invalid(), abstract_arg('failure transports by name')])->tag('kernel.event_subscriber')->tag('monolog.logger', ['channel' => 'messenger'])->set('messenger.listener.dispatch_pcntl_signal_listener', Dispatch_Pcntl_Signal_Listener::class)->tag('kernel.event_subscriber')->set('messenger.listener.stop_worker_on_restart_signal_listener', Stop_Worker_On_Restart_Signal_Listener::class)->args([service('cache.messenger.restart_workers_signal'), service('logger')->ignore_on_invalid()])->tag('kernel.event_subscriber')->tag('monolog.logger', ['channel' => 'messenger'])->set('messenger.listener.stop_worker_on_stop_exception_listener', Stop_Worker_On_Custom_Stop_Exception_Listener::class)->tag('kernel.event_subscriber')->set('messenger.listener.reset_services', Reset_Services_Listener::class)->args([service('services_resetter')])->set('messenger.listener.reset_memory_usage', Reset_Memory_Usage_Listener::class)->tag('kernel.event_subscriber')->set('messenger.routable_message_bus', Routable_Message_Bus::class)->args([abstract_arg('message bus locator'), service('messenger.default_bus')])->set('messenger.redispatch_message_handler', Redispatch_Message_Handler::class)->args([service('messenger.default_bus')])->tag('messenger.message_handler');
};