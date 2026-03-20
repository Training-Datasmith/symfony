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

use Symfony\Component\Property_Info\Extractor\Constructor_Extractor;
use Symfony\Component\Property_Info\Extractor\Reflection_Extractor;
use Symfony\Component\Property_Info\Property_Access_Extractor_Interface;
use Symfony\Component\Property_Info\Property_Description_Extractor_Interface;
use Symfony\Component\Property_Info\Property_Info_Cache_Extractor;
use Symfony\Component\Property_Info\Property_Info_Extractor;
use Symfony\Component\Property_Info\Property_Info_Extractor_Interface;
use Symfony\Component\Property_Info\Property_Initializable_Extractor_Interface;
use Symfony\Component\Property_Info\Property_List_Extractor_Interface;
use Symfony\Component\Property_Info\Property_Read_Info_Extractor_Interface;
use Symfony\Component\Property_Info\Property_Type_Extractor_Interface;
use Symfony\Component\Property_Info\Property_Write_Info_Extractor_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('property_info', Property_Info_Extractor::class)->args([[], [], [], [], []])->alias(Property_Access_Extractor_Interface::class, 'property_info')->alias(Property_Description_Extractor_Interface::class, 'property_info')->alias(Property_Info_Extractor_Interface::class, 'property_info')->alias(Property_Type_Extractor_Interface::class, 'property_info')->alias(Property_List_Extractor_Interface::class, 'property_info')->alias(Property_Initializable_Extractor_Interface::class, 'property_info')->set('property_info.cache', Property_Info_Cache_Extractor::class)->decorate('property_info')->args([service('property_info.cache.inner'), service('cache.property_info')])->set('property_info.reflection_extractor', Reflection_Extractor::class)->tag('property_info.list_extractor', ['priority' => -1000])->tag('property_info.type_extractor', ['priority' => -1002])->tag('property_info.constructor_extractor', ['priority' => -1002])->tag('property_info.access_extractor', ['priority' => -1000])->tag('property_info.initializable_extractor', ['priority' => -1000])->alias(Property_Read_Info_Extractor_Interface::class, 'property_info.reflection_extractor')->alias(Property_Write_Info_Extractor_Interface::class, 'property_info.reflection_extractor')->set('property_info.constructor_extractor', Constructor_Extractor::class)->args([[]])->tag('property_info.type_extractor', ['priority' => -999]);
};