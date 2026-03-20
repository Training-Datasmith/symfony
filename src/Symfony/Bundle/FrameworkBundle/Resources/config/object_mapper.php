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

use Symfony\Component\Object_Mapper\Metadata\Object_Mapper_Metadata_Factory_Interface;
use Symfony\Component\Object_Mapper\Metadata\Reflection_Object_Mapper_Metadata_Factory;
use Symfony\Component\Object_Mapper\Metadata\Reverse_Class_Object_Mapper_Metadata_Factory;
use Symfony\Component\Object_Mapper\Object_Mapper;
use Symfony\Component\Object_Mapper\Object_Mapper_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('object_mapper.metadata_factory', Reflection_Object_Mapper_Metadata_Factory::class)->alias(Object_Mapper_Metadata_Factory_Interface::class, 'object_mapper.metadata_factory')->set('object_mapper.metadata_factory.reverse_class', Reverse_Class_Object_Mapper_Metadata_Factory::class)->decorate('object_mapper.metadata_factory')->args([service('.inner'), abstract_arg('class_map')])->set('object_mapper', Object_Mapper::class)->args([service('object_mapper.metadata_factory'), service('property_accessor')->ignore_on_invalid(), tagged_locator('object_mapper.transform_callable'), tagged_locator('object_mapper.condition_callable')])->alias(Object_Mapper_Interface::class, 'object_mapper');
};