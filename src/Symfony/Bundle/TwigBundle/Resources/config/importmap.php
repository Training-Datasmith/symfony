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

use Symfony\Bridge\Twig\Extension\Import_Map_Extension;
use Symfony\Bridge\Twig\Extension\Import_Map_Runtime;
return static function (Container_Configurator $container): void {
    $container->services()->set('twig.runtime.importmap', Import_Map_Runtime::class)->args([service('asset_mapper.importmap.renderer')])->tag('twig.runtime')->set('twig.extension.importmap', Import_Map_Extension::class)->tag('twig.extension');
};