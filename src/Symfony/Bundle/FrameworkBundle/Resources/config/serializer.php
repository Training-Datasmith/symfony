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

use Psr\Cache\Cache_Item_Pool_Interface;
use Symfony\Bundle\Framework_Bundle\Cache_Warmer\Serializer_Cache_Warmer;
use Symfony\Component\Cache\Adapter\Php_Array_Adapter;
use Symfony\Component\Error_Handler\Error_Renderer\Error_Renderer_Interface;
use Symfony\Component\Error_Handler\Error_Renderer\Html_Error_Renderer;
use Symfony\Component\Error_Handler\Error_Renderer\Serializer_Error_Renderer;
use Symfony\Component\Property_Info\Extractor\Serializer_Extractor;
use Symfony\Component\Serializer\Encoder\Csv_Encoder;
use Symfony\Component\Serializer\Encoder\Decoder_Interface;
use Symfony\Component\Serializer\Encoder\Encoder_Interface;
use Symfony\Component\Serializer\Encoder\Json_Encoder;
use Symfony\Component\Serializer\Encoder\Xml_Encoder;
use Symfony\Component\Serializer\Encoder\Yaml_Encoder;
use Symfony\Component\Serializer\Mapping\Class_Discriminator_From_Class_Metadata;
use Symfony\Component\Serializer\Mapping\Class_Discriminator_Resolver_Interface;
use Symfony\Component\Serializer\Mapping\Factory\Cache_Class_Metadata_Factory;
use Symfony\Component\Serializer\Mapping\Factory\Class_Metadata_Factory;
use Symfony\Component\Serializer\Mapping\Factory\Class_Metadata_Factory_Interface;
use Symfony\Component\Serializer\Mapping\Loader\Attribute_Loader;
use Symfony\Component\Serializer\Mapping\Loader\Loader_Chain;
use Symfony\Component\Serializer\Name_Converter\Camel_Case_To_Snake_Case_Name_Converter;
use Symfony\Component\Serializer\Name_Converter\Metadata_Aware_Name_Converter;
use Symfony\Component\Serializer\Name_Converter\Snake_Case_To_Camel_Case_Name_Converter;
use Symfony\Component\Serializer\Normalizer\Array_Denormalizer;
use Symfony\Component\Serializer\Normalizer\Backed_Enum_Normalizer;
use Symfony\Component\Serializer\Normalizer\Constraint_Violation_List_Normalizer;
use Symfony\Component\Serializer\Normalizer\Data_Uri_Normalizer;
use Symfony\Component\Serializer\Normalizer\Date_Interval_Normalizer;
use Symfony\Component\Serializer\Normalizer\Date_Time_Normalizer;
use Symfony\Component\Serializer\Normalizer\Date_Time_Zone_Normalizer;
use Symfony\Component\Serializer\Normalizer\Denormalizer_Interface;
use Symfony\Component\Serializer\Normalizer\Form_Error_Normalizer;
use Symfony\Component\Serializer\Normalizer\Json_Serializable_Normalizer;
use Symfony\Component\Serializer\Normalizer\Mime_Message_Normalizer;
use Symfony\Component\Serializer\Normalizer\Normalizer_Interface;
use Symfony\Component\Serializer\Normalizer\Number_Normalizer;
use Symfony\Component\Serializer\Normalizer\Object_Normalizer;
use Symfony\Component\Serializer\Normalizer\Problem_Normalizer;
use Symfony\Component\Serializer\Normalizer\Property_Normalizer;
use Symfony\Component\Serializer\Normalizer\Translatable_Normalizer;
use Symfony\Component\Serializer\Normalizer\Uid_Normalizer;
use Symfony\Component\Serializer\Normalizer\Unwrapping_Denormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Serializer_Interface;
return static function (Container_Configurator $container): void {
    $container->parameters()->set('serializer.mapping.cache.file', '%kernel.build_dir%/serialization.php');
    $container->services()->set('serializer', Serializer::class)->args([[], [], []])->alias(Serializer_Interface::class, 'serializer')->alias(Normalizer_Interface::class, 'serializer')->alias(Denormalizer_Interface::class, 'serializer')->alias(Encoder_Interface::class, 'serializer')->alias(Decoder_Interface::class, 'serializer')->alias('serializer.property_accessor', 'property_accessor')->set('serializer.mapping.class_discriminator_resolver', Class_Discriminator_From_Class_Metadata::class)->args([service('serializer.mapping.class_metadata_factory')])->alias(Class_Discriminator_Resolver_Interface::class, 'serializer.mapping.class_discriminator_resolver')->set('serializer.normalizer.constraint_violation_list', Constraint_Violation_List_Normalizer::class)->args([1 => service('serializer.name_converter.metadata_aware')])->autowire(true)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -915])->set('serializer.normalizer.mime_message', Mime_Message_Normalizer::class)->args([service('serializer.normalizer.property')])->tag('serializer.normalizer', ['built_in' => true, 'priority' => -915])->set('serializer.normalizer.datetimezone', Date_Time_Zone_Normalizer::class)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -915])->set('serializer.normalizer.dateinterval', Date_Interval_Normalizer::class)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -915])->set('serializer.normalizer.data_uri', Data_Uri_Normalizer::class)->args([service('mime_types')->null_on_invalid()])->tag('serializer.normalizer', ['built_in' => true, 'priority' => -920])->set('serializer.normalizer.datetime', Date_Time_Normalizer::class)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -910])->set('serializer.normalizer.json_serializable', Json_Serializable_Normalizer::class)->args([null, null])->tag('serializer.normalizer', ['built_in' => true, 'priority' => -950])->set('serializer.normalizer.problem', Problem_Normalizer::class)->args([param('kernel.debug'), '$translator' => service('translator')->null_on_invalid()])->tag('serializer.normalizer', ['built_in' => true, 'priority' => -890])->set('serializer.denormalizer.unwrapping', Unwrapping_Denormalizer::class)->args([service('serializer.property_accessor')])->tag('serializer.normalizer', ['built_in' => true, 'priority' => 1000])->set('serializer.normalizer.uid', Uid_Normalizer::class)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -890])->set('serializer.normalizer.translatable', Translatable_Normalizer::class)->args(['$translator' => service('translator')])->tag('serializer.normalizer', ['built_in' => true, 'priority' => -920])->set('serializer.normalizer.form_error', Form_Error_Normalizer::class)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -915])->set('serializer.normalizer.object', Object_Normalizer::class)->args([service('serializer.mapping.class_metadata_factory'), service('serializer.name_converter.metadata_aware'), service('serializer.property_accessor'), service('property_info')->ignore_on_invalid(), service('serializer.mapping.class_discriminator_resolver')->ignore_on_invalid(), null, abstract_arg('default context, set in the SerializerPass'), service('property_info')->ignore_on_invalid()])->tag('serializer.normalizer', ['built_in' => true, 'priority' => -1000])->set('serializer.normalizer.property', Property_Normalizer::class)->args([service('serializer.mapping.class_metadata_factory'), service('serializer.name_converter.metadata_aware'), service('property_info')->ignore_on_invalid(), service('serializer.mapping.class_discriminator_resolver')->ignore_on_invalid(), null])->set('serializer.denormalizer.array', Array_Denormalizer::class)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -990])->set('serializer.mapping.chain_loader', Loader_Chain::class)->args([[]])->set('serializer.mapping.attribute_loader', Attribute_Loader::class)->args([true, []])->set('serializer.mapping.class_metadata_factory', Class_Metadata_Factory::class)->args([service('serializer.mapping.chain_loader')])->alias(Class_Metadata_Factory_Interface::class, 'serializer.mapping.class_metadata_factory')->set('serializer.mapping.cache_warmer', Serializer_Cache_Warmer::class)->args([abstract_arg('The serializer metadata loaders'), param('serializer.mapping.cache.file')])->tag('kernel.cache_warmer')->set('serializer.mapping.cache.symfony', Cache_Item_Pool_Interface::class)->factory([Php_Array_Adapter::class, 'create'])->args([param('serializer.mapping.cache.file'), service('cache.serializer')])->set('serializer.mapping.cache_class_metadata_factory', Cache_Class_Metadata_Factory::class)->decorate('serializer.mapping.class_metadata_factory')->args([service('serializer.mapping.cache_class_metadata_factory.inner'), service('serializer.mapping.cache.symfony')])->set('serializer.encoder.xml', Xml_Encoder::class)->tag('serializer.encoder', ['built_in' => true])->set('serializer.encoder.json', Json_Encoder::class)->args([null, null])->tag('serializer.encoder', ['built_in' => true])->set('serializer.encoder.yaml', Yaml_Encoder::class)->args([null, null])->tag('serializer.encoder', ['built_in' => true])->set('serializer.encoder.csv', Csv_Encoder::class)->tag('serializer.encoder', ['built_in' => true])->set('serializer.name_converter.camel_case_to_snake_case', Camel_Case_To_Snake_Case_Name_Converter::class)->set('serializer.name_converter.snake_case_to_camel_case', Snake_Case_To_Camel_Case_Name_Converter::class)->set('serializer.name_converter.metadata_aware.abstract', Metadata_Aware_Name_Converter::class)->abstract()->args([service('serializer.mapping.class_metadata_factory')])->set('serializer.name_converter.metadata_aware')->parent('serializer.name_converter.metadata_aware.abstract')->set('property_info.serializer_extractor', Serializer_Extractor::class)->args([service('serializer.mapping.class_metadata_factory')])->tag('property_info.list_extractor', ['priority' => -999])->alias('error_renderer', 'error_renderer.serializer')->alias('error_renderer.serializer', 'error_handler.error_renderer.serializer')->set('error_handler.error_renderer.serializer', Serializer_Error_Renderer::class)->args([service('serializer'), inline_service()->factory([Serializer_Error_Renderer::class, 'getPreferredFormat'])->args([service('request_stack')]), inline_service(Error_Renderer_Interface::class)->factory([\Closure::class, 'fromCallable'])->args([[service('error_renderer.default'), 'render']])->lazy(), inline_service()->factory([Html_Error_Renderer::class, 'isDebug'])->args([service('request_stack'), param('kernel.debug')])])->set('serializer.normalizer.backed_enum', Backed_Enum_Normalizer::class)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -915])->set('serializer.normalizer.number', Number_Normalizer::class)->tag('serializer.normalizer', ['built_in' => true, 'priority' => -915]);
};