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

use Symfony\Component\Form\Choice_List\Factory\Caching_Factory_Decorator;
use Symfony\Component\Form\Choice_List\Factory\Default_Choice_List_Factory;
use Symfony\Component\Form\Choice_List\Factory\Property_Access_Decorator;
use Symfony\Component\Form\Enum_Form_Type_Guesser;
use Symfony\Component\Form\Extension\Core\Type\Choice_Type;
use Symfony\Component\Form\Extension\Core\Type\Color_Type;
use Symfony\Component\Form\Extension\Core\Type\File_Type;
use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Extension\Core\Type\Submit_Type;
use Symfony\Component\Form\Extension\Core\Type\Text_Type;
use Symfony\Component\Form\Extension\Core\Type\Transformation_Failure_Extension;
use Symfony\Component\Form\Extension\Dependency_Injection\Dependency_Injection_Extension;
use Symfony\Component\Form\Extension\Html_Sanitizer\Type\Text_Type_Html_Sanitizer_Extension;
use Symfony\Component\Form\Extension\Http_Foundation\Http_Foundation_Request_Handler;
use Symfony\Component\Form\Extension\Http_Foundation\Type\Form_Flow_Type_Session_Data_Storage_Extension;
use Symfony\Component\Form\Extension\Http_Foundation\Type\Form_Type_Http_Foundation_Extension;
use Symfony\Component\Form\Extension\Validator\Type\Form_Type_Validator_Extension;
use Symfony\Component\Form\Extension\Validator\Type\Repeated_Type_Validator_Extension;
use Symfony\Component\Form\Extension\Validator\Type\Submit_Type_Validator_Extension;
use Symfony\Component\Form\Extension\Validator\Type\Upload_Validator_Extension;
use Symfony\Component\Form\Extension\Validator\Validator_Type_Guesser;
use Symfony\Component\Form\Extension\Validator\Violation_Mapper\Violation_Mapper;
use Symfony\Component\Form\Extension\Validator\Violation_Mapper\Violation_Mapper_Interface;
use Symfony\Component\Form\Form_Factory;
use Symfony\Component\Form\Form_Factory_Interface;
use Symfony\Component\Form\Form_Registry;
use Symfony\Component\Form\Form_Registry_Interface;
use Symfony\Component\Form\Resolved_Form_Type_Factory;
use Symfony\Component\Form\Resolved_Form_Type_Factory_Interface;
use Symfony\Component\Form\Util\Server_Params;
return static function (Container_Configurator $container): void {
    $container->services()->set('form.resolved_type_factory', Resolved_Form_Type_Factory::class)->alias(Resolved_Form_Type_Factory_Interface::class, 'form.resolved_type_factory')->set('form.violation_mapper', Violation_Mapper::class)->args([service('twig.form.renderer')->ignore_on_invalid(), service('translator')->ignore_on_invalid()])->alias(Violation_Mapper_Interface::class, 'form.violation_mapper')->set('form.registry', Form_Registry::class)->args([[
        /*
         * We don't need to be able to add more extensions.
         * more types can be registered with the form.type tag
         * more type extensions can be registered with the form.type_extension tag
         * more type_guessers can be registered with the form.type_guesser tag
         */
        service('form.extension'),
    ], service('form.resolved_type_factory')])->alias(Form_Registry_Interface::class, 'form.registry')->set('form.factory', Form_Factory::class)->args([service('form.registry')])->alias(Form_Factory_Interface::class, 'form.factory')->set('form.extension', Dependency_Injection_Extension::class)->args([abstract_arg('All services with tag "form.type" are stored in a service locator by FormPass'), abstract_arg('All services with tag "form.type_extension" are stored here by FormPass'), abstract_arg('All services with tag "form.type_guesser" are stored here by FormPass')])->set('form.type_guesser.validator', Validator_Type_Guesser::class)->args([service('validator.mapping.class_metadata_factory')])->tag('form.type_guesser')->set('form.type_guesser.enum_type', Enum_Form_Type_Guesser::class)->tag('form.type_guesser')->alias('form.property_accessor', 'property_accessor')->set('form.choice_list_factory.default', Default_Choice_List_Factory::class)->set('form.choice_list_factory.property_access', Property_Access_Decorator::class)->args([service('form.choice_list_factory.default'), service('form.property_accessor')])->set('form.choice_list_factory.cached', Caching_Factory_Decorator::class)->args([service('form.choice_list_factory.property_access')])->tag('kernel.reset', ['method' => 'reset'])->alias('form.choice_list_factory', 'form.choice_list_factory.cached')->set('form.type.form', Form_Type::class)->args([service('form.property_accessor')])->tag('form.type')->set('form.type.choice', Choice_Type::class)->args([service('form.choice_list_factory'), service('translator')->ignore_on_invalid()])->tag('form.type')->set('form.type.file', File_Type::class)->args([service('translator')->ignore_on_invalid()])->tag('form.type')->set('form.type.color', Color_Type::class)->args([service('translator')->ignore_on_invalid()])->tag('form.type')->set('form.type_extension.form.transformation_failure_handling', Transformation_Failure_Extension::class)->args([service('translator')->ignore_on_invalid()])->tag('form.type_extension', ['extended-type' => Form_Type::class])->set('form.type_extension.form.html_sanitizer', Text_Type_Html_Sanitizer_Extension::class)->args([tagged_locator('html_sanitizer', 'sanitizer')])->tag('form.type_extension', ['extended-type' => Text_Type::class])->set('form.type_extension.form.http_foundation', Form_Type_Http_Foundation_Extension::class)->args([service('form.type_extension.form.request_handler')])->tag('form.type_extension')->set('form.type_extension.form.flow.session_data_storage', Form_Flow_Type_Session_Data_Storage_Extension::class)->args([service('request_stack')->ignore_on_invalid()])->tag('form.type_extension')->set('form.type_extension.form.request_handler', Http_Foundation_Request_Handler::class)->args([service('form.server_params')])->set('form.server_params', Server_Params::class)->args([service('request_stack')])->set('form.type_extension.form.validator', Form_Type_Validator_Extension::class)->args([service('validator'), service('form.violation_mapper'), service('twig.form.renderer')->ignore_on_invalid(), service('translator')->ignore_on_invalid()])->tag('form.type_extension', ['extended-type' => Form_Type::class])->set('form.type_extension.repeated.validator', Repeated_Type_Validator_Extension::class)->tag('form.type_extension')->set('form.type_extension.submit.validator', Submit_Type_Validator_Extension::class)->tag('form.type_extension', ['extended-type' => Submit_Type::class])->set('form.type_extension.upload.validator', Upload_Validator_Extension::class)->args([service('translator'), param('validator.translation_domain')])->tag('form.type_extension');
};