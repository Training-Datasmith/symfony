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

use Symfony\Component\Serializer\Data_Collector\Serializer_Data_Collector;
use Symfony\Component\Serializer\Debug\Traceable_Serializer;
return static function (Container_Configurator $container): void {
    $container->services()->set('debug.serializer', Traceable_Serializer::class)->decorate('serializer')->args([service('debug.serializer.inner'), service('serializer.data_collector'), 'default'])->set('serializer.data_collector', Serializer_Data_Collector::class)->tag('data_collector', ['template' => '@WebProfiler/Collector/serializer.html.twig', 'id' => 'serializer']);
};