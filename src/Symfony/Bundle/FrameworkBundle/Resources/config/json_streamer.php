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

use Symfony\Component\Json_Streamer\Cache_Warmer\Streamer_Cache_Warmer;
use Symfony\Component\Json_Streamer\Json_Stream_Reader;
use Symfony\Component\Json_Streamer\Json_Stream_Writer;
use Symfony\Component\Json_Streamer\Mapping\Generic_Type_Property_Metadata_Loader;
use Symfony\Component\Json_Streamer\Mapping\Property_Metadata_Loader;
use Symfony\Component\Json_Streamer\Mapping\Read\Attribute_Property_Metadata_Loader as ReadAttributePropertyMetadataLoader;
use Symfony\Component\Json_Streamer\Mapping\Read\Date_Time_Type_Property_Metadata_Loader as ReadDateTimeTypePropertyMetadataLoader;
use Symfony\Component\Json_Streamer\Mapping\Write\Attribute_Property_Metadata_Loader as WriteAttributePropertyMetadataLoader;
use Symfony\Component\Json_Streamer\Mapping\Write\Date_Time_Type_Property_Metadata_Loader as WriteDateTimeTypePropertyMetadataLoader;
use Symfony\Component\Json_Streamer\Value_Transformer\Date_Time_To_String_Value_Transformer;
use Symfony\Component\Json_Streamer\Value_Transformer\String_To_Date_Time_Value_Transformer;
return static function (Container_Configurator $container): void {
    $container->services()->set('json_streamer.stream_writer', Json_Stream_Writer::class)->args([tagged_locator('json_streamer.value_transformer'), service('json_streamer.write.property_metadata_loader'), param('.json_streamer.stream_writers_dir'), service('config_cache_factory')->ignore_on_invalid(), param('.json_streamer.default_options')])->set('json_streamer.stream_reader', Json_Stream_Reader::class)->args([tagged_locator('json_streamer.value_transformer'), service('json_streamer.read.property_metadata_loader'), param('.json_streamer.stream_readers_dir'), service('config_cache_factory')->ignore_on_invalid(), param('.json_streamer.default_options')])->alias(Json_Stream_Writer::class, 'json_streamer.stream_writer')->alias(Json_Stream_Reader::class, 'json_streamer.stream_reader')->set('json_streamer.write.property_metadata_loader', Property_Metadata_Loader::class)->args([service('type_info.resolver')])->set('.json_streamer.write.property_metadata_loader.generic', Generic_Type_Property_Metadata_Loader::class)->decorate('json_streamer.write.property_metadata_loader')->args([service('.inner'), service('type_info.type_context_factory')])->set('.json_streamer.write.property_metadata_loader.date_time', Write_Date_Time_Type_Property_Metadata_Loader::class)->decorate('json_streamer.write.property_metadata_loader')->args([service('.inner')])->set('.json_streamer.write.property_metadata_loader.attribute', Write_Attribute_Property_Metadata_Loader::class)->decorate('json_streamer.write.property_metadata_loader')->args([service('.inner'), tagged_locator('json_streamer.value_transformer'), service('type_info.resolver')])->set('json_streamer.read.property_metadata_loader', Property_Metadata_Loader::class)->args([service('type_info.resolver')])->set('.json_streamer.read.property_metadata_loader.generic', Generic_Type_Property_Metadata_Loader::class)->decorate('json_streamer.read.property_metadata_loader')->args([service('.inner'), service('type_info.type_context_factory')])->set('.json_streamer.read.property_metadata_loader.date_time', Read_Date_Time_Type_Property_Metadata_Loader::class)->decorate('json_streamer.read.property_metadata_loader')->args([service('.inner')])->set('.json_streamer.read.property_metadata_loader.attribute', Read_Attribute_Property_Metadata_Loader::class)->decorate('json_streamer.read.property_metadata_loader')->args([service('.inner'), tagged_locator('json_streamer.value_transformer'), service('type_info.resolver')])->set('json_streamer.value_transformer.date_time_to_string', Date_Time_To_String_Value_Transformer::class)->tag('json_streamer.value_transformer')->set('json_streamer.value_transformer.string_to_date_time', String_To_Date_Time_Value_Transformer::class)->tag('json_streamer.value_transformer')->set('.json_streamer.cache_warmer.streamer', Streamer_Cache_Warmer::class)->args([abstract_arg('streamable'), service('json_streamer.write.property_metadata_loader'), service('json_streamer.read.property_metadata_loader'), param('.json_streamer.stream_writers_dir'), param('.json_streamer.stream_readers_dir'), service('logger')->ignore_on_invalid(), service('config_cache_factory')->ignore_on_invalid()])->tag('kernel.cache_warmer');
};