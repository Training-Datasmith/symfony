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

use Symfony\Component\Http_Foundation\Session\Session_Factory;
use Symfony\Component\Http_Foundation\Session\Storage\Handler\Abstract_Session_Handler;
use Symfony\Component\Http_Foundation\Session\Storage\Handler\Identity_Marshaller;
use Symfony\Component\Http_Foundation\Session\Storage\Handler\Marshalling_Session_Handler;
use Symfony\Component\Http_Foundation\Session\Storage\Handler\Native_File_Session_Handler;
use Symfony\Component\Http_Foundation\Session\Storage\Handler\Session_Handler_Factory;
use Symfony\Component\Http_Foundation\Session\Storage\Handler\Strict_Session_Handler;
use Symfony\Component\Http_Foundation\Session\Storage\Metadata_Bag;
use Symfony\Component\Http_Foundation\Session\Storage\Mock_File_Session_Storage_Factory;
use Symfony\Component\Http_Foundation\Session\Storage\Native_Session_Storage_Factory;
use Symfony\Component\Http_Foundation\Session\Storage\Php_Bridge_Session_Storage_Factory;
use Symfony\Component\Http_Kernel\Event_Listener\Session_Listener;
return static function (Container_Configurator $container): void {
    $container->parameters()->set('session.metadata.storage_key', '_sf2_meta');
    $container->services()->set('session.factory', Session_Factory::class)->args([service('request_stack'), service('session.storage.factory'), [service('session_listener'), 'onSessionUsage']])->set('session.storage.factory.native', Native_Session_Storage_Factory::class)->args([param('session.storage.options'), service('session.handler'), inline_service(Metadata_Bag::class)->args([param('session.metadata.storage_key'), param('session.metadata.update_threshold')]), false])->set('session.storage.factory.php_bridge', Php_Bridge_Session_Storage_Factory::class)->args([service('session.handler'), inline_service(Metadata_Bag::class)->args([param('session.metadata.storage_key'), param('session.metadata.update_threshold')]), false])->set('session.storage.factory.mock_file', Mock_File_Session_Storage_Factory::class)->args([param('kernel.cache_dir') . '/sessions', 'MOCKSESSID', inline_service(Metadata_Bag::class)->args([param('session.metadata.storage_key'), param('session.metadata.update_threshold')])])->alias(\Session_Handler_Interface::class, 'session.handler')->set('session.handler.native', Strict_Session_Handler::class)->args([inline_service(\Session_Handler::class)])->set('session.handler.native_file', Strict_Session_Handler::class)->args([inline_service(Native_File_Session_Handler::class)->args([param('session.save_path')])])->set('session.abstract_handler', Abstract_Session_Handler::class)->factory([Session_Handler_Factory::class, 'createHandler'])->args([abstract_arg('A string or a connection object'), []])->set('session_listener', Session_Listener::class)->args([service_locator(['session_factory' => service('session.factory')->ignore_on_invalid(), 'logger' => service('logger')->ignore_on_invalid(), 'session_collector' => service('data_collector.request.session_collector')->ignore_on_invalid(), 'request_stack' => service('request_stack')->ignore_on_invalid()]), param('kernel.debug'), param('session.storage.options')])->tag('kernel.event_subscriber')->tag('kernel.reset', ['method' => 'reset'])->set('session.marshaller', Identity_Marshaller::class)->set('session.marshalling_handler', Marshalling_Session_Handler::class)->decorate('session.handler')->args([service('session.marshalling_handler.inner'), service('session.marshaller')]);
};