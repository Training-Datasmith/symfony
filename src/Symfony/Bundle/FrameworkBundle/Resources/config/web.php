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

use Symfony\Bundle\Framework_Bundle\Controller\Abstract_Controller;
use Symfony\Bundle\Framework_Bundle\Controller\Controller_Helper;
use Symfony\Bundle\Framework_Bundle\Controller\Controller_Resolver;
use Symfony\Bundle\Framework_Bundle\Controller\Template_Controller;
use Symfony\Component\Expression_Language\Expression_Language;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Backed_Enum_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Date_Time_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Default_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Query_Parameter_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Request_Attribute_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Request_Payload_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Request_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Service_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Session_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Uid_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Variadic_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Error_Controller;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata_Factory;
use Symfony\Component\Http_Kernel\Event_Listener\Cache_Attribute_Listener;
use Symfony\Component\Http_Kernel\Event_Listener\Controller_Attributes_Listener;
use Symfony\Component\Http_Kernel\Event_Listener\Disallow_Robots_Indexing_Listener;
use Symfony\Component\Http_Kernel\Event_Listener\Error_Listener;
use Symfony\Component\Http_Kernel\Event_Listener\Is_Signature_Valid_Attribute_Listener;
use Symfony\Component\Http_Kernel\Event_Listener\Locale_Listener;
use Symfony\Component\Http_Kernel\Event_Listener\Response_Listener;
use Symfony\Component\Http_Kernel\Event_Listener\Serialize_Controller_Result_Attribute_Listener;
use Symfony\Component\Http_Kernel\Event_Listener\Validate_Request_Listener;
return static function (Container_Configurator $container): void {
    $container->services()->set('controller_resolver', Controller_Resolver::class)->args([service('service_container'), service('logger')->ignore_on_invalid()])->call('allowControllers', [[Abstract_Controller::class, Template_Controller::class]])->tag('monolog.logger', ['channel' => 'request'])->set('argument_metadata_factory', Argument_Metadata_Factory::class)->set('argument_resolver', Argument_Resolver::class)->args([service('argument_metadata_factory'), abstract_arg('argument value resolvers'), abstract_arg('targeted value resolvers')])->set('argument_resolver.backed_enum_resolver', Backed_Enum_Value_Resolver::class)->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => Backed_Enum_Value_Resolver::class])->set('argument_resolver.uid', Uid_Value_Resolver::class)->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => Uid_Value_Resolver::class])->set('argument_resolver.datetime', Date_Time_Value_Resolver::class)->args([service('clock')->null_on_invalid()])->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => Date_Time_Value_Resolver::class])->set('argument_resolver.request_payload', Request_Payload_Value_Resolver::class)->args([service('serializer'), service('validator')->null_on_invalid(), service('translator')->null_on_invalid(), param('validator.translation_domain'), service('controller.expression_language')->null_on_invalid()])->tag('controller.targeted_value_resolver', ['name' => Request_Payload_Value_Resolver::class])->tag('kernel.event_subscriber')->lazy()->set('argument_resolver.request_attribute', Request_Attribute_Value_Resolver::class)->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => Request_Attribute_Value_Resolver::class])->set('argument_resolver.request', Request_Value_Resolver::class)->tag('controller.argument_value_resolver', ['priority' => 50, 'name' => Request_Value_Resolver::class])->set('argument_resolver.session', Session_Value_Resolver::class)->tag('controller.argument_value_resolver', ['priority' => 50, 'name' => Session_Value_Resolver::class])->set('argument_resolver.service', Service_Value_Resolver::class)->args([abstract_arg('service locator, set in RegisterControllerArgumentLocatorsPass')])->tag('controller.argument_value_resolver', ['priority' => -50, 'name' => Service_Value_Resolver::class])->set('argument_resolver.default', Default_Value_Resolver::class)->tag('controller.argument_value_resolver', ['priority' => -100, 'name' => Default_Value_Resolver::class])->set('argument_resolver.variadic', Variadic_Value_Resolver::class)->tag('controller.argument_value_resolver', ['priority' => -150, 'name' => Variadic_Value_Resolver::class])->set('argument_resolver.query_parameter_value_resolver', Query_Parameter_Value_Resolver::class)->tag('controller.targeted_value_resolver', ['name' => Query_Parameter_Value_Resolver::class])->set('response_listener', Response_Listener::class)->args([param('kernel.charset'), abstract_arg('The "set_content_language_from_locale" config value')])->tag('kernel.event_subscriber')->set('locale_listener', Locale_Listener::class)->args([service('request_stack'), param('kernel.default_locale'), service('router')->ignore_on_invalid(), abstract_arg('The "set_locale_from_accept_language" config value'), param('kernel.enabled_locales')])->tag('kernel.event_subscriber')->set('validate_request_listener', Validate_Request_Listener::class)->tag('kernel.event_subscriber')->set('disallow_search_engine_index_response_listener', Disallow_Robots_Indexing_Listener::class)->tag('kernel.event_subscriber')->set('error_controller', Error_Controller::class)->public()->args([service('http_kernel'), param('kernel.error_controller'), service('error_renderer')])->set('exception_listener', Error_Listener::class)->args([param('kernel.error_controller'), service('logger')->null_on_invalid(), param('kernel.debug'), abstract_arg('an exceptions to log & status code mapping'), abstract_arg('list of loggers by log_channel')])->tag('kernel.event_subscriber')->tag('monolog.logger', ['channel' => 'request'])->set('kernel.controller_attributes_listener', Controller_Attributes_Listener::class)->args([abstract_arg('attributes with listeners by event'), service('controller.expression_language')->null_on_invalid()])->tag('kernel.event_subscriber')->set('controller.cache_attribute_listener', Cache_Attribute_Listener::class)->tag('kernel.event_subscriber')->tag('kernel.reset', ['method' => '?reset'])->set('controller.is_signature_valid_attribute_listener', Is_Signature_Valid_Attribute_Listener::class)->args([service('uri_signer')])->tag('kernel.event_subscriber')->set('controller.helper', Controller_Helper::class)->tag('container.service_subscriber')->alias(Controller_Helper::class, 'controller.helper')->set('controller.expression_language', Expression_Language::class)->args([service('cache.controller_expression_language')->null_on_invalid()])->set('cache.controller_expression_language')->parent('cache.system')->private()->tag('cache.pool')->set('serialize_controller_result_listener', Serialize_Controller_Result_Attribute_Listener::class)->args([service('serializer')->null_on_invalid()])->tag('kernel.event_subscriber');
};