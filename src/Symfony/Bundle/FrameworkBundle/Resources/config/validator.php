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

use Symfony\Bundle\Framework_Bundle\Cache_Warmer\Validator_Cache_Warmer;
use Symfony\Component\Cache\Adapter\Php_Array_Adapter;
use Symfony\Component\Clock\Clock_Interface;
use Symfony\Component\Expression_Language\Expression_Language;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Constraints\Email_Validator;
use Symfony\Component\Validator\Constraints\Expression_Language_Provider;
use Symfony\Component\Validator\Constraints\Expression_Validator;
use Symfony\Component\Validator\Constraints\No_Suspicious_Characters_Validator;
use Symfony\Component\Validator\Constraints\Not_Compromised_Password_Validator;
use Symfony\Component\Validator\Constraints\When_Validator;
use Symfony\Component\Validator\Container_Constraint_Validator_Factory;
use Symfony\Component\Validator\Mapping\Loader\Property_Info_Loader;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\Validator_Interface;
use Symfony\Component\Validator\Validator_Builder;
return static function (Container_Configurator $container): void {
    $container->parameters()->set('validator.mapping.cache.file', '%kernel.build_dir%/validation.php');
    $validators_dir = \dirname((new \ReflectionClass(Email_Validator::class))->get_file_name());
    $container->services()->set('validator', Validator_Interface::class)->factory([service('validator.builder'), 'getValidator'])->alias(Validator_Interface::class, 'validator')->set('validator.builder', Validator_Builder::class)->factory([Validation::class, 'createValidatorBuilder'])->call('setConstraintValidatorFactory', [service('validator.validator_factory')])->call('setGroupProviderLocator', [tagged_locator('validator.group_provider')])->call('setTranslator', [service('translator')->ignore_on_invalid()])->call('setTranslationDomain', [param('validator.translation_domain')])->alias('validator.mapping.class_metadata_factory', 'validator')->set('validator.mapping.cache_warmer', Validator_Cache_Warmer::class)->args([service('validator.builder'), param('validator.mapping.cache.file')])->tag('kernel.cache_warmer')->set('validator.mapping.cache.adapter', Php_Array_Adapter::class)->factory([Php_Array_Adapter::class, 'create'])->args([param('validator.mapping.cache.file'), service('cache.validator')])->set('validator.validator_factory', Container_Constraint_Validator_Factory::class)->args([abstract_arg('Constraint validators locator')])->load('Symfony\Component\Validator\Constraints\\', $validators_dir . '/*Validator.php')->abstract()->tag('container.excluded')->tag('validator.constraint_validator')->bind(Clock_Interface::class, service('clock')->null_on_invalid())->set('validator.expression', Expression_Validator::class)->args([service('validator.expression_language')->null_on_invalid()])->tag('validator.constraint_validator', ['alias' => 'validator.expression'])->set('validator.expression_language', Expression_Language::class)->args([service('cache.validator_expression_language')->null_on_invalid()])->call('registerProvider', [service('validator.expression_language_provider')->ignore_on_invalid()])->set('cache.validator_expression_language')->parent('cache.system')->private()->tag('cache.pool')->set('validator.expression_language_provider', Expression_Language_Provider::class)->set('validator.email', Email_Validator::class)->args([abstract_arg('Default mode')])->tag('validator.constraint_validator')->set('validator.not_compromised_password', Not_Compromised_Password_Validator::class)->args([service('http_client')->null_on_invalid(), param('kernel.charset'), false])->tag('validator.constraint_validator')->set('validator.when', When_Validator::class)->args([service('validator.expression_language')->null_on_invalid()])->tag('validator.constraint_validator')->set('validator.no_suspicious_characters', No_Suspicious_Characters_Validator::class)->args([param('kernel.enabled_locales')])->tag('validator.constraint_validator', ['alias' => No_Suspicious_Characters_Validator::class])->set('validator.property_info_loader', Property_Info_Loader::class)->args([service('property_info'), service('property_info'), service('property_info')])->tag('validator.auto_mapper')->set('validator.form.attribute_metadata', Form::class)->tag('container.excluded')->tag('validator.attribute_metadata');
};