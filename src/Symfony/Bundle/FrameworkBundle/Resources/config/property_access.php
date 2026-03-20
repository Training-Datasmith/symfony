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

use Symfony\Component\Property_Access\Property_Accessor;
use Symfony\Component\Property_Access\Property_Accessor_Interface;
use Symfony\Component\Property_Info\Property_Read_Info_Extractor_Interface;
use Symfony\Component\Property_Info\Property_Write_Info_Extractor_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('property_accessor', Property_Accessor::class)->args([abstract_arg('magic methods allowed, set by the extension'), abstract_arg('throw exceptions, set by the extension'), service('cache.property_access')->ignore_on_invalid(), service(Property_Read_Info_Extractor_Interface::class)->null_on_invalid(), service(Property_Write_Info_Extractor_Interface::class)->null_on_invalid()])->alias(Property_Accessor_Interface::class, 'property_accessor');
};