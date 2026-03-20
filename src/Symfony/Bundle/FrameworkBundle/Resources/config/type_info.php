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

use Symfony\Component\Type_Info\Type_Context\Type_Context_Factory;
use Symfony\Component\Type_Info\Type_Resolver\Reflection_Parameter_Type_Resolver;
use Symfony\Component\Type_Info\Type_Resolver\Reflection_Property_Type_Resolver;
use Symfony\Component\Type_Info\Type_Resolver\Reflection_Return_Type_Resolver;
use Symfony\Component\Type_Info\Type_Resolver\Reflection_Type_Resolver;
use Symfony\Component\Type_Info\Type_Resolver\Type_Resolver;
use Symfony\Component\Type_Info\Type_Resolver\Type_Resolver_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('type_info.type_context_factory', Type_Context_Factory::class)->args([service('type_info.resolver.string')->null_on_invalid(), []])->set('type_info.resolver', Type_Resolver::class)->args([service_locator([\Reflection_Type::class => service('type_info.resolver.reflection_type'), \ReflectionParameter::class => service('type_info.resolver.reflection_parameter'), \ReflectionProperty::class => service('type_info.resolver.reflection_property'), \Reflection_Function_Abstract::class => service('type_info.resolver.reflection_return')])])->alias(Type_Resolver_Interface::class, 'type_info.resolver')->set('type_info.resolver.reflection_type', Reflection_Type_Resolver::class)->args([service('type_info.type_context_factory')])->set('type_info.resolver.reflection_parameter', Reflection_Parameter_Type_Resolver::class)->args([service('type_info.resolver.reflection_type'), service('type_info.type_context_factory')])->set('type_info.resolver.reflection_property', Reflection_Property_Type_Resolver::class)->args([service('type_info.resolver.reflection_type'), service('type_info.type_context_factory')])->set('type_info.resolver.reflection_return', Reflection_Return_Type_Resolver::class)->args([service('type_info.resolver.reflection_type'), service('type_info.type_context_factory')]);
};